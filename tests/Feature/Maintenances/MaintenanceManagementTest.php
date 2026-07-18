<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenances;

use App\Domain\Finance\Actions\SeedDefaultFinancialCategories;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Maintenances\Models\Maintenance;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MaintenanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_corretiva_gera_despesa_prevista_na_3_4_com_competencia_na_abertura(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(1);

        $this->actingAs($owner)->post(route('maintenances.store'), $this->payload($vehicle, [
            'type' => 'corrective',
            'status' => 'in_progress',
            'labor' => '300,00',
            'parts' => '450,00',
        ]))->assertRedirect();

        $maintenance = app(TenantContext::class)->runFor($company, fn () => Maintenance::query()->firstOrFail());

        // total = mão de obra + peças
        $this->assertSame(75000, (int) $maintenance->total_cents);

        app(TenantContext::class)->runFor($company, function () use ($maintenance): void {
            $entry = FinancialEntry::query()->where('sourceable_id', $maintenance->getKey())->firstOrFail();

            $this->assertSame('expense', $entry->getAttribute('type')->value);
            $this->assertSame('forecast', $entry->getAttribute('status')->value);
            $this->assertNull($entry->getAttribute('paid_at'));
            $this->assertSame(75000, (int) $entry->getAttribute('amount_cents'));
            $this->assertSame('3.4', $entry->category?->getAttribute('code'));
            // sem conclusão, competência = abertura
            $this->assertSame('2026-07-01', $entry->getAttribute('competence_date')->toDateString());
        });
    }

    public function test_preventiva_cai_na_4_3_com_competencia_na_conclusao(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(2);

        $this->actingAs($owner)->post(route('maintenances.store'), $this->payload($vehicle, [
            'type' => 'preventive',
            'status' => 'completed',
            'opened_at' => '2026-07-01',
            'closed_at' => '2026-07-03',
            'labor' => '200,00',
            'parts' => '0,00',
        ]))->assertRedirect();

        $maintenance = app(TenantContext::class)->runFor($company, fn () => Maintenance::query()->firstOrFail());

        app(TenantContext::class)->runFor($company, function () use ($maintenance): void {
            $entry = FinancialEntry::query()->where('sourceable_id', $maintenance->getKey())->firstOrFail();

            $this->assertSame('4.3', $entry->category?->getAttribute('code'));
            $this->assertSame('2026-07-03', $entry->getAttribute('competence_date')->toDateString());
        });
    }

    public function test_conclusao_obrigatoria_quando_status_concluida(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(3);

        $this->actingAs($owner)->post(route('maintenances.store'), $this->payload($vehicle, [
            'status' => 'completed',
            'closed_at' => '',
        ]))->assertSessionHasErrors('closed_at');
    }

    public function test_cancelar_status_cancela_o_lancamento(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(4);

        $this->actingAs($owner)->post(route('maintenances.store'), $this->payload($vehicle, [
            'labor' => '100,00', 'parts' => '0,00',
        ]))->assertRedirect();

        $maintenance = app(TenantContext::class)->runFor($company, fn () => Maintenance::query()->firstOrFail());

        $this->actingAs($owner)->put(route('maintenances.update', ['maintenance' => $maintenance->getKey()]), $this->payload($vehicle, [
            'status' => 'canceled',
            'labor' => '100,00', 'parts' => '0,00',
        ]))->assertRedirect();

        $this->assertDatabaseHas('financial_entries', [
            'sourceable_type' => Maintenance::class,
            'sourceable_id' => $maintenance->getKey(),
            'status' => 'canceled',
        ]);
    }

    public function test_excluir_cancela_o_lancamento(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(5);

        $this->actingAs($owner)->post(route('maintenances.store'), $this->payload($vehicle, [
            'labor' => '100,00', 'parts' => '0,00',
        ]))->assertRedirect();

        $maintenance = app(TenantContext::class)->runFor($company, fn () => Maintenance::query()->firstOrFail());

        $this->actingAs($owner)
            ->delete(route('maintenances.destroy', ['maintenance' => $maintenance->getKey()]))
            ->assertRedirect(route('maintenances.index'));

        $this->assertSoftDeleted('maintenances', ['id' => $maintenance->getKey()]);

        $this->assertDatabaseHas('financial_entries', [
            'sourceable_type' => Maintenance::class,
            'sourceable_id' => $maintenance->getKey(),
            'status' => 'canceled',
        ]);
    }

    public function test_membro_de_outro_grupo_nao_ve_manutencao(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(6);

        $this->actingAs($owner)->post(route('maintenances.store'), $this->payload($vehicle, [
            'labor' => '100,00', 'parts' => '0,00',
        ]))->assertRedirect();

        $maintenance = app(TenantContext::class)->runFor($company, fn () => Maintenance::query()->firstOrFail());

        [$intruder] = $this->scenario(7);

        $this->actingAs($intruder)
            ->get(route('maintenances.show', ['maintenance' => $maintenance->getKey()]))
            ->assertNotFound();
    }

    public function test_manutencao_vincula_oficina_cadastrada(): void
    {
        [$owner, $company, $vehicle] = $this->scenario(8);

        $workshopId = app(TenantContext::class)->runFor($company, fn (): int => (int) BusinessPartner::query()->create([
            'legal_name' => 'Oficina do Zé LTDA', 'kind' => 'workshop', 'active' => true,
        ])->getKey());

        $this->actingAs($owner)->post(route('maintenances.store'), $this->payload($vehicle, [
            'supplier_id' => $workshopId,
            'labor' => '100,00', 'parts' => '50,00',
        ]))->assertRedirect();

        $this->assertDatabaseHas('maintenances', [
            'company_id' => $company->getKey(),
            'supplier_id' => $workshopId,
            'total_cents' => 15000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Vehicle $vehicle, array $overrides): array
    {
        return array_merge([
            'vehicle_id' => $vehicle->getKey(),
            'type' => 'corrective',
            'category' => 'engine',
            'status' => 'open',
            'opened_at' => '2026-07-01',
            'odometer' => 120000,
            'labor' => '0,00',
            'parts' => '0,00',
        ], $overrides);
    }

    /**
     * @return array{0: User, 1: Company, 2: Vehicle}
     */
    private function scenario(int $seed): array
    {
        $owner = User::factory()->create(['email' => 'maint-'.$seed.'@example.com']);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Maint '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '22334455'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Maint Empresa '.$seed.' LTDA',
            'trade_name' => 'Maint Empresa '.$seed,
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

            return Vehicle::query()->create([
                'company_id' => $company->getKey(),
                'plate' => 'MNT'.str_pad((string) $seed, 4, '0', STR_PAD_LEFT),
                'type' => 'tractor', 'status' => 'active', 'ownership' => 'own',
                'odometer_initial' => 0, 'odometer_current' => 0,
            ]);
        });

        return [$owner, $company, $vehicle];
    }
}
