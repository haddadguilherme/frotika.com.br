<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domain\Finance\Actions\SeedDefaultFinancialCategories;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ShowDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_painel_traz_saldo_receita_e_comparativo_reais(): void
    {
        [$owner, $company] = $this->scenario(1);

        $tenant = app(TenantContext::class);

        $tenant->runFor($company, function () use ($owner, $company): void {
            app(SeedDefaultFinancialCategories::class)->execute($company);

            $vehicle = Vehicle::query()->create([
                'plate' => 'DAS1D23',
                'type' => 'tractor',
                'status' => 'active',
                'ownership' => 'own',
            ]);

            BankAccount::query()->create([
                'name' => 'Itaú',
                'type' => 'checking',
                'initial_balance_cents' => 0,
                'initial_balance_at' => now()->toDateString(),
                'current_balance_cents' => 250_000,
                'is_default' => false,
                'active' => true,
            ]);

            $revenueCategory = FinancialCategory::query()->where('code', '1.1')->firstOrFail();
            $fuelCategory = FinancialCategory::query()->where('code', '3.1')->firstOrFail();

            $competence = CarbonImmutable::now()->startOfMonth()->addDays(9)->toDateString();

            FinancialEntry::query()->create([
                'financial_category_id' => $revenueCategory->getKey(),
                'vehicle_id' => $vehicle->getKey(),
                'type' => 'revenue',
                'description' => 'Frete',
                'competence_date' => $competence,
                'amount_cents' => 100_000,
                'status' => 'forecast',
                'created_by' => $owner->getKey(),
            ]);

            FinancialEntry::query()->create([
                'financial_category_id' => $fuelCategory->getKey(),
                'vehicle_id' => $vehicle->getKey(),
                'type' => 'expense',
                'description' => 'Diesel',
                'competence_date' => $competence,
                'amount_cents' => 40_000,
                'status' => 'forecast',
                'created_by' => $owner->getKey(),
            ]);
        });

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Painel operacional')
            ->assertSee('Saldo consolidado')
            ->assertSee('2.500,00')   // saldo consolidado (R$ 2.500,00)
            ->assertSee('1.000,00')   // receita bruta do mês
            ->assertSee('DAS1D23');   // veículo no comparativo
    }

    public function test_painel_sem_movimento_mostra_estado_vazio(): void
    {
        [$owner] = $this->scenario(2);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nenhum veículo com movimento neste mês.');
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function scenario(int $seed): array
    {
        $owner = User::factory()->create(['email' => 'dash-'.$seed.'@example.com']);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Painel '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '33445566'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Painel '.$seed.' LTDA',
            'trade_name' => 'Painel '.$seed,
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
}
