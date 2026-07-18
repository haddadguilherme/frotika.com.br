<?php

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Fleet\Enums\VehicleFuelType;
use App\Domain\Fleet\Enums\VehicleOwnership;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cadastra_veiculo_normalizando_placa_e_valores(): void
    {
        [$owner] = $this->createOwnerWithCompany();

        $response = $this
            ->actingAs($owner)
            ->post(route('vehicles.store'), [
                'plate' => 'abc1d23',
                'type' => VehicleType::Tractor->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'Scania',
                'model' => 'R450',
                'year_manufacture' => '2020',
                'year_model' => '2021',
                'fuel_type' => VehicleFuelType::DieselS10->value,
                'tank_capacity_l' => '600',
                'odometer_initial' => '350000',
                'acquisition_value' => '450.000,00',
                'residual_value' => '180000',
                'depreciation_months' => '60',
            ]);

        $vehicle = Vehicle::withoutGlobalScopes()->where('plate', 'ABC1D23')->firstOrFail();

        $response->assertRedirect(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));
        $this->assertSame(VehicleType::Tractor, $vehicle->type);
        $this->assertSame(VehicleFuelType::DieselS10, $vehicle->fuel_type);
        $this->assertSame(45000000, (int) $vehicle->getAttribute('acquisition_value_cents'));
        $this->assertSame(18000000, (int) $vehicle->getAttribute('residual_value_cents'));
        $this->assertSame(350000, (int) $vehicle->getAttribute('odometer_current'));
        $this->assertFalse((bool) $vehicle->getAttribute('provisioned'));
    }

    public function test_placa_duplicada_e_rejeitada(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $this->makeVehicle($company, 'ABC1D23');

        $response = $this
            ->actingAs($owner)
            ->from(route('vehicles.create'))
            ->post(route('vehicles.store'), [
                'plate' => 'ABC1D23',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
            ]);

        $response->assertRedirect(route('vehicles.create'));
        $response->assertSessionHasErrors(['plate']);
    }

    public function test_placa_invalida_e_rejeitada(): void
    {
        [$owner] = $this->createOwnerWithCompany();

        $response = $this
            ->actingAs($owner)
            ->from(route('vehicles.create'))
            ->post(route('vehicles.store'), [
                'plate' => 'XX999',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
            ]);

        $response->assertRedirect(route('vehicles.create'));
        $response->assertSessionHasErrors(['plate']);
    }

    public function test_membro_sem_papel_de_gestao_nao_cadastra(): void
    {
        [, , $group] = $this->createOwnerWithCompany();
        $member = $this->createMember($group, 'manager');

        $this->actingAs($member)->get(route('vehicles.create'))->assertForbidden();

        $this->actingAs($member)
            ->post(route('vehicles.store'), [
                'plate' => 'XYZ4A56',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('vehicles', ['plate' => 'XYZ4A56']);
    }

    public function test_editar_veiculo_provisionado_finaliza_cadastro(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'GXX8D33', provisioned: true);

        $response = $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $vehicle->getKey()]), [
                'plate' => 'GXX8D33',
                'type' => VehicleType::Tractor->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Aggregate->value,
                'brand' => 'Volvo',
                'model' => 'FH 540',
            ]);

        $response->assertRedirect(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->getKey(),
            'brand' => 'Volvo',
            'ownership' => VehicleOwnership::Aggregate->value,
            'provisioned' => false,
        ]);
    }

    public function test_desativa_veiculo(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'ABC1D23');

        $response = $this
            ->actingAs($owner)
            ->delete(route('vehicles.destroy', ['vehicle' => $vehicle->getKey()]));

        $response->assertRedirect(route('vehicles.index'));
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->getKey()]);
    }

    public function test_listagem_mostra_apenas_veiculos_da_empresa_ativa(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $this->makeVehicle($company, 'AAA1A11');

        $otherOwner = User::factory()->create();
        $otherGroup = $this->createGroup($otherOwner);
        $otherCompany = $this->createCompany($otherGroup, '55111222000305');
        $this->makeVehicle($otherCompany, 'BBB2B22');

        $response = $this->actingAs($owner)->get(route('vehicles.index'));

        $response->assertOk();
        $response->assertSee('AAA1A11');
        $response->assertDontSee('BBB2B22');
    }

    private function makeVehicle(Company $company, string $plate, bool $provisioned = false): Vehicle
    {
        return app(TenantContext::class)->runFor($company, fn (): Vehicle => Vehicle::query()->create([
            'plate' => $plate,
            'type' => VehicleType::Tractor->value,
            'status' => VehicleStatus::Active->value,
            'ownership' => VehicleOwnership::Own->value,
            'provisioned' => $provisioned,
        ]));
    }

    /**
     * @return array{User, Company, Group}
     */
    private function createOwnerWithCompany(): array
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        GroupLicense::query()->create([
            'group_id' => $group->getKey(),
            'status' => GroupLicenseStatus::Active,
            'trial_starts_at' => now()->subDays(30),
            'activated_at' => now()->subDays(20),
            'monthly_price_cents' => 9900,
        ]);

        $company = $this->createCompany($group, '55111222000112');

        $group->forceFill(['primary_company_id' => $company->getKey()])->save();

        $group->users()->attach($owner->getKey(), [
            'role' => 'owner',
            'invited_by' => null,
            'joined_at' => now(),
        ]);

        $owner->companies()->attach($company->getKey());
        $owner->forceFill([
            'current_group_id' => $group->getKey(),
            'current_company_id' => $company->getKey(),
        ])->save();

        return [$owner, $company, $group];
    }

    private function createMember(Group $group, string $role): User
    {
        $member = User::factory()->create();

        $group->users()->attach($member->getKey(), [
            'role' => $role,
            'invited_by' => null,
            'joined_at' => now(),
        ]);

        $primaryCompanyId = (int) $group->refresh()->primary_company_id;
        $member->companies()->attach($primaryCompanyId);
        $member->forceFill([
            'current_group_id' => $group->getKey(),
            'current_company_id' => $primaryCompanyId,
        ])->save();

        return $member;
    }

    private function createGroup(User $owner): Group
    {
        return Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo '.Str::random(5),
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);
    }

    private function createCompany(Group $group, string $cnpj): Company
    {
        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => $cnpj,
            'legal_name' => 'Empresa '.$cnpj,
            'trade_name' => 'Empresa '.$cnpj,
            'tax_regime' => 'simples',
        ]);
    }
}
