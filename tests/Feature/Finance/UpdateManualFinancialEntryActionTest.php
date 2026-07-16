<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\UpdateManualFinancialEntry;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Finance\Models\Recurrence;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UpdateManualFinancialEntryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualiza_lancamento_manual_da_empresa_ativa(): void
    {
        $company = $this->createCompany(800);
        [$categoryId, $bankAccountId] = $this->createFinanceBase($company, 'expense');
        $entryId = $this->createManualSettledEntry($company, $categoryId, $bankAccountId);

        $tenant = app(TenantContext::class);
        $tenant->runFor($company, function () use ($bankAccountId): void {
            BankAccount::query()->whereKey($bankAccountId)->update([
                'current_balance_cents' => 777,
            ]);
        });

        $action = app(UpdateManualFinancialEntry::class);

        $action->execute($company, $entryId, [
            'financial_category_id' => $categoryId,
            'bank_account_id' => null,
            'type' => 'expense',
            'description' => 'Despesa ajustada para previsto',
            'competence_date' => '2026-07-25',
            'due_date' => '2026-07-30',
            'paid_at' => null,
            'amount_cents' => 19000,
            'status' => 'forecast',
            'payment_method' => null,
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entryId,
            'company_id' => $company->getKey(),
            'description' => 'Despesa ajustada para previsto',
            'competence_date' => '2026-07-25 00:00:00',
            'paid_at' => null,
            'bank_account_id' => null,
            'amount_cents' => 19000,
            'status' => 'forecast',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 0,
        ]);

        $entry = $tenant->runFor($company, fn (): FinancialEntry => FinancialEntry::query()->findOrFail($entryId));

        $this->assertSame(FinancialEntryStatus::Forecast, $entry->status);
    }

    public function test_rejeita_atualizacao_de_lancamento_de_outra_empresa(): void
    {
        $companyA = $this->createCompany(900);
        $companyB = $this->createCompany(901);

        [$categoryIdB, $bankAccountIdB] = $this->createFinanceBase($companyB, 'expense');
        $entryIdFromB = $this->createManualSettledEntry($companyB, $categoryIdB, $bankAccountIdB);

        [$categoryIdA] = $this->createFinanceBase($companyA, 'expense');

        $action = app(UpdateManualFinancialEntry::class);

        $this->expectException(ValidationException::class);

        $action->execute($companyA, $entryIdFromB, [
            'financial_category_id' => $categoryIdA,
            'bank_account_id' => null,
            'type' => 'expense',
            'description' => 'Tentativa invalida',
            'competence_date' => '2026-07-25',
            'due_date' => null,
            'paid_at' => null,
            'amount_cents' => 9900,
            'status' => 'forecast',
            'payment_method' => null,
        ]);
    }

    public function test_rejeita_recorrencia_de_outra_empresa_na_atualizacao(): void
    {
        $companyA = $this->createCompany(903);
        $companyB = $this->createCompany(904);

        [$categoryIdA, $bankAccountIdA] = $this->createFinanceBase($companyA, 'expense');
        [$categoryIdB] = $this->createFinanceBase($companyB, 'expense');
        $entryId = $this->createManualSettledEntry($companyA, $categoryIdA, $bankAccountIdA);
        $recurrenceIdFromB = $this->createRecurrence($companyB, $categoryIdB, 'expense');

        $action = app(UpdateManualFinancialEntry::class);

        $this->expectException(ValidationException::class);

        $action->execute($companyA, $entryId, [
            'financial_category_id' => $categoryIdA,
            'bank_account_id' => null,
            'type' => 'expense',
            'description' => 'Recorrencia invalida no update',
            'competence_date' => '2026-07-25',
            'due_date' => '2026-07-30',
            'paid_at' => null,
            'amount_cents' => 19000,
            'status' => 'forecast',
            'payment_method' => null,
            'recurrence_id' => $recurrenceIdFromB,
        ]);
    }

    public function test_atualiza_transferencia_e_propaga_dados_para_o_par(): void
    {
        $company = $this->createCompany(902);
        [$categoryId, $originBankAccountId, $destinationBankAccountId, $expenseEntryId, $revenueEntryId] = $this->createTransferPairEntries($company);

        $tenant = app(TenantContext::class);
        $tenant->runFor($company, function () use ($originBankAccountId, $destinationBankAccountId): void {
            BankAccount::query()->whereKey($originBankAccountId)->update([
                'current_balance_cents' => 111,
            ]);

            BankAccount::query()->whereKey($destinationBankAccountId)->update([
                'current_balance_cents' => 222,
            ]);
        });

        $action = app(UpdateManualFinancialEntry::class);

        $action->execute($company, $expenseEntryId, [
            'financial_category_id' => $categoryId,
            'bank_account_id' => $originBankAccountId,
            'type' => 'expense',
            'description' => 'Transferencia ajustada',
            'competence_date' => '2026-07-20',
            'due_date' => null,
            'paid_at' => '2026-07-20',
            'amount_cents' => 4500,
            'status' => 'settled',
            'payment_method' => 'bank_transfer',
            'document_number' => 'TR-002',
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $expenseEntryId,
            'company_id' => $company->getKey(),
            'type' => 'expense',
            'description' => 'Transferencia ajustada',
            'amount_cents' => 4500,
            'paid_at' => '2026-07-20 00:00:00',
            'transfer_pair_id' => $revenueEntryId,
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $revenueEntryId,
            'company_id' => $company->getKey(),
            'type' => 'revenue',
            'description' => 'Transferencia ajustada',
            'amount_cents' => 4500,
            'paid_at' => '2026-07-20 00:00:00',
            'transfer_pair_id' => $expenseEntryId,
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $originBankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 5500,
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $destinationBankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 6500,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createFinanceBase(Company $company, string $categoryType): array
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function () use ($categoryType): array {
            $category = FinancialCategory::query()->create([
                'code' => '9.3',
                'name' => 'Categoria update',
                'type' => $categoryType,
                'dre_group' => 'variable_cost',
                'allocation' => 'vehicle_direct',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 930,
            ]);

            $bankAccount = BankAccount::query()->create([
                'name' => 'Conta update',
                'type' => 'cash',
                'initial_balance_cents' => 0,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => true,
                'active' => true,
            ]);

            return [$category->getKey(), $bankAccount->getKey()];
        });
    }

    private function createManualSettledEntry(Company $company, int $categoryId, int $bankAccountId): int
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function () use ($categoryId, $bankAccountId): int {
            $author = User::factory()->create();

            $entry = FinancialEntry::query()->create([
                'financial_category_id' => $categoryId,
                'bank_account_id' => $bankAccountId,
                'type' => 'expense',
                'description' => 'Entrada para update',
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
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private function createTransferPairEntries(Company $company): array
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function (): array {
            $category = FinancialCategory::query()->create([
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
                'name' => 'Conta origem update',
                'type' => 'cash',
                'initial_balance_cents' => 10000,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => true,
                'active' => true,
            ]);

            $destinationAccount = BankAccount::query()->create([
                'name' => 'Conta destino update',
                'type' => 'cash',
                'initial_balance_cents' => 2000,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => false,
                'active' => true,
            ]);

            $author = User::factory()->create();

            $expenseEntry = FinancialEntry::query()->create([
                'financial_category_id' => $category->getKey(),
                'bank_account_id' => $originAccount->getKey(),
                'type' => 'expense',
                'description' => 'Transferencia inicial',
                'competence_date' => '2026-07-15',
                'paid_at' => '2026-07-15',
                'amount_cents' => 3000,
                'status' => 'settled',
                'payment_method' => 'bank_transfer',
                'created_by' => $author->getKey(),
            ]);

            $revenueEntry = FinancialEntry::query()->create([
                'financial_category_id' => $category->getKey(),
                'bank_account_id' => $destinationAccount->getKey(),
                'type' => 'revenue',
                'description' => 'Transferencia inicial',
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
                $category->getKey(),
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
            'email' => 'update-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Update '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '77889900'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Update Empresa '.$seed.' LTDA',
            'trade_name' => 'Update Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }

    private function createRecurrence(Company $company, int $categoryId, string $type): int
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function () use ($categoryId, $type): int {
            $author = User::factory()->create();

            $recurrence = Recurrence::query()->create([
                'financial_category_id' => $categoryId,
                'type' => $type,
                'description' => 'Recorrencia update',
                'amount_cents' => 4200,
                'frequency' => 'monthly',
                'day_of_month' => 10,
                'starts_at' => '2026-07-01',
                'installments_generated' => 0,
                'active' => true,
                'created_by' => $author->getKey(),
            ]);

            return (int) $recurrence->getKey();
        });
    }
}
