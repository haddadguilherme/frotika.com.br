<?php

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\Enums\VehicleFinancingType;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VehicleFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_cria_veiculo_com_novos_campos_e_casts(): void
    {
        $company = $this->createCompany();

        /** @var TenantContext $tenant */
        $tenant = app(TenantContext::class);

        /** @var Vehicle $vehicle */
        $vehicle = $tenant->runFor($company, fn (): Vehicle => Vehicle::factory()->create([
            'engine_number' => 'MTR-ABC123',
            'axle_distance_m' => 4.25,
            'tire_count' => 10,
            'tire_size' => '295/80R22.5',
            'crlv_due_at' => '2027-02-10',
            'antt_due_at' => '2027-03-15',
            'insurance_due_at' => '2027-04-20',
            'is_financed' => true,
            'financing_type' => VehicleFinancingType::Consortium->value,
            'creditor_name' => 'Sicoob',
        ]));

        $this->assertSame($company->getKey(), (int) $vehicle->getAttribute('company_id'));
        $this->assertSame(VehicleFinancingType::Consortium, $vehicle->financing_type);
        $this->assertInstanceOf(Carbon::class, $vehicle->crlv_due_at);
        $this->assertSame('2027-02-10', $vehicle->crlv_due_at?->toDateString());
        $this->assertSame('2027-03-15', $vehicle->antt_due_at?->toDateString());
        $this->assertSame('2027-04-20', $vehicle->insurance_due_at?->toDateString());

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->getKey(),
            'engine_number' => 'MTR-ABC123',
            'tire_count' => 10,
            'tire_size' => '295/80R22.5',
            'is_financed' => true,
            'financing_type' => VehicleFinancingType::Consortium->value,
            'creditor_name' => 'Sicoob',
        ]);
    }

    private function createCompany(): Company
    {
        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Frota Teste',
            'type' => 'customer',
            'status' => 'active',
        ]);

        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '12345678000195',
            'legal_name' => 'Transportadora Teste LTDA',
            'trade_name' => 'Transporte Teste',
            'tax_regime' => 'simples',
        ]);
    }
}
