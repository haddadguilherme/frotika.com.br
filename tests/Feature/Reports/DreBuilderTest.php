<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Finance\Actions\SeedDefaultFinancialCategories;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fleet\Models\VehicleCostParameter;
use App\Domain\Fuelings\Models\Fueling;
use App\Domain\Reports\Dre\DreBuilder;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DreBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_rateia_despesa_por_maior_resto_quando_metodo_igual(): void
    {
        $company = $this->createCompany(901);
        $author = User::factory()->create();

        $this->setApportionmentMethod($company, 'equal');

        $tenant = app(TenantContext::class);

        [$vehicleAId, $vehicleBId, $vehicleCId, $revenueCategoryId, $adminCategoryId] = $tenant->runFor(
            $company,
            function () use ($company): array {
                app(SeedDefaultFinancialCategories::class)->execute($company);

                $vehicleA = Vehicle::query()->create([
                    'plate' => 'QAA1A11',
                    'type' => 'tractor',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                $vehicleB = Vehicle::query()->create([
                    'plate' => 'QBB2B22',
                    'type' => 'truck',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                $vehicleC = Vehicle::query()->create([
                    'plate' => 'QCC3C33',
                    'type' => 'toco',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                $revenueCategory = FinancialCategory::query()->where('code', '1.1')->firstOrFail();
                $adminCategory = FinancialCategory::query()->where('code', '5.1')->firstOrFail();

                return [
                    (int) $vehicleA->getKey(),
                    (int) $vehicleB->getKey(),
                    (int) $vehicleC->getKey(),
                    (int) $revenueCategory->getKey(),
                    (int) $adminCategory->getKey(),
                ];
            }
        );

        $this->createEntry($company, $author, $vehicleAId, $revenueCategoryId, 'revenue', 100_000, '2026-07-10');
        $this->createEntry($company, $author, $vehicleBId, $revenueCategoryId, 'revenue', 100_000, '2026-07-10');
        $this->createEntry($company, $author, $vehicleCId, $revenueCategoryId, 'revenue', 100_000, '2026-07-10');

        $this->createEntry($company, $author, null, $adminCategoryId, 'expense', 100_000, '2026-07-10');

        $dre = app(DreBuilder::class)->execute($company, '2026-07-01', '2026-07-31');

        $this->assertSame('equal', $dre['apportionment']['method']);
        $this->assertFalse($dre['apportionment']['divisor_zero']);
        $this->assertSame(3, $dre['totals']['vehicles_count']);

        $adminByVehicle = [];

        foreach ($dre['vehicles'] as $vehicle) {
            $adminByVehicle[$vehicle['vehicle_id']] = $vehicle['groups_cents']['admin_expense'];
        }

        $this->assertSame(-100_000, array_sum($adminByVehicle));
        $this->assertSame(-33_333, $adminByVehicle[$vehicleAId]);
        $this->assertSame(-33_333, $adminByVehicle[$vehicleBId]);
        $this->assertSame(-33_334, $adminByVehicle[$vehicleCId]);
    }

    public function test_rateio_por_km_com_divisor_zero_retorna_rateio_zero_e_aviso(): void
    {
        $company = $this->createCompany(902);
        $author = User::factory()->create();

        $this->setApportionmentMethod($company, 'by_km');

        $tenant = app(TenantContext::class);

        [$vehicleAId, $vehicleBId, $adminCategoryId] = $tenant->runFor(
            $company,
            function () use ($company): array {
                app(SeedDefaultFinancialCategories::class)->execute($company);

                $vehicleA = Vehicle::query()->create([
                    'plate' => 'QDD4D44',
                    'type' => 'tractor',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                $vehicleB = Vehicle::query()->create([
                    'plate' => 'QEE5E55',
                    'type' => 'truck',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                $adminCategory = FinancialCategory::query()->where('code', '5.1')->firstOrFail();

                return [
                    (int) $vehicleA->getKey(),
                    (int) $vehicleB->getKey(),
                    (int) $adminCategory->getKey(),
                ];
            }
        );

        $this->createEntry($company, $author, null, $adminCategoryId, 'expense', 20_000, '2026-07-15');

        $dre = app(DreBuilder::class)->execute($company, '2026-07-01', '2026-07-31');

        $this->assertSame('by_km', $dre['apportionment']['method']);
        $this->assertTrue($dre['apportionment']['divisor_zero']);
        $this->assertNotEmpty($dre['apportionment']['warnings']);

        $adminByVehicle = [];

        foreach ($dre['vehicles'] as $vehicle) {
            $adminByVehicle[$vehicle['vehicle_id']] = $vehicle['groups_cents']['admin_expense'];
        }

        $this->assertSame(0, $adminByVehicle[$vehicleAId]);
        $this->assertSame(0, $adminByVehicle[$vehicleBId]);
        $this->assertSame(0, array_sum($adminByVehicle));
    }

    public function test_ignora_categoria_sem_efeito_de_caixa_no_dre(): void
    {
        $company = $this->createCompany(904);
        $author = User::factory()->create();

        $this->setApportionmentMethod($company, 'equal');

        $tenant = app(TenantContext::class);

        [$vehicleId, $nonCashAdminCategoryId] = $tenant->runFor(
            $company,
            function () use ($company): array {
                app(SeedDefaultFinancialCategories::class)->execute($company);

                $vehicle = Vehicle::query()->create([
                    'plate' => 'QFF6F66',
                    'type' => 'tractor',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                $category = FinancialCategory::query()->create([
                    'code' => '9.5',
                    'name' => 'Ajuste sem caixa',
                    'type' => 'expense',
                    'dre_group' => 'admin_expense',
                    'allocation' => 'apportioned',
                    'affects_cashflow' => false,
                    'is_system' => false,
                    'active' => true,
                    'sort_order' => 950,
                ]);

                return [(int) $vehicle->getKey(), (int) $category->getKey()];
            }
        );

        $this->createEntry($company, $author, null, $nonCashAdminCategoryId, 'expense', 50_000, '2026-07-20');

        $dre = app(DreBuilder::class)->execute($company, '2026-07-01', '2026-07-31');

        $this->assertSame(0, $dre['totals']['admin_expense_cents']);
        $this->assertSame(0, $dre['vehicles'][0]['groups_cents']['admin_expense']);
        $this->assertSame($vehicleId, $dre['vehicles'][0]['vehicle_id']);
    }

    public function test_calcula_km_consumo_por_km_e_detalhe_por_categoria(): void
    {
        $company = $this->createCompany(905);
        $author = User::factory()->create();

        $this->setApportionmentMethod($company, 'none');

        $tenant = app(TenantContext::class);

        [$vehicleId, $revenueCategoryId] = $tenant->runFor(
            $company,
            function () use ($company): array {
                app(SeedDefaultFinancialCategories::class)->execute($company);

                $vehicle = Vehicle::query()->create([
                    'plate' => 'QGG7G77',
                    'type' => 'tractor',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                $revenueCategory = FinancialCategory::query()->where('code', '1.1')->firstOrFail();

                return [
                    (int) $vehicle->getKey(),
                    (int) $revenueCategory->getKey(),
                ];
            }
        );

        $this->createEntry($company, $author, $vehicleId, $revenueCategoryId, 'revenue', 100_000, '2026-07-10');

        // O abastecimento gera a despesa de combustível via EntrySynchronizer
        // (regra 7). 400 L (R$ 2.000) + 500 L (R$ 2.500) = R$ 4.500.
        $this->createFueling($company, $author, $vehicleId, 1_000, 2.5, '2026-07-11 08:00:00');
        $this->createFueling($company, $author, $vehicleId, 1_500, 3.0, '2026-07-18 08:00:00');

        $dre = app(DreBuilder::class)->execute($company, '2026-07-01', '2026-07-31');

        $vehicle = $dre['vehicles'][0];

        $this->assertSame($vehicleId, $vehicle['vehicle_id']);
        $this->assertSame(2_500, $vehicle['km']);
        // 2500 km / (1000/2.5 + 1500/3.0 = 900 L) = 2,78 km/l
        $this->assertSame(2.78, $vehicle['consumption']);
        // Receita líquida R$ 1.000,00 / 2.500 km = 0,40
        $this->assertSame(0.4, $vehicle['per_km']['revenue']);
        // Custo total R$ 4.500,00 / 2.500 km = 1,80
        $this->assertSame(1.8, $vehicle['per_km']['cost']);

        $this->assertSame(2_500, $dre['totals']['km']);
        $this->assertSame(2.78, $dre['totals']['consumption']);

        $categoriesByCode = [];

        foreach ($vehicle['categories'] as $category) {
            $categoriesByCode[$category['code']] = $category['amount_cents'];
        }

        $this->assertSame(100_000, $categoriesByCode['1.1']);
        $this->assertSame(-450_000, $categoriesByCode['3.1']);
    }

    public function test_calcula_reservas_e_resultado_economico_com_override_por_veiculo(): void
    {
        $company = $this->createCompany(906);
        $author = User::factory()->create();

        $this->setApportionmentMethod($company, 'none');

        $tenant = app(TenantContext::class);

        [$vehicleId, $revenueCategoryId] = $tenant->runFor(
            $company,
            function () use ($company): array {
                app(SeedDefaultFinancialCategories::class)->execute($company);

                $vehicle = Vehicle::query()->create([
                    'plate' => 'QHH8H88',
                    'type' => 'tractor',
                    'status' => 'active',
                    'ownership' => 'own',
                ]);

                // Padrão da empresa.
                VehicleCostParameter::query()->create([
                    'vehicle_id' => null,
                    'tire_set_price_cents' => 800_000,
                    'tire_life_km' => 100_000,
                    'oil_change_cost_cents' => 60_000,
                    'oil_interval_km' => 15_000,
                    'prudential_percent' => 5.0,
                    'driver_salary_cents' => 300_000,
                    'owner_prolabore_cents' => 200_000,
                ]);

                // Override do veículo: só o salário.
                VehicleCostParameter::query()->create([
                    'vehicle_id' => $vehicle->getKey(),
                    'driver_salary_cents' => 400_000,
                ]);

                $revenueCategory = FinancialCategory::query()->where('code', '1.1')->firstOrFail();

                return [
                    (int) $vehicle->getKey(),
                    (int) $revenueCategory->getKey(),
                ];
            }
        );

        $this->createEntry($company, $author, $vehicleId, $revenueCategoryId, 'revenue', 1_000_000, '2026-07-10');
        // 2.500 km e R$ 4.500 de combustível (via EntrySynchronizer).
        $this->createFueling($company, $author, $vehicleId, 1_000, 2.5, '2026-07-11 08:00:00');
        $this->createFueling($company, $author, $vehicleId, 1_500, 3.0, '2026-07-18 08:00:00');

        $dre = app(DreBuilder::class)->execute($company, '2026-07-01', '2026-07-31');

        $vehicle = $dre['vehicles'][0];
        $reserves = $vehicle['reserves'];

        // Resultado de caixa: receita 1.000.000 − combustível 450.000.
        $this->assertSame(550_000, $vehicle['metrics']['net_result_cents']);

        $this->assertSame(-20_000, $reserves['tire_cents']);   // 8 c/km × 2500
        $this->assertSame(-10_000, $reserves['oil_cents']);    // 4 c/km × 2500
        $this->assertSame(-50_000, $reserves['prudential_cents']); // 5% de 1.000.000
        $this->assertSame(-400_000, $reserves['driver_salary_cents']); // override
        $this->assertSame(-200_000, $reserves['owner_prolabore_cents']); // padrão
        $this->assertSame(-680_000, $reserves['total_cents']);

        // Econômico = caixa 550.000 − reservas 680.000.
        $this->assertSame(-130_000, $vehicle['economic_result_cents']);

        $this->assertSame(-680_000, $dre['totals']['reserves']['total_cents']);
        $this->assertSame(-130_000, $dre['totals']['economic_result_cents']);
    }

    public function test_periodo_sem_dados_retorna_zerado_sem_erro(): void
    {
        $company = $this->createCompany(903);

        $dre = app(DreBuilder::class)->execute($company, '2026-07-01', '2026-07-31');

        $this->assertSame([], $dre['vehicles']);
        $this->assertSame(0, $dre['totals']['vehicles_count']);
        $this->assertSame(0, $dre['totals']['net_result_cents']);
        $this->assertSame(0, $dre['totals']['gross_revenue_cents']);
        $this->assertSame(0, $dre['totals']['admin_expense_cents']);
    }

    private function createEntry(
        Company $company,
        User $author,
        ?int $vehicleId,
        int $categoryId,
        string $type,
        int $amountCents,
        string $competenceDate,
    ): void {
        $tenant = app(TenantContext::class);

        $tenant->runFor($company, function () use ($author, $vehicleId, $categoryId, $type, $amountCents, $competenceDate): void {
            FinancialEntry::query()->create([
                'financial_category_id' => $categoryId,
                'bank_account_id' => null,
                'vehicle_id' => $vehicleId,
                'driver_id' => null,
                'trip_id' => null,
                'type' => $type,
                'description' => 'Lançamento para DRE',
                'document_number' => null,
                'competence_date' => $competenceDate,
                'due_date' => null,
                'paid_at' => null,
                'amount_cents' => $amountCents,
                'status' => 'forecast',
                'payment_method' => null,
                'recurrence_id' => null,
                'created_by' => $author->getKey(),
            ]);
        });
    }

    private function createFueling(
        Company $company,
        User $author,
        int $vehicleId,
        int $kmSinceLast,
        float $kmPerLiter,
        string $fueledAt,
    ): void {
        $tenant = app(TenantContext::class);

        $tenant->runFor($company, function () use ($author, $vehicleId, $kmSinceLast, $kmPerLiter, $fueledAt): void {
            $liters = $kmSinceLast / $kmPerLiter;

            Fueling::query()->create([
                'vehicle_id' => $vehicleId,
                'driver_id' => null,
                'supplier_id' => null,
                'product' => 'diesel_s10',
                'tank' => 'main',
                'fueled_at' => $fueledAt,
                'odometer' => $kmSinceLast,
                'liters' => $liters,
                'price_per_liter' => 5.0,
                'total_cents' => (int) round($liters * 500),
                'full_tank' => true,
                'km_since_last' => $kmSinceLast,
                'km_per_liter' => $kmPerLiter,
                'payment_method' => 'pix',
                'created_by' => $author->getKey(),
            ]);
        });
    }

    private function setApportionmentMethod(Company $company, string $method): void
    {
        $company->forceFill([
            'settings' => json_encode([
                'dre_apportionment_method' => $method,
            ], JSON_THROW_ON_ERROR),
        ])->save();
    }

    private function createCompany(int $seed): Company
    {
        $owner = User::factory()->create([
            'email' => 'dre-owner-'.$seed.'@example.com',
        ]);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo DRE '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '99887766'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'DRE Empresa '.$seed.' LTDA',
            'trade_name' => 'DRE Empresa '.$seed,
            'tax_regime' => 'simples',
        ]);
    }
}
