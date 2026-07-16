<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Finance\Models\Recurrence;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GenerateRecurrencesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_gera_lancamentos_previstos_de_recorrencias(): void
    {
        $company = $this->createCompany(1820);
        $this->createMonthlyRecurrence($company, '2026-05-01');

        $this->artisan('frotika:generate-recurrences --reference-date=2026-07-15')
            ->expectsOutputToContain('Geracao de recorrencias concluida (apply)')
            ->assertSuccessful();

        $tenant = app(TenantContext::class);

        $entriesCount = $tenant->runFor($company, fn (): int => FinancialEntry::query()->where('status', 'forecast')->count());

        $this->assertSame(3, $entriesCount);
    }

    private function createMonthlyRecurrence(Company $company, string $startsAt): void
    {
        $tenant = app(TenantContext::class);

        $tenant->runFor($company, function () use ($startsAt): void {
            $category = FinancialCategory::query()->create([
                'code' => '11.4',
                'name' => 'Categoria comando recorrencia',
                'type' => 'expense',
                'dre_group' => 'fixed_cost',
                'allocation' => 'non_vehicle',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 1140,
            ]);

            $author = User::factory()->create();

            Recurrence::query()->create([
                'financial_category_id' => $category->getKey(),
                'type' => 'expense',
                'description' => 'Despesa por comando',
                'amount_cents' => 20000,
                'frequency' => 'monthly',
                'day_of_month' => 10,
                'starts_at' => $startsAt,
                'installments_generated' => 0,
                'active' => true,
                'created_by' => $author->getKey(),
            ]);
        });
    }

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'command-recurrence-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Command Recurrence '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '55112233'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Command Recurrence Empresa '.$seed.' LTDA',
            'trade_name' => 'Command Recurrence Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }
}
