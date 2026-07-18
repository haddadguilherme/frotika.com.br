<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\BuildCashFlowMatrix;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class BuildCashFlowMatrixActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_monta_matriz_sem_previstos_usando_apenas_liquidados(): void
    {
        $company = $this->createCompany(1300);
        [$bankAccountId, $expenseCategoryId, $revenueCategoryId] = $this->createFinanceBase($company);

        $this->createEntry($company, $bankAccountId, $revenueCategoryId, 'revenue', 'settled', 5000, '2026-07-01', null);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'settled', 1000, '2026-07-05', null);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'settled', 700, '2026-07-10', null);
        $this->createEntry($company, $bankAccountId, $revenueCategoryId, 'revenue', 'settled', 2000, '2026-07-11', null);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'forecast', 300, null, '2026-07-12');
        $this->createEntry($company, $bankAccountId, $revenueCategoryId, 'revenue', 'forecast', 400, null, null);

        $action = app(BuildCashFlowMatrix::class);

        $matrix = $action->execute($company, '2026-07-10', '2026-07-13', false);

        $this->assertFalse($matrix['include_forecast']);
        $this->assertSame([
            'bank_account_ids' => [],
            'financial_category_ids' => [],
            'vehicle_ids' => [],
            'statuses' => ['settled'],
        ], $matrix['applied_filters']);

        $this->assertSame([
            'opening_balance_cents' => 5000,
            'revenue_cents' => 2000,
            'expense_cents' => 700,
            'net_cents' => 1300,
            'closing_balance_cents' => 6300,
        ], $matrix['totals']);
        $this->assertCount(1, $matrix['accounts']);

        $account = $matrix['accounts'][0];

        $this->assertSame(5000, $account['opening_balance_cents']);
        $this->assertSame(2000, $account['revenue_cents']);
        $this->assertSame(700, $account['expense_cents']);
        $this->assertSame(1300, $account['net_cents']);
        $this->assertSame(6300, $account['closing_balance_cents']);
        $this->assertSame(-700, $account['days'][0]['net_cents']);
        $this->assertSame(2000, $account['days'][1]['net_cents']);
        $this->assertSame(0, $account['days'][2]['net_cents']);
        $this->assertSame(0, $account['days'][3]['net_cents']);
    }

    public function test_considera_previstos_com_fallback_de_due_date_para_competence_date(): void
    {
        $company = $this->createCompany(1400);
        [$bankAccountId, $expenseCategoryId, $revenueCategoryId] = $this->createFinanceBase($company);

        $this->createEntry($company, $bankAccountId, $revenueCategoryId, 'revenue', 'settled', 5000, '2026-07-01', null);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'settled', 1000, '2026-07-05', null);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'settled', 700, '2026-07-10', null);
        $this->createEntry($company, $bankAccountId, $revenueCategoryId, 'revenue', 'settled', 2000, '2026-07-11', null);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'forecast', 300, null, '2026-07-12');
        $this->createEntry($company, $bankAccountId, $revenueCategoryId, 'revenue', 'forecast', 400, null, null);

        $otherCompany = $this->createCompany(1401);
        [$otherBankAccountId, $otherExpenseCategoryId] = $this->createSecondaryFinanceBase($otherCompany);
        $this->createEntry($otherCompany, $otherBankAccountId, $otherExpenseCategoryId, 'expense', 'settled', 9999, '2026-07-10', null);

        $action = app(BuildCashFlowMatrix::class);

        $matrix = $action->execute($company, '2026-07-10', '2026-07-13', true);

        $this->assertTrue($matrix['include_forecast']);
        $this->assertCount(1, $matrix['accounts']);

        $account = $matrix['accounts'][0];

        $this->assertSame(5000, $account['opening_balance_cents']);
        $this->assertSame(6400, $account['closing_balance_cents']);
        $this->assertSame(-300, $account['days'][2]['net_cents']);
        $this->assertSame(400, $account['days'][3]['net_cents']);
    }

    public function test_aplica_filtros_de_categoria_veiculo_e_status(): void
    {
        $company = $this->createCompany(1500);
        [$bankAccountId, $expenseCategoryId, $revenueCategoryId] = $this->createFinanceBase($company);
        [$vehicleAId, $vehicleBId] = $this->createVehicles($company);

        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'settled', 100, '2026-07-09', null, $vehicleAId);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'settled', 700, '2026-07-10', null, $vehicleAId);
        $this->createEntry($company, $bankAccountId, $revenueCategoryId, 'revenue', 'settled', 2000, '2026-07-11', null, $vehicleAId);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'settled', 500, '2026-07-11', null, $vehicleBId);
        $this->createEntry($company, $bankAccountId, $expenseCategoryId, 'expense', 'forecast', 300, null, '2026-07-12', $vehicleAId);

        $action = app(BuildCashFlowMatrix::class);

        $matrix = $action->execute(
            $company,
            '2026-07-10',
            '2026-07-12',
            true,
            [$bankAccountId],
            [$expenseCategoryId],
            [$vehicleAId],
            ['settled'],
        );

        $this->assertFalse($matrix['include_forecast']);
        $this->assertSame([
            'bank_account_ids' => [$bankAccountId],
            'financial_category_ids' => [$expenseCategoryId],
            'vehicle_ids' => [$vehicleAId],
            'statuses' => ['settled'],
        ], $matrix['applied_filters']);
        $this->assertSame([
            'opening_balance_cents' => 900,
            'revenue_cents' => 0,
            'expense_cents' => 700,
            'net_cents' => -700,
            'closing_balance_cents' => 200,
        ], $matrix['totals']);
        $this->assertCount(1, $matrix['accounts']);

        $account = $matrix['accounts'][0];

        $this->assertSame(900, $account['opening_balance_cents']);
        $this->assertSame(200, $account['closing_balance_cents']);
        $this->assertSame(-700, $account['days'][0]['net_cents']);
        $this->assertSame(0, $account['days'][1]['net_cents']);
        $this->assertSame(0, $account['days'][2]['net_cents']);
    }

    public function test_rejeita_status_invalido_no_filtro(): void
    {
        $company = $this->createCompany(1600);
        [$bankAccountId] = $this->createFinanceBase($company);

        $action = app(BuildCashFlowMatrix::class);

        $this->expectException(ValidationException::class);

        $action->execute(
            $company,
            '2026-07-10',
            '2026-07-12',
            true,
            [$bankAccountId],
            null,
            null,
            ['canceled'],
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function createFinanceBase(Company $company): array
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function (): array {
            $bankAccount = BankAccount::query()->create([
                'name' => 'Conta fluxo',
                'type' => 'cash',
                'initial_balance_cents' => 1000,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => true,
                'active' => true,
            ]);

            $expenseCategory = FinancialCategory::query()->create([
                'code' => '9.8',
                'name' => 'Despesa fluxo',
                'type' => 'expense',
                'dre_group' => 'variable_cost',
                'allocation' => 'vehicle_direct',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 980,
            ]);

            $revenueCategory = FinancialCategory::query()->create([
                'code' => '9.9',
                'name' => 'Receita fluxo',
                'type' => 'revenue',
                'dre_group' => 'gross_revenue',
                'allocation' => 'vehicle_direct',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 990,
            ]);

            return [$bankAccount->getKey(), $expenseCategory->getKey(), $revenueCategory->getKey()];
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createVehicles(Company $company): array
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function (): array {
            $a = Vehicle::query()->create([
                'plate' => 'CFA1A11', 'type' => 'tractor', 'status' => 'active', 'ownership' => 'own',
            ]);
            $b = Vehicle::query()->create([
                'plate' => 'CFB2B22', 'type' => 'tractor', 'status' => 'active', 'ownership' => 'own',
            ]);

            return [(int) $a->getKey(), (int) $b->getKey()];
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createSecondaryFinanceBase(Company $company): array
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function (): array {
            $bankAccount = BankAccount::query()->create([
                'name' => 'Conta externa',
                'type' => 'cash',
                'initial_balance_cents' => 0,
                'initial_balance_at' => '2026-07-01',
                'current_balance_cents' => 0,
                'is_default' => true,
                'active' => true,
            ]);

            $expenseCategory = FinancialCategory::query()->create([
                'code' => '10.1',
                'name' => 'Despesa externa',
                'type' => 'expense',
                'dre_group' => 'variable_cost',
                'allocation' => 'vehicle_direct',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 1001,
            ]);

            return [$bankAccount->getKey(), $expenseCategory->getKey()];
        });
    }

    private function createEntry(
        Company $company,
        int $bankAccountId,
        int $categoryId,
        string $type,
        string $status,
        int $amountCents,
        ?string $paidAt,
        ?string $dueDate,
        ?int $vehicleId = null,
    ): void {
        $tenant = app(TenantContext::class);

        $tenant->runFor($company, function () use ($bankAccountId, $categoryId, $type, $status, $amountCents, $paidAt, $dueDate, $vehicleId): void {
            $author = User::factory()->create();

            FinancialEntry::query()->create([
                'financial_category_id' => $categoryId,
                'bank_account_id' => $bankAccountId,
                'vehicle_id' => $vehicleId,
                'type' => $type,
                'description' => 'Entrada para fluxo',
                'competence_date' => '2026-07-13',
                'due_date' => $dueDate,
                'paid_at' => $paidAt,
                'amount_cents' => $amountCents,
                'status' => $status,
                'payment_method' => $paidAt !== null ? 'pix' : null,
                'created_by' => $author->getKey(),
            ]);
        });
    }

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'cashflow-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo CashFlow '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '10111213'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'CashFlow Empresa '.$seed.' LTDA',
            'trade_name' => 'CashFlow Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }
}
