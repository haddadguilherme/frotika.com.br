<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BankAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_gestor_cadastra_conta_convertendo_reais_para_centavos(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany(1);

        $response = $this->actingAs($owner)->post(route('bank-accounts.store'), [
            'name' => 'Banco do Brasil',
            'type' => 'checking',
            'initial_balance' => '1.500,50',
            'initial_balance_at' => '2026-07-01',
            'is_default' => '1',
        ]);

        $response->assertRedirect(route('bank-accounts.index'));

        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $company->getKey(),
            'name' => 'Banco do Brasil',
            'type' => 'checking',
            'initial_balance_cents' => 150050,
            'current_balance_cents' => 150050,
            'is_default' => true,
        ]);
    }

    public function test_definir_nova_conta_padrao_remove_a_anterior(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany(2);

        $first = $this->createAccount($company, 'Caixa', true);

        $this->actingAs($owner)->post(route('bank-accounts.store'), [
            'name' => 'Nova padrão',
            'type' => 'digital',
            'initial_balance' => '0',
            'is_default' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_accounts', ['id' => $first, 'is_default' => false]);
        $this->assertDatabaseHas('bank_accounts', ['name' => 'Nova padrão', 'is_default' => true]);
    }

    public function test_conta_com_lancamento_vinculado_nao_pode_ser_removida(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany(3);
        $accountId = $this->createAccount($company, 'Caixa', true);
        $this->createEntryOnAccount($company, $accountId, $owner);

        $this->actingAs($owner)
            ->from(route('bank-accounts.index'))
            ->delete(route('bank-accounts.destroy', ['account' => $accountId]))
            ->assertSessionHasErrors('bank_account');

        $this->assertDatabaseHas('bank_accounts', ['id' => $accountId, 'deleted_at' => null]);
    }

    public function test_conta_sem_lancamento_e_removida(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany(4);
        $accountId = $this->createAccount($company, 'Descartável', false);

        $this->actingAs($owner)
            ->delete(route('bank-accounts.destroy', ['account' => $accountId]))
            ->assertRedirect(route('bank-accounts.index'));

        $this->assertSoftDeleted('bank_accounts', ['id' => $accountId]);
    }

    public function test_conta_de_outro_grupo_fica_invisivel(): void
    {
        [, $company] = $this->createOwnerWithCompany(5);
        $accountId = $this->createAccount($company, 'Caixa', true);

        [$intruder] = $this->createOwnerWithCompany(6);

        $this->actingAs($intruder)
            ->get(route('bank-accounts.edit', ['account' => $accountId]))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function createOwnerWithCompany(int $seed): array
    {
        $owner = User::factory()->create(['email' => 'bank-owner-'.$seed.'@example.com']);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Bank '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '11222333'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Bank Empresa '.$seed.' LTDA',
            'trade_name' => 'Bank Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);

        $owner->groups()->attach($group->getKey(), [
            'role' => 'owner',
            'invited_by' => null,
            'joined_at' => now(),
        ]);
        $owner->companies()->attach($company->getKey());
        $owner->forceFill([
            'current_group_id' => $group->getKey(),
            'current_company_id' => $company->getKey(),
        ])->save();

        return [$owner, $company];
    }

    private function createAccount(Company $company, string $name, bool $isDefault): int
    {
        return app(TenantContext::class)->runFor($company, function () use ($name, $isDefault): int {
            $account = BankAccount::query()->create([
                'name' => $name,
                'type' => 'cash',
                'initial_balance_cents' => 0,
                'current_balance_cents' => 0,
                'is_default' => $isDefault,
                'active' => true,
            ]);

            return (int) $account->getKey();
        });
    }

    private function createEntryOnAccount(Company $company, int $accountId, User $author): void
    {
        app(TenantContext::class)->runFor($company, function () use ($company, $accountId, $author): void {
            $category = FinancialCategory::query()->create([
                'code' => '3.9',
                'name' => 'Despesa teste',
                'type' => 'expense',
                'dre_group' => 'variable_cost',
                'allocation' => 'non_vehicle',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 390,
            ]);

            FinancialEntry::query()->create([
                'company_id' => $company->getKey(),
                'bank_account_id' => $accountId,
                'financial_category_id' => $category->getKey(),
                'type' => 'expense',
                'description' => 'Vinculada',
                'competence_date' => '2026-07-10',
                'paid_at' => '2026-07-10',
                'amount_cents' => 1000,
                'status' => 'settled',
                'created_by' => $author->getKey(),
            ]);
        });
    }
}
