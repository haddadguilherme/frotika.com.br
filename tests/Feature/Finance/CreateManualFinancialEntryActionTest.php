<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\CreateManualFinancialEntry;
use App\Domain\Finance\Enums\FinancialEntryPaymentMethod;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
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

final class CreateManualFinancialEntryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_lancamento_liquidado_com_duas_datas_e_conta_da_empresa(): void
    {
        $company = $this->createCompany(100);
        $createdBy = User::factory()->create();

        [$categoryId, $bankAccountId] = $this->createFinanceBase($company, 'expense');

        $action = app(CreateManualFinancialEntry::class);

        $entryId = $action->execute($company, $createdBy->getKey(), [
            'financial_category_id' => $categoryId,
            'bank_account_id' => $bankAccountId,
            'type' => 'expense',
            'description' => 'Troca de pneu',
            'document_number' => 'NF-001',
            'competence_date' => '2026-07-10',
            'due_date' => '2026-07-15',
            'paid_at' => '2026-07-12',
            'amount_cents' => 125000,
            'status' => 'settled',
            'payment_method' => 'pix',
        ]);

        $this->assertGreaterThan(0, $entryId);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entryId,
            'company_id' => $company->getKey(),
            'bank_account_id' => $bankAccountId,
            'financial_category_id' => $categoryId,
            'type' => 'expense',
            'description' => 'Troca de pneu',
            'competence_date' => '2026-07-10 00:00:00',
            'paid_at' => '2026-07-12 00:00:00',
            'amount_cents' => 125000,
            'status' => 'settled',
            'created_by' => $createdBy->getKey(),
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccountId,
            'company_id' => $company->getKey(),
            'current_balance_cents' => -125000,
        ]);

        $tenant = app(TenantContext::class);

        $entry = $tenant->runFor($company, fn (): FinancialEntry => FinancialEntry::query()->findOrFail($entryId));

        $this->assertSame(FinancialEntryType::Expense, $entry->type);
        $this->assertSame(FinancialEntryStatus::Settled, $entry->status);
        $this->assertSame(FinancialEntryPaymentMethod::Pix, $entry->payment_method);
    }

    public function test_rejeita_lancamento_previsto_com_conta_ou_data_de_pagamento(): void
    {
        $company = $this->createCompany(200);
        $createdBy = User::factory()->create();

        [$categoryId, $bankAccountId] = $this->createFinanceBase($company, 'expense');

        $action = app(CreateManualFinancialEntry::class);

        $this->expectException(ValidationException::class);

        $action->execute($company, $createdBy->getKey(), [
            'financial_category_id' => $categoryId,
            'bank_account_id' => $bankAccountId,
            'type' => 'expense',
            'description' => 'Despesa prevista',
            'competence_date' => '2026-07-20',
            'paid_at' => '2026-07-20',
            'amount_cents' => 5000,
            'status' => 'forecast',
        ]);

        $this->assertDatabaseCount('financial_entries', 0);
    }

    public function test_rejeita_categoria_de_outra_empresa(): void
    {
        $companyA = $this->createCompany(300);
        $companyB = $this->createCompany(400);
        $createdBy = User::factory()->create();

        [$categoryIdFromB] = $this->createFinanceBase($companyB, 'revenue');

        $action = app(CreateManualFinancialEntry::class);

        $this->expectException(ValidationException::class);

        $action->execute($companyA, $createdBy->getKey(), [
            'financial_category_id' => $categoryIdFromB,
            'type' => 'revenue',
            'description' => 'Receita teste',
            'competence_date' => '2026-07-20',
            'amount_cents' => 9000,
            'status' => 'forecast',
        ]);

        $this->assertDatabaseCount('financial_entries', 0);
    }

    public function test_rejeita_recorrencia_de_outra_empresa(): void
    {
        $companyA = $this->createCompany(301);
        $companyB = $this->createCompany(302);
        $createdBy = User::factory()->create();

        [$categoryIdA] = $this->createFinanceBase($companyA, 'expense');
        [$categoryIdB] = $this->createFinanceBase($companyB, 'expense');
        $recurrenceIdFromB = $this->createRecurrence($companyB, $categoryIdB, 'expense');

        $action = app(CreateManualFinancialEntry::class);

        $this->expectException(ValidationException::class);

        $action->execute($companyA, $createdBy->getKey(), [
            'financial_category_id' => $categoryIdA,
            'type' => 'expense',
            'description' => 'Recorrencia de outra empresa',
            'competence_date' => '2026-07-20',
            'amount_cents' => 9000,
            'status' => 'forecast',
            'recurrence_id' => $recurrenceIdFromB,
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
                'code' => '9.1',
                'name' => 'Categoria teste',
                'type' => $categoryType,
                'dre_group' => $categoryType === 'revenue' ? 'gross_revenue' : 'variable_cost',
                'allocation' => 'non_vehicle',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 910,
            ]);

            $bankAccount = BankAccount::query()->create([
                'name' => 'Caixa teste',
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

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'finance-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Finance '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '55443322'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Finance Empresa '.$seed.' LTDA',
            'trade_name' => 'Finance Empresa '.$seed,
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
                'description' => 'Recorrencia teste',
                'amount_cents' => 5000,
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
