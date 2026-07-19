<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fleet\Models\VehicleCostParameter;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CostParametersScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_renderiza_para_gestor(): void
    {
        [$owner] = $this->scenario(1);

        $this->actingAs($owner)
            ->get(route('cost-parameters.edit'))
            ->assertOk()
            ->assertSee('Parâmetros de custo')
            ->assertSee('Padrão da empresa');
    }

    public function test_salva_padrao_da_empresa_e_override_do_veiculo(): void
    {
        [$owner, $company, $vehicleId] = $this->scenario(2);

        $this->actingAs($owner)
            ->put(route('cost-parameters.update'), [
                'default' => [
                    'oil_reserve_per_km' => '0,0700',
                    'tire_reserve_per_km' => '0,2513',
                    'prudential_reserve_per_km' => '0,2000',
                    'driver_salary' => '3.000,00',
                    'prolabore_percent' => '5',
                ],
                'vehicles' => [
                    (string) $vehicleId => [
                        'driver_salary' => '4.000,00',
                    ],
                ],
            ])
            ->assertRedirect(route('cost-parameters.edit'));

        app(TenantContext::class)->runFor($company, function () use ($vehicleId): void {
            $default = VehicleCostParameter::query()->whereNull('vehicle_id')->firstOrFail();
            $this->assertSame('0.0700', (string) $default->getAttribute('oil_reserve_per_km'));
            $this->assertSame('0.2513', (string) $default->getAttribute('tire_reserve_per_km'));
            $this->assertSame('0.2000', (string) $default->getAttribute('prudential_reserve_per_km'));
            $this->assertSame(300_000, (int) $default->getAttribute('driver_salary_cents'));
            $this->assertSame('5.00', (string) $default->getAttribute('prolabore_percent'));

            $override = VehicleCostParameter::query()->where('vehicle_id', $vehicleId)->firstOrFail();
            $this->assertSame(400_000, (int) $override->getAttribute('driver_salary_cents'));
            $this->assertNull($override->getAttribute('oil_reserve_per_km'));
        });
    }

    public function test_percentual_de_prolabore_acima_de_cem_e_rejeitado(): void
    {
        [$owner] = $this->scenario(3);

        $this->actingAs($owner)
            ->put(route('cost-parameters.update'), [
                'default' => ['prolabore_percent' => '150'],
            ])
            ->assertSessionHasErrors('default.prolabore_percent');
    }

    /**
     * @return array{0: User, 1: Company, 2: int}
     */
    private function scenario(int $seed): array
    {
        $owner = User::factory()->create(['email' => 'cost-params-'.$seed.'@example.com']);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Custo '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '44556677'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Custo '.$seed.' LTDA',
            'trade_name' => 'Custo '.$seed,
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

        $vehicleId = app(TenantContext::class)->runFor($company, function (): int {
            $vehicle = Vehicle::query()->create([
                'plate' => 'QII9I99',
                'type' => 'tractor',
                'status' => 'active',
                'ownership' => 'own',
            ]);

            return (int) $vehicle->getKey();
        });

        return [$owner, $company, $vehicleId];
    }
}
