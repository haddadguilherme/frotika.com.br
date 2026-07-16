<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\CancelFinancialEntry;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CancelFinancialEntryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancela_lancamento_manual_da_empresa_ativa(): void
    {
        $company = $this->createCompany(500);
        $entryId = $this->createManualSettledEntry($company);

        $tenant = app(TenantContext::class);
        $bankAccountId = $tenant->runFor($company, fn (): int => (int) FinancialEntry::query()->findOrFail($entryId)->bank_account_id);

        $tenant->runFor($company, function () use ($bankAccountId): void {
            BankAccount::query()->whereKey($bankAccountId)->update([
                'current_balance_cents' => 999,
            ]);
        });

        $action = app(CancelFinancialEntry::class);
        $action->execute($company, $entryId);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entryId,
            'company_id' => $company->getKey(),
            'status' => 'canceled',
            'paid_at' => null,
            'bank_account_id' => null,
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 0,
        ]);

        $entry = $tenant->runFor($company, fn (): FinancialEntry => FinancialEntry::query()->findOrFail($entryId));

        $this->assertSame(FinancialEntryStatus::Canceled, $entry->status);
    }

    public function test_rejeita_cancelamento_de_lancamento_de_outra_empresa(): void
    {
        $companyA = $this->createCompany(600);
        $companyB = $this->createCompany(700);

        $entryIdFromB = $this->createManualSettledEntry($companyB);

        $action = app(CancelFinancialEntry::class);

        $this->expectException(ValidationException::class);

        $action->execute($companyA, $entryIdFromB);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entryIdFromB,
            'company_id' => $companyB->getKey(),
            'status' => 'settled',
        ]);
    }

    public function test_cancela_transferencia_e_tambem_cancela_o_par(): void
    {
        $company = $this->createCompany(750);
        [$originBankAccountId, $destinationBankAccountId, $expenseEntryId, $revenueEntryId] = $this->createTransferPairEntry($company);

        $tenant = app(TenantContext::class);
        $tenant->runFor($company, function () use ($originBankAccountId, $destinationBankAccountId): void {
            BankAccount::query()->whereKey($originBankAccountId)->update([
                'current_balance_cents' => 1,
            ]);

            BankAccount::query()->whereKey($destinationBankAccountId)->update([
                'current_balance_cents' => 1,
            ]);
        });

        $action = app(CancelFinancialEntry::class);
        $action->execute($company, $expenseEntryId);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $expenseEntryId,
            'company_id' => $company->getKey(),
            'status' => 'canceled',
            'paid_at' => null,
            'bank_account_id' => null,
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $revenueEntryId,
            'company_id' => $company->getKey(),
            'status' => 'canceled',
            'paid_at' => null,
            'bank_account_id' => null,
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $originBankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 10000,
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $destinationBankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 2000,
        ]);
    }

    private function createManualSettledEntry(Company $company): int
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function (): int {
            $category = FinancialCategory::query()->create([
                'code' => '9.2',
                'name' => 'Categoria cancelamento',
                'type' => 'expense',
                'dre_group' => 'variable_cost',
                'allocation' => 'vehicle_direct',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 920,
            ]);

            $bankAccount = BankAccount::query()->create([
                'name' => 'Conta cancelamento',
                'type' => 'cash',
                'initial_balance_cents' => 0,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => true,
                'active' => true,
            ]);

            $author = User::factory()->create();

            $entry = FinancialEntry::query()->create([
                'financial_category_id' => $category->getKey(),
                'bank_account_id' => $bankAccount->getKey(),
                'type' => 'expense',
                'description' => 'Entrada para cancelamento',
                'competence_date' => '2026-07-15',
                'paid_at' => '2026-07-15',
                'amount_cents' => 15000,
                'status' => 'settled',
                'payment_method' => 'pix',
                'created_by' => $author->getKey(),
            ]);

            return $entry->getKey();
        });
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function createTransferPairEntry(Company $company): array
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function (): array {
            $transferCategory = FinancialCategory::query()->create([
                'code' => '8.4',
                'name' => 'Transferencia entre contas',
                'type' => 'expense',
                'dre_group' => 'non_operating',
                'allocation' => 'non_vehicle',
                'affects_cashflow' => true,
                'is_system' => true,
                'active' => true,
                'sort_order' => 840,
            ]);

            $originAccount = BankAccount::query()->create([
                'name' => 'Conta origem cancelamento',
                'type' => 'cash',
                'initial_balance_cents' => 10000,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => true,
                'active' => true,
            ]);

            $destinationAccount = BankAccount::query()->create([
                'name' => 'Conta destino cancelamento',
                'type' => 'cash',
                'initial_balance_cents' => 2000,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => false,
                'active' => true,
            ]);

            $author = User::factory()->create();

            $expenseEntry = FinancialEntry::query()->create([
                'financial_category_id' => $transferCategory->getKey(),
                'bank_account_id' => $originAccount->getKey(),
                'type' => 'expense',
                'description' => 'Transferencia para cancelar',
                'competence_date' => '2026-07-15',
                'paid_at' => '2026-07-15',
                'amount_cents' => 3000,
                'status' => 'settled',
                'payment_method' => 'bank_transfer',
                'created_by' => $author->getKey(),
            ]);

            $revenueEntry = FinancialEntry::query()->create([
                'financial_category_id' => $transferCategory->getKey(),
                'bank_account_id' => $destinationAccount->getKey(),
                'type' => 'revenue',
                'description' => 'Transferencia para cancelar',
                'competence_date' => '2026-07-15',
                'paid_at' => '2026-07-15',
                'amount_cents' => 3000,
                'status' => 'settled',
                'payment_method' => 'bank_transfer',
                'transfer_pair_id' => $expenseEntry->getKey(),
                'created_by' => $author->getKey(),
            ]);

            $expenseEntry->forceFill([
                'transfer_pair_id' => $revenueEntry->getKey(),
            ])->save();

            return [
                $originAccount->getKey(),
                $destinationAccount->getKey(),
                $expenseEntry->getKey(),
                $revenueEntry->getKey(),
            ];
        });
    }

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'cancel-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Cancel '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '66778899'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Cancel Empresa '.$seed.' LTDA',
            'trade_name' => 'Cancel Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }
}
