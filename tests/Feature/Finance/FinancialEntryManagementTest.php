<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Models\CteDocument;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FinancialEntryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_despesa_liquidada_derivando_tipo_da_categoria(): void
    {
        [$owner, $company, $base] = $this->scenario(1);

        $response = $this->actingAs($owner)->post(route('financial-entries.store'), [
            'financial_category_id' => $base['expense'],
            'description' => 'Troca de óleo',
            'amount' => '1.250,00',
            'competence_date' => '2026-07-10',
            'status' => 'settled',
            'bank_account_id' => $base['account'],
            'paid_at' => '2026-07-11',
            'payment_method' => 'pix',
        ]);

        $response->assertRedirect(route('financial-entries.index'));

        $this->assertDatabaseHas('financial_entries', [
            'company_id' => $company->getKey(),
            'type' => 'expense',
            'description' => 'Troca de óleo',
            'amount_cents' => 125000,
            'status' => 'settled',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $base['account'],
            'current_balance_cents' => -125000,
        ]);
    }

    public function test_cria_receita_prevista_sem_conta(): void
    {
        [$owner, $company, $base] = $this->scenario(2);

        $this->actingAs($owner)->post(route('financial-entries.store'), [
            'financial_category_id' => $base['revenue'],
            'description' => 'Frete a receber',
            'amount' => '3.000,00',
            'competence_date' => '2026-07-05',
            'due_date' => '2026-08-05',
            'status' => 'forecast',
        ])->assertRedirect();

        $this->assertDatabaseHas('financial_entries', [
            'company_id' => $company->getKey(),
            'type' => 'revenue',
            'status' => 'forecast',
            'bank_account_id' => null,
            'amount_cents' => 300000,
        ]);
    }

    public function test_previsto_com_conta_e_rejeitado(): void
    {
        [$owner, , $base] = $this->scenario(3);

        $this->actingAs($owner)
            ->from(route('financial-entries.create'))
            ->post(route('financial-entries.store'), [
                'financial_category_id' => $base['revenue'],
                'description' => 'Errado',
                'amount' => '10,00',
                'competence_date' => '2026-07-05',
                'status' => 'forecast',
                'bank_account_id' => $base['account'],
            ])
            ->assertSessionHasErrors('bank_account_id');

        $this->assertDatabaseCount('financial_entries', 0);
    }

    public function test_dar_baixa_liquida_previsto_e_atualiza_saldo(): void
    {
        [$owner, $company, $base] = $this->scenario(4);
        $entryId = $this->createForecastRevenue($company, $base, $owner);

        $this->actingAs($owner)->post(route('financial-entries.settle', ['entry' => $entryId]), [
            'bank_account_id' => $base['account'],
            'paid_at' => '2026-07-20',
            'payment_method' => 'pix',
        ])->assertRedirect(route('financial-entries.show', ['entry' => $entryId]));

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entryId,
            'status' => 'settled',
            'paid_at' => '2026-07-20 00:00:00',
            'bank_account_id' => $base['account'],
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $base['account'],
            'current_balance_cents' => 300000,
        ]);
    }

    public function test_lancamento_sincronizado_nao_abre_edicao(): void
    {
        [$owner, $company, $base] = $this->scenario(5);
        $entryId = $this->createSyncedEntry($company, $base, $owner);

        $this->actingAs($owner)
            ->get(route('financial-entries.edit', ['entry' => $entryId]))
            ->assertRedirect(route('financial-entries.show', ['entry' => $entryId]))
            ->assertSessionHas('warning');
    }

    public function test_cancela_lancamento_manual(): void
    {
        [$owner, $company, $base] = $this->scenario(6);
        $entryId = $this->createForecastRevenue($company, $base, $owner);

        $this->actingAs($owner)
            ->delete(route('financial-entries.destroy', ['entry' => $entryId]))
            ->assertRedirect(route('financial-entries.index'));

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entryId,
            'status' => 'canceled',
        ]);
    }

    public function test_membro_de_outro_grupo_nao_ve_lancamento(): void
    {
        [$owner, $company, $base] = $this->scenario(7);
        $entryId = $this->createForecastRevenue($company, $base, $owner);

        [$intruder] = $this->scenario(8);

        $this->actingAs($intruder)
            ->get(route('financial-entries.show', ['entry' => $entryId]))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Company, 2: array{revenue: int, expense: int, account: int}}
     */
    private function scenario(int $seed): array
    {
        $owner = User::factory()->create(['email' => 'entry-owner-'.$seed.'@example.com']);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Entry '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '99887766'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Entry Empresa '.$seed.' LTDA',
            'trade_name' => 'Entry Empresa '.$seed,
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

        $base = app(TenantContext::class)->runFor($company, function (): array {
            $revenue = FinancialCategory::query()->create([
                'code' => '1.1', 'name' => 'Receita de fretes', 'type' => 'revenue',
                'dre_group' => 'gross_revenue', 'allocation' => 'vehicle_direct',
                'affects_cashflow' => true, 'is_system' => true, 'active' => true, 'sort_order' => 110,
            ]);
            $expense = FinancialCategory::query()->create([
                'code' => '3.4', 'name' => 'Manutenção', 'type' => 'expense',
                'dre_group' => 'variable_cost', 'allocation' => 'vehicle_direct',
                'affects_cashflow' => true, 'is_system' => true, 'active' => true, 'sort_order' => 340,
            ]);
            $account = BankAccount::query()->create([
                'name' => 'Caixa', 'type' => 'cash', 'initial_balance_cents' => 0,
                'current_balance_cents' => 0, 'is_default' => true, 'active' => true,
            ]);

            return [
                'revenue' => (int) $revenue->getKey(),
                'expense' => (int) $expense->getKey(),
                'account' => (int) $account->getKey(),
            ];
        });

        return [$owner, $company, $base];
    }

    /**
     * @param  array{revenue: int, expense: int, account: int}  $base
     */
    private function createForecastRevenue(Company $company, array $base, User $author): int
    {
        return app(TenantContext::class)->runFor($company, function () use ($company, $base, $author): int {
            $entry = FinancialEntry::query()->create([
                'company_id' => $company->getKey(),
                'financial_category_id' => $base['revenue'],
                'type' => 'revenue',
                'description' => 'Frete previsto',
                'competence_date' => '2026-07-05',
                'due_date' => '2026-08-05',
                'amount_cents' => 300000,
                'status' => 'forecast',
                'created_by' => $author->getKey(),
            ]);

            return (int) $entry->getKey();
        });
    }

    /**
     * @param  array{revenue: int, expense: int, account: int}  $base
     */
    private function createSyncedEntry(Company $company, array $base, User $author): int
    {
        return app(TenantContext::class)->runFor($company, function () use ($company, $base, $author): int {
            $entry = FinancialEntry::query()->create([
                'company_id' => $company->getKey(),
                'financial_category_id' => $base['revenue'],
                'sourceable_type' => CteDocument::class,
                'sourceable_id' => 1,
                'type' => 'revenue',
                'description' => 'Receita de CT-e',
                'competence_date' => '2026-07-05',
                'amount_cents' => 500000,
                'status' => 'forecast',
                'created_by' => $author->getKey(),
            ]);

            return (int) $entry->getKey();
        });
    }
}
