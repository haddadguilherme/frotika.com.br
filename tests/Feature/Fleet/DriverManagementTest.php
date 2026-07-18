<?php

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\Models\Driver;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DriverManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_motorista_com_cnh(): void
    {
        [$owner, $company] = $this->scenario(1);

        $this->actingAs($owner)->post(route('drivers.store'), [
            'name' => 'João da Silva',
            'cpf' => '529.982.247-25',
            'cnh_number' => '12345678900',
            'cnh_category' => 'E',
            'cnh_expires_at' => '2027-01-31',
            'status' => 'active',
        ])->assertRedirect();

        $driver = app(TenantContext::class)->runFor($company, fn () => Driver::query()->firstOrFail());

        $this->assertSame('João da Silva', $driver->getAttribute('name'));
        $this->assertSame('52998224725', $driver->getAttribute('cpf'));
        $this->assertSame('E', $driver->cnh_category?->value);
        $this->assertSame('2027-01-31', $driver->cnh_expires_at?->toDateString());
    }

    public function test_cpf_invalido_e_bloqueado(): void
    {
        [$owner] = $this->scenario(2);

        $this->actingAs($owner)->post(route('drivers.store'), [
            'name' => 'Maria',
            'cpf' => '111.111.111-11',
            'status' => 'active',
        ])->assertSessionHasErrors('cpf');

        $this->assertDatabaseCount('drivers', 0);
    }

    public function test_cpf_duplicado_na_empresa_e_bloqueado(): void
    {
        [$owner, $company] = $this->scenario(3);

        $payload = ['name' => 'A', 'cpf' => '529.982.247-25', 'status' => 'active'];

        $this->actingAs($owner)->post(route('drivers.store'), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('drivers.store'), ['name' => 'B', 'cpf' => '52998224725', 'status' => 'active'])
            ->assertSessionHasErrors('cpf');

        $this->assertSame(1, app(TenantContext::class)->runFor($company, fn () => Driver::query()->count()));
    }

    public function test_alerta_de_cnh_vencida_e_a_vencer(): void
    {
        $expired = (new Driver)->forceFill(['cnh_expires_at' => Carbon::today()->subDay()]);
        $this->assertSame('expired', $expired->cnhAlert());

        $expiring = (new Driver)->forceFill(['cnh_expires_at' => Carbon::today()->addDays(10)]);
        $this->assertSame('expiring', $expiring->cnhAlert());
        $this->assertSame(10, $expiring->cnhDaysToExpire());

        $ok = (new Driver)->forceFill(['cnh_expires_at' => Carbon::today()->addDays(90)]);
        $this->assertNull($ok->cnhAlert());

        $none = new Driver;
        $this->assertNull($none->cnhAlert());
        $this->assertNull($none->cnhDaysToExpire());
    }

    public function test_atualiza_motorista(): void
    {
        [$owner, $company] = $this->scenario(4);

        $this->actingAs($owner)->post(route('drivers.store'), [
            'name' => 'Carlos', 'cpf' => '529.982.247-25', 'status' => 'active',
        ])->assertRedirect();

        $driver = app(TenantContext::class)->runFor($company, fn () => Driver::query()->firstOrFail());

        $this->actingAs($owner)->put(route('drivers.update', ['driver' => $driver->getKey()]), [
            'name' => 'Carlos Souza', 'cpf' => '529.982.247-25', 'status' => 'inactive',
        ])->assertRedirect(route('drivers.show', ['driver' => $driver->getKey()]));

        $driver->refresh();
        $this->assertSame('Carlos Souza', $driver->getAttribute('name'));
        $this->assertSame('inactive', $driver->status->value);
    }

    public function test_membro_de_outro_grupo_nao_ve_motorista(): void
    {
        [$owner, $company] = $this->scenario(5);

        $this->actingAs($owner)->post(route('drivers.store'), [
            'name' => 'Privado', 'cpf' => '529.982.247-25', 'status' => 'active',
        ])->assertRedirect();

        $driver = app(TenantContext::class)->runFor($company, fn () => Driver::query()->firstOrFail());

        [$intruder] = $this->scenario(6);

        $this->actingAs($intruder)
            ->get(route('drivers.show', ['driver' => $driver->getKey()]))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function scenario(int $seed): array
    {
        $owner = User::factory()->create(['email' => 'driver-'.$seed.'@example.com']);

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Driver '.$seed,
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '33445566'.str_pad((string) $seed, 6, '0', STR_PAD_LEFT),
            'legal_name' => 'Driver Empresa '.$seed.' LTDA',
            'trade_name' => 'Driver Empresa '.$seed,
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
