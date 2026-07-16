<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\CreateTransferBetweenBankAccounts;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CreateTransferBetweenBankAccountsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_transferencia_com_par_de_lancamentos_vinculados_e_recalcula_saldos(): void
    {
        $company = $this->createCompany(1500);
        [$originBankAccountId, $destinationBankAccountId] = $this->createTransferFinanceBase($company);

        $createdBy = User::factory()->create();
        $action = app(CreateTransferBetweenBankAccounts::class);

        $result = $action->execute($company, $createdBy->getKey(), [
            'origin_bank_account_id' => $originBankAccountId,
            'destination_bank_account_id' => $destinationBankAccountId,
            'transfer_date' => '2026-07-15',
            'amount_cents' => 3500,
            'description' => 'Transferencia operacional',
            'document_number' => 'TR-001',
            'payment_method' => 'bank_transfer',
        ]);

        $expenseEntryId = $result['expense_entry_id'];
        $revenueEntryId = $result['revenue_entry_id'];

        $this->assertDatabaseHas('financial_entries', [
            'id' => $expenseEntryId,
            'company_id' => $company->getKey(),
            'bank_account_id' => $originBankAccountId,
            'type' => 'expense',
            'status' => 'settled',
            'amount_cents' => 3500,
            'transfer_pair_id' => $revenueEntryId,
            'document_number' => 'TR-001',
            'paid_at' => '2026-07-15 00:00:00',
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $revenueEntryId,
            'company_id' => $company->getKey(),
            'bank_account_id' => $destinationBankAccountId,
            'type' => 'revenue',
            'status' => 'settled',
            'amount_cents' => 3500,
            'transfer_pair_id' => $expenseEntryId,
            'document_number' => 'TR-001',
            'paid_at' => '2026-07-15 00:00:00',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $originBankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 6500,
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $destinationBankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => 5500,
        ]);
    }

    public function test_rejeita_transferencia_quando_conta_de_origem_e_destino_sao_iguais(): void
    {
        $company = $this->createCompany(1600);
        [$originBankAccountId] = $this->createTransferFinanceBase($company);

        $createdBy = User::factory()->create();
        $action = app(CreateTransferBetweenBankAccounts::class);

        $this->expectException(ValidationException::class);

        $action->execute($company, $createdBy->getKey(), [
            'origin_bank_account_id' => $originBankAccountId,
            'destination_bank_account_id' => $originBankAccountId,
            'transfer_date' => '2026-07-15',
            'amount_cents' => 1000,
        ]);

        $this->assertDatabaseCount('financial_entries', 0);
    }

    public function test_rejeita_transferencia_para_conta_de_outra_empresa(): void
    {
        $companyA = $this->createCompany(1700);
        $companyB = $this->createCompany(1701);

        [$originBankAccountId] = $this->createTransferFinanceBase($companyA);
        [, $destinationBankAccountIdFromOtherCompany] = $this->createTransferFinanceBase($companyB);

        $createdBy = User::factory()->create();
        $action = app(CreateTransferBetweenBankAccounts::class);

        $this->expectException(ValidationException::class);

        $action->execute($companyA, $createdBy->getKey(), [
            'origin_bank_account_id' => $originBankAccountId,
            'destination_bank_account_id' => $destinationBankAccountIdFromOtherCompany,
            'transfer_date' => '2026-07-15',
            'amount_cents' => 1000,
        ]);

        $this->assertDatabaseCount('financial_entries', 0);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createTransferFinanceBase(Company $company): array
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function (): array {
            FinancialCategory::query()->create([
                'code' => '8',
                'name' => 'Movimentacoes nao operacionais',
                'type' => null,
                'dre_group' => null,
                'allocation' => null,
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 800,
            ]);

            FinancialCategory::query()->create([
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
                'name' => 'Conta origem',
                'type' => 'cash',
                'initial_balance_cents' => 10000,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => true,
                'active' => true,
            ]);

            $destinationAccount = BankAccount::query()->create([
                'name' => 'Conta destino',
                'type' => 'cash',
                'initial_balance_cents' => 2000,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => false,
                'active' => true,
            ]);

            return [$originAccount->getKey(), $destinationAccount->getKey()];
        });
    }

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'transfer-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Transfer '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '19181716'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Transfer Empresa '.$seed.' LTDA',
            'trade_name' => 'Transfer Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }
}
