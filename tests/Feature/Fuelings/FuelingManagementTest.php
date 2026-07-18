<?php

declare(strict_types=1);

namespace Tests\Feature\Fuelings;

use App\Domain\Finance\Actions\SeedDefaultFinancialCategories;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fuelings\Models\Fueling;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FuelingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_abastecimento_a_vista_liquida_na_conta_padrao(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(1);

        $response = $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 100000,
            'liters' => '200,000',
            'total' => '1.200,00',
            'payment_method' => 'cash',
            'full_tank' => '1',
        ]));

        $response->assertRedirect();

        $this->assertDatabaseHas('fuelings', [
            'company_id' => $company->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'total_cents' => 120000,
            'full_tank' => 1,
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'company_id' => $company->getKey(),
            'sourceable_type' => Fueling::class,
            'type' => 'expense',
            'status' => 'settled',
            'amount_cents' => 120000,
            'competence_date' => '2026-07-01 00:00:00',
        ]);

        // À vista baixa na conta padrão e reduz o saldo.
        $this->assertDatabaseHas('bank_accounts', [
            'is_default' => 1,
            'current_balance_cents' => -120000,
        ]);
    }

    public function test_abastecimento_faturado_vira_conta_a_pagar(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(2);

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 50000,
            'liters' => '150,000',
            'total' => '900,00',
            'payment_method' => 'invoice',
        ]))->assertRedirect();

        $this->assertDatabaseHas('financial_entries', [
            'company_id' => $company->getKey(),
            'sourceable_type' => Fueling::class,
            'type' => 'expense',
            'status' => 'forecast',
            'bank_account_id' => null,
            'paid_at' => null,
            'amount_cents' => 90000,
        ]);
    }

    public function test_arla_cai_na_categoria_arla_e_nao_calcula_consumo(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(3);

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 20000,
            'product' => 'arla32',
            'liters' => '20,000',
            'total' => '120,00',
            'payment_method' => 'cash',
            'full_tank' => '1',
        ]))->assertRedirect();

        $fueling = app(TenantContext::class)->runFor($company, fn () => Fueling::query()->firstOrFail());

        $this->assertNull($fueling->km_per_liter);

        // A categoria do lançamento deve ser 3.2 (Arla 32).
        app(TenantContext::class)->runFor($company, function () use ($fueling): void {
            $entry = FinancialEntry::query()
                ->where('sourceable_id', $fueling->getKey())
                ->firstOrFail();

            $this->assertSame('3.2', $entry->category?->getAttribute('code'));
        });
    }

    public function test_consumo_calculado_entre_dois_tanques_cheios(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(4);

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 100000, 'liters' => '200,000', 'total' => '1.200,00', 'payment_method' => 'cash', 'full_tank' => '1',
            'fueled_at' => '2026-07-01T08:00',
        ]))->assertRedirect();

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 101000, 'liters' => '100,000', 'total' => '600,00', 'payment_method' => 'cash', 'full_tank' => '1',
            'fueled_at' => '2026-07-05T08:00',
        ]))->assertRedirect();

        $second = app(TenantContext::class)->runFor($company, fn () => Fueling::query()->orderByDesc('fueled_at')->firstOrFail());

        $this->assertSame('10.000', $second->km_per_liter);
    }

    public function test_odometro_regressivo_bloqueia_sem_confirmacao(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(5);

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 100000, 'liters' => '100,000', 'total' => '600,00', 'payment_method' => 'cash',
            'fueled_at' => '2026-07-01T08:00',
        ]))->assertRedirect();

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 90000, 'liters' => '100,000', 'total' => '600,00', 'payment_method' => 'cash',
            'fueled_at' => '2026-07-02T08:00',
        ]))->assertSessionHasErrors('odometer');

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 90000, 'liters' => '100,000', 'total' => '600,00', 'payment_method' => 'cash',
            'fueled_at' => '2026-07-02T08:00', 'allow_odometer_rollback' => '1',
        ]))->assertRedirect();
    }

    public function test_excluir_abastecimento_cancela_lancamento(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(6);

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 10000, 'liters' => '100,000', 'total' => '600,00', 'payment_method' => 'cash',
        ]))->assertRedirect();

        $fueling = app(TenantContext::class)->runFor($company, fn () => Fueling::query()->firstOrFail());

        $this->actingAs($owner)
            ->delete(route('fuelings.destroy', ['fueling' => $fueling->getKey()]))
            ->assertRedirect(route('fuelings.index'));

        $this->assertSoftDeleted('fuelings', ['id' => $fueling->getKey()]);

        $this->assertDatabaseHas('financial_entries', [
            'sourceable_type' => Fueling::class,
            'sourceable_id' => $fueling->getKey(),
            'status' => 'canceled',
        ]);

        // Cancelado devolve o saldo da conta padrão.
        $this->assertDatabaseHas('bank_accounts', [
            'is_default' => 1,
            'current_balance_cents' => 0,
        ]);
    }

    public function test_membro_de_outro_grupo_nao_ve_abastecimento(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(7);

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 10000, 'liters' => '100,000', 'total' => '600,00', 'payment_method' => 'cash',
        ]))->assertRedirect();

        $fueling = app(TenantContext::class)->runFor($company, fn () => Fueling::query()->firstOrFail());

        [$intruder] = $this->scenario(8);

        $this->actingAs($intruder)
            ->get(route('fuelings.show', ['fueling' => $fueling->getKey()]))
            ->assertNotFound();
    }

    public function test_abastecimento_vincula_motorista_e_posto_e_propaga_ao_lancamento(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(9);

        [$driverId, $stationId] = app(TenantContext::class)->runFor($company, function (): array {
            $driver = Driver::query()->create([
                'name' => 'José Motorista', 'cpf' => '52998224725', 'status' => 'active',
            ]);
            $station = BusinessPartner::query()->create([
                'legal_name' => 'Posto Bom Preço LTDA', 'kind' => 'gas_station', 'active' => true,
            ]);

            return [(int) $driver->getKey(), (int) $station->getKey()];
        });

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 20000, 'liters' => '100,000', 'total' => '600,00', 'payment_method' => 'invoice',
            'driver_id' => $driverId, 'supplier_id' => $stationId,
        ]))->assertRedirect();

        $this->assertDatabaseHas('fuelings', [
            'company_id' => $company->getKey(),
            'driver_id' => $driverId,
            'supplier_id' => $stationId,
        ]);

        // O vínculo de motorista chega ao lançamento financeiro (DRE por motorista).
        $this->assertDatabaseHas('financial_entries', [
            'sourceable_type' => Fueling::class,
            'driver_id' => $driverId,
        ]);
    }

    public function test_motorista_de_outra_empresa_e_bloqueado(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(10);
        [, $otherCompany] = $this->scenario(11);

        $foreignDriverId = app(TenantContext::class)->runFor($otherCompany, fn (): int => (int) Driver::query()->create([
            'name' => 'Motorista Alheio', 'cpf' => '11144477735', 'status' => 'active',
        ])->getKey());

        $this->actingAs($owner)->post(route('fuelings.store'), $this->payload($vehicle, [
            'odometer' => 30000, 'driver_id' => $foreignDriverId,
        ]))->assertSessionHasErrors('driver_id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Vehicle $vehicle, array $overrides): array
    {
        return array_merge([
            'vehicle_id' => $vehicle->getKey(),
            'fueled_at' => '2026-07-01T08:00',
            'odometer' => 100000,
            'product' => 'diesel_s10',
            'tank' => 'main',
            'liters' => '100,000',
            'total' => '600,00',
            'payment_method' => 'cash',
        ], $overrides);
    }

    /**
     * @return array{0: User, 1: Company, 2: Vehicle}
     */
    private function scenario(int $seed): array
    {
        $owner = User::factory()->create(['email' => 'fuel-mgmt-'.$seed.'@example.com']);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo FuelM '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '11223344'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'FuelM Empresa '.$seed.' LTDA',
            'trade_name' => 'FuelM Empresa '.$seed,
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

        $vehicle = app(TenantContext::class)->runFor($company, function () use ($company, $seed): Vehicle {
            app(SeedDefaultFinancialCategories::class)->execute($company);

            BankAccount::query()->create([
                'name' => 'Caixa', 'type' => 'cash', 'initial_balance_cents' => 0,
                'current_balance_cents' => 0, 'is_default' => true, 'active' => true,
            ]);

            return Vehicle::query()->create([
                'company_id' => $company->getKey(),
                'plate' => 'FLM'.str_pad((string) $seed, 4, '0', STR_PAD_LEFT),
                'type' => 'tractor', 'status' => 'active', 'ownership' => 'own',
                'odometer_initial' => 0, 'odometer_current' => 0,
            ]);
        });

        return [$owner, $company, $vehicle];
    }
}
