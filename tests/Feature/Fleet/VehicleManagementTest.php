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
            ]);

        $vehicle = Vehicle::withoutGlobalScopes()->where('plate', 'ABC1D23')->firstOrFail();

        $response->assertRedirect(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));
        $this->assertSame(VehicleType::Tractor, $vehicle->type);
        $this->assertSame(VehicleFuelType::DieselS10, $vehicle->fuel_type);
        $this->assertSame(45000000, (int) $vehicle->getAttribute('acquisition_value_cents'));
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

    public function test_mass_assignment_de_company_id_nao_altera_tenant_no_cadastro(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $otherOwner = User::factory()->create();
        $otherGroup = $this->createGroup($otherOwner);
        $otherCompany = $this->createCompany($otherGroup, '11222333000144');

        $this
            ->actingAs($owner)
            ->post(route('vehicles.store'), [
                'plate' => 'QWE1R23',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'company_id' => $otherCompany->getKey(),
            ])
            ->assertRedirect();

        $vehicle = Vehicle::withoutGlobalScopes()->where('plate', 'QWE1R23')->firstOrFail();

        $this->assertSame($company->getKey(), (int) $vehicle->getAttribute('company_id'));
        $this->assertNotSame($otherCompany->getKey(), (int) $vehicle->getAttribute('company_id'));
    }

    public function test_mass_assignment_de_company_id_nao_altera_tenant_na_edicao(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'TRE2E34');

        $otherOwner = User::factory()->create();
        $otherGroup = $this->createGroup($otherOwner);
        $otherCompany = $this->createCompany($otherGroup, '11222333000155');

        $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $vehicle->getKey()]), [
                'plate' => 'TRE2E34',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'DAF',
                'company_id' => $otherCompany->getKey(),
            ])
            ->assertRedirect();

        $vehicle = $vehicle->refresh();

        $this->assertSame($company->getKey(), (int) $vehicle->getAttribute('company_id'));
        $this->assertNotSame($otherCompany->getKey(), (int) $vehicle->getAttribute('company_id'));
        $this->assertSame('DAF', $vehicle->getAttribute('brand'));
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

    public function test_editar_veiculo_provisionado_sem_campos_minimos_mantem_provisionado(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'HJK2L45', provisioned: true);

        $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $vehicle->getKey()]), [
                'plate' => 'HJK2L45',
                'type' => VehicleType::Tractor->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'Scania',
                'model' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->getKey(),
            'provisioned' => true,
        ]);
    }

    public function test_editar_veiculo_completo_nunca_retorna_para_provisionado(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'ZXC3V67', provisioned: false);

        $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $vehicle->getKey()]), [
                'plate' => 'ZXC3V67',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'Iveco',
                'model' => 'Tector',
                'provisioned' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->getKey(),
            'provisioned' => false,
            'brand' => 'Iveco',
            'model' => 'Tector',
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

    public function test_exibicao_de_hodometro_mostra_unidade_uma_unica_vez(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();

        $vehicle = app(TenantContext::class)->runFor($company, function (): Vehicle {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->create([
                'plate' => 'KMU1N23',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'odometer_initial' => 1331430,
            ]);

            $vehicle->setAttribute('odometer_current', 1331430);
            $vehicle->save();

            return $vehicle;
        });

        $response = $this->actingAs($owner)->get(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));

        $response->assertOk();
        $response->assertSee('1.331.430 km');
        $response->assertDontSee('1.331.430 km km');
    }

    public function test_exibicao_nao_mostra_m3_para_cavalo_semirreboque(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();

        $vehicle = app(TenantContext::class)->runFor($company, function (): Vehicle {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->create([
                'plate' => 'VOL0M30',
                'type' => VehicleType::Tractor->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'capacity_m3' => 12.500,
            ]);

            return $vehicle;
        });

        $response = $this->actingAs($owner)->get(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));

        $response->assertOk();
        $response->assertDontSee('m³');
    }

    public function test_edicao_salva_datas_de_documentacao_do_veiculo(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'DOC9A01');

        $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $vehicle->getKey()]), [
                'plate' => 'DOC9A01',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'Volvo',
                'model' => 'VM 360',
                'crlv_due_at' => '2027-06-10',
                'insurance_due_at' => '2027-06-20',
                'antt_due_at' => '2027-07-05',
            ])
            ->assertRedirect();

        $vehicle = $vehicle->refresh();

        $this->assertSame('2027-06-10', $vehicle->crlv_due_at?->toDateString());
        $this->assertSame('2027-06-20', $vehicle->insurance_due_at?->toDateString());
        $this->assertSame('2027-07-05', $vehicle->antt_due_at?->toDateString());

        $show = $this->actingAs($owner)->get(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));
        $show->assertOk();
        $show->assertSee('10/06/2027');
        $show->assertSee('20/06/2027');
        $show->assertSee('05/07/2027');
    }

    public function test_exibicao_marca_documento_vencido_com_selo_de_danger(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();

        $vehicle = app(TenantContext::class)->runFor($company, function (): Vehicle {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->create([
                'plate' => 'VNC1D00',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'crlv_due_at' => now()->subDay()->toDateString(),
            ]);

            return $vehicle;
        });

        $response = $this->actingAs($owner)->get(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));

        $response->assertOk();
        $response->assertSee('Vencido');
        $response->assertSee('text-danger-700', false);
    }

    public function test_veiculo_provisionado_aparece_no_filtro_e_some_ao_completar_cadastro(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $provisioned = $this->makeVehicle($company, 'PRV1A11', provisioned: true);
        $this->makeVehicle($company, 'CMP2B22', provisioned: false);

        $filtered = $this->actingAs($owner)->get(route('vehicles.index', ['provisioned' => 1]));

        $filtered->assertOk();
        $filtered->assertSee('PRV1A11');
        $filtered->assertDontSee('CMP2B22');

        $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $provisioned->getKey()]), [
                'plate' => 'PRV1A11',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'Scania',
                'model' => 'R 460',
            ])
            ->assertRedirect();

        $filteredAfter = $this->actingAs($owner)->get(route('vehicles.index', ['provisioned' => 1]));

        $filteredAfter->assertOk();
        $filteredAfter->assertDontSee('PRV1A11');
    }

    public function test_contador_de_provisorios_respeita_tenant_da_empresa_ativa(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $this->makeVehicle($company, 'TEN1A11', provisioned: true);
        $this->makeVehicle($company, 'TEN2B22', provisioned: true);

        $otherOwner = User::factory()->create();
        $otherGroup = $this->createGroup($otherOwner);
        $otherCompany = $this->createCompany($otherGroup, '22333444000177');
        $this->makeVehicle($otherCompany, 'OUT3C33', provisioned: true);

        $response = $this->actingAs($owner)->get(route('vehicles.index'));

        $response->assertOk();
        $response->assertSee('2 veículos aguardando cadastro completo.');
        $response->assertDontSee('3 veículos aguardando cadastro completo.');
    }

    public function test_detalhe_de_veiculo_provisionado_exibe_acao_completar_cadastro(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'CTA7D77', provisioned: true);

        $response = $this->actingAs($owner)->get(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));

        $response->assertOk();
        $response->assertSee('Cadastro incompleto');
        $response->assertSee('Completar cadastro');
    }

    public function test_edicao_salva_bloco_de_especificacoes_e_propriedade(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $vehicle = $this->makeVehicle($company, 'ESP4P44');

        $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $vehicle->getKey()]), [
                'plate' => 'ESP4P44',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Leased->value,
                'brand' => 'Volvo',
                'model' => 'VM 360',
                'engine_number' => 'ENG-4455AB',
                'axle_distance_m' => '4.15',
                'tire_count' => '10',
                'tire_size' => '295/80R22.5',
                'is_financed' => '1',
                'financing_type' => 'leasing',
                'creditor_name' => 'Itaú BBA',
            ])
            ->assertRedirect();

        $vehicle = $vehicle->refresh();

        $this->assertSame('ENG-4455AB', $vehicle->getAttribute('engine_number'));
        $this->assertSame('4.15', (string) $vehicle->getAttribute('axle_distance_m'));
        $this->assertSame(10, (int) $vehicle->getAttribute('tire_count'));
        $this->assertSame('295/80R22.5', $vehicle->getAttribute('tire_size'));
        $this->assertTrue((bool) $vehicle->getAttribute('is_financed'));
        $this->assertSame('leasing', $vehicle->getAttribute('financing_type')?->value);
        $this->assertSame('Itaú BBA', $vehicle->getAttribute('creditor_name'));

        $show = $this->actingAs($owner)->get(route('vehicles.show', ['vehicle' => $vehicle->getKey()]));
        $show->assertOk();
        $show->assertSee('ENG-4455AB');
        $show->assertSee('4.15 m');
        $show->assertSee('295/80R22.5');
        $show->assertSee('Leasing');
        $show->assertSee('Itaú BBA');
    }

    public function test_desligar_toggle_de_financiamento_limpa_campos_relacionados(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();

        $vehicle = app(TenantContext::class)->runFor($company, function (): Vehicle {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->create([
                'plate' => 'LMP5R55',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'Scania',
                'model' => 'R 450',
                'is_financed' => true,
                'financing_type' => 'bank_loan',
                'creditor_name' => 'Banco do Brasil',
            ]);

            return $vehicle;
        });

        $this
            ->actingAs($owner)
            ->put(route('vehicles.update', ['vehicle' => $vehicle->getKey()]), [
                'plate' => 'LMP5R55',
                'type' => VehicleType::Truck->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
                'brand' => 'Scania',
                'model' => 'R 450',
            ])
            ->assertRedirect();

        $vehicle = $vehicle->refresh();

        $this->assertFalse((bool) $vehicle->getAttribute('is_financed'));
        $this->assertNull($vehicle->getAttribute('financing_type'));
        $this->assertNull($vehicle->getAttribute('creditor_name'));
    }

    private function makeVehicle(Company $company, string $plate, bool $provisioned = false): Vehicle
    {
        return app(TenantContext::class)->runFor($company, function () use ($plate, $provisioned): Vehicle {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->create([
                'plate' => $plate,
                'type' => VehicleType::Tractor->value,
                'status' => VehicleStatus::Active->value,
                'ownership' => VehicleOwnership::Own->value,
            ]);

            if ($provisioned) {
                $vehicle->setAttribute('provisioned', true);
                $vehicle->save();
            }

            return $vehicle;
        });
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
