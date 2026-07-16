<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\CreateRecurrence;
use App\Domain\Finance\Enums\RecurrenceFrequency;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\Recurrence;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CreateRecurrenceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_recorrencia_mensal_da_empresa_ativa(): void
    {
        $company = $this->createCompany(1800);
        $categoryId = $this->createCategory($company, 'expense');
        $author = User::factory()->create();

        $action = app(CreateRecurrence::class);

        $recurrenceId = $action->execute($company, (int) $author->getKey(), [
            'financial_category_id' => $categoryId,
            'type' => 'expense',
            'description' => 'Seguro mensal',
            'amount_cents' => 35000,
            'frequency' => 'monthly',
            'day_of_month' => 15,
            'starts_at' => '2026-07-01',
            'payment_method' => 'bank_slip',
            'installments' => 12,
        ]);

        $this->assertDatabaseHas('recurrences', [
            'id' => $recurrenceId,
            'company_id' => $company->getKey(),
            'financial_category_id' => $categoryId,
            'type' => 'expense',
            'description' => 'Seguro mensal',
            'amount_cents' => 35000,
            'frequency' => 'monthly',
            'day_of_month' => 15,
            'installments' => 12,
            'installments_generated' => 0,
            'active' => true,
            'created_by' => $author->getKey(),
        ]);

        $tenant = app(TenantContext::class);
        $recurrence = $tenant->runFor($company, fn (): Recurrence => Recurrence::query()->findOrFail($recurrenceId));

        $this->assertSame(RecurrenceFrequency::Monthly, $recurrence->frequency);
    }

    public function test_rejeita_recorrencia_mensal_sem_dia_do_mes(): void
    {
        $company = $this->createCompany(1801);
        $categoryId = $this->createCategory($company, 'expense');
        $author = User::factory()->create();

        $action = app(CreateRecurrence::class);

        $this->expectException(ValidationException::class);

        $action->execute($company, (int) $author->getKey(), [
            'financial_category_id' => $categoryId,
            'type' => 'expense',
            'description' => 'Despesa recorrente',
            'amount_cents' => 1500,
            'frequency' => 'monthly',
            'starts_at' => '2026-07-01',
        ]);

        $this->assertDatabaseCount('recurrences', 0);
    }

    public function test_rejeita_categoria_de_outra_empresa(): void
    {
        $companyA = $this->createCompany(1802);
        $companyB = $this->createCompany(1803);
        $categoryIdFromB = $this->createCategory($companyB, 'expense');
        $author = User::factory()->create();

        $action = app(CreateRecurrence::class);

        $this->expectException(ValidationException::class);

        $action->execute($companyA, (int) $author->getKey(), [
            'financial_category_id' => $categoryIdFromB,
            'type' => 'expense',
            'description' => 'Categoria invalida',
            'amount_cents' => 1900,
            'frequency' => 'weekly',
            'starts_at' => '2026-07-01',
        ]);
    }

    private function createCategory(Company $company, string $type): int
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function () use ($type): int {
            $category = FinancialCategory::query()->create([
                'code' => '11.1',
                'name' => 'Categoria recorrencia',
                'type' => $type,
                'dre_group' => 'fixed_cost',
                'allocation' => 'non_vehicle',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 1110,
            ]);

            return (int) $category->getKey();
        });
    }

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'recurrence-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Recurrence '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '30112233'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Recurrence Empresa '.$seed.' LTDA',
            'trade_name' => 'Recurrence Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }
}
