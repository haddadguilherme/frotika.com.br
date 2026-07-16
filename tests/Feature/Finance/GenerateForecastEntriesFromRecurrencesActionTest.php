<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\GenerateForecastEntriesFromRecurrences;
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

final class GenerateForecastEntriesFromRecurrencesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_lancamentos_previstos_e_eh_idempotente(): void
    {
        $company = $this->createCompany(1810);
        $recurrenceId = $this->createMonthlyRecurrence($company, '2026-05-01', null, null);

        $action = app(GenerateForecastEntriesFromRecurrences::class);

        $firstRun = $action->execute($company, '2026-07-15');
        $secondRun = $action->execute($company, '2026-07-15');

        $this->assertSame(1, $firstRun['recurrences_processed']);
        $this->assertSame(3, $firstRun['entries_created']);
        $this->assertSame(0, $secondRun['entries_created']);

        $tenant = app(TenantContext::class);

        $entriesCount = $tenant->runFor($company, fn (): int => (int) FinancialEntry::query()->where('recurrence_id', $recurrenceId)->count());

        $this->assertSame(3, $entriesCount);

        $this->assertDatabaseHas('recurrences', [
            'id' => $recurrenceId,
            'installments_generated' => 3,
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'recurrence_id' => $recurrenceId,
            'status' => 'forecast',
            'competence_date' => '2026-07-10 00:00:00',
        ]);
    }

    public function test_respeita_limite_de_parcelas_e_modo_dry_run(): void
    {
        $company = $this->createCompany(1811);
        $recurrenceId = $this->createWeeklyRecurrence($company, '2026-07-01', 2);

        $action = app(GenerateForecastEntriesFromRecurrences::class);
        $tenant = app(TenantContext::class);

        $dryRun = $action->execute($company, '2026-07-20', true);
        $this->assertSame(2, $dryRun['entries_created']);

        $dryRunCount = $tenant->runFor($company, fn (): int => (int) FinancialEntry::query()->where('recurrence_id', $recurrenceId)->count());
        $this->assertSame(0, $dryRunCount);

        $applyRun = $action->execute($company, '2026-07-20');

        $this->assertSame(2, $applyRun['entries_created']);

        $applyRunCount = $tenant->runFor($company, fn (): int => (int) FinancialEntry::query()->where('recurrence_id', $recurrenceId)->count());
        $this->assertSame(2, $applyRunCount);

        $this->assertDatabaseHas('recurrences', [
            'id' => $recurrenceId,
            'installments_generated' => 2,
        ]);
    }

    private function createMonthlyRecurrence(Company $company, string $startsAt, ?string $endsAt, ?int $installments): int
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function () use ($startsAt, $endsAt, $installments): int {
            $category = FinancialCategory::query()->create([
                'code' => '11.2',
                'name' => 'Categoria geracao mensal',
                'type' => 'expense',
                'dre_group' => 'fixed_cost',
                'allocation' => 'non_vehicle',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 1120,
            ]);

            $author = User::factory()->create();

            $recurrence = Recurrence::query()->create([
                'financial_category_id' => $category->getKey(),
                'type' => 'expense',
                'description' => 'Seguro recorrente',
                'amount_cents' => 10000,
                'frequency' => 'monthly',
                'day_of_month' => 10,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'installments' => $installments,
                'installments_generated' => 0,
                'active' => true,
                'created_by' => $author->getKey(),
            ]);

            return (int) $recurrence->getKey();
        });
    }

    private function createWeeklyRecurrence(Company $company, string $startsAt, int $installments): int
    {
        $tenant = app(TenantContext::class);

        return $tenant->runFor($company, function () use ($startsAt, $installments): int {
            $category = FinancialCategory::query()->create([
                'code' => '11.3',
                'name' => 'Categoria geracao semanal',
                'type' => 'revenue',
                'dre_group' => 'non_operating',
                'allocation' => 'non_vehicle',
                'affects_cashflow' => true,
                'is_system' => false,
                'active' => true,
                'sort_order' => 1130,
            ]);

            $author = User::factory()->create();

            $recurrence = Recurrence::query()->create([
                'financial_category_id' => $category->getKey(),
                'type' => 'revenue',
                'description' => 'Receita semanal',
                'amount_cents' => 5000,
                'frequency' => 'weekly',
                'day_of_month' => null,
                'starts_at' => $startsAt,
                'ends_at' => null,
                'installments' => $installments,
                'installments_generated' => 0,
                'active' => true,
                'created_by' => $author->getKey(),
            ]);

            return (int) $recurrence->getKey();
        });
    }

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'generate-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Generate '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '44112233'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Generate Empresa '.$seed.' LTDA',
            'trade_name' => 'Generate Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }
}
