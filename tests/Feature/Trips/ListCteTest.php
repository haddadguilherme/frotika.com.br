<?php

declare(strict_types=1);

namespace Tests\Feature\Trips;

use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Finance\Actions\SeedDefaultFinancialCategories;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Enums\CteServiceType;
use App\Domain\Trips\Enums\CteStatus;
use App\Domain\Trips\Enums\CteType;
use App\Domain\Trips\Models\CteDocument;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ListCteTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_traz_apenas_o_mes_corrente_por_padrao(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();

        $this->createCte($company, [
            'sender_name' => 'REMETENTE ATUAL LTDA',
            'issued_at' => CarbonImmutable::now()->startOfMonth()->addDays(3),
        ]);
        $this->createCte($company, [
            'sender_name' => 'REMETENTE ANTIGO LTDA',
            'issued_at' => CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->addDays(3),
        ]);

        $this->actingAs($owner)
            ->get(route('cte.index'))
            ->assertOk()
            ->assertSee('REMETENTE ATUAL LTDA')
            ->assertSee('DESTINATARIO LTDA')
            ->assertSee('São Paulo/SP')
            ->assertDontSee('REMETENTE ANTIGO LTDA');
    }

    public function test_filtro_por_intervalo_de_datas(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();

        $lastMonth = CarbonImmutable::now()->subMonthNoOverflow();

        $this->createCte($company, [
            'sender_name' => 'REMETENTE ATUAL LTDA',
            'issued_at' => CarbonImmutable::now()->startOfMonth()->addDays(3),
        ]);
        $this->createCte($company, [
            'sender_name' => 'REMETENTE ANTIGO LTDA',
            'issued_at' => $lastMonth->startOfMonth()->addDays(3),
        ]);

        $this->actingAs($owner)
            ->get(route('cte.index', [
                'from' => $lastMonth->startOfMonth()->format('Y-m-d'),
                'to' => $lastMonth->endOfMonth()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee('REMETENTE ANTIGO LTDA')
            ->assertDontSee('REMETENTE ATUAL LTDA');
    }

    public function test_baixa_o_xml_do_cte(): void
    {
        Storage::fake('local');
        [$owner, $company] = $this->createOwnerWithCompany();

        $xml = '<?xml version="1.0"?><cteProc>conteudo</cteProc>';
        $path = 'grupos/teste/cte/2026/07/chave.xml';
        Storage::disk('local')->put($path, $xml);

        $cte = $this->createCte($company, ['xml_path' => $path]);

        $response = $this->actingAs($owner)->get(route('cte.xml', ['cte' => $cte->getKey()]));

        $response->assertOk();
        $response->assertDownload($cte->getAttribute('access_key').'.xml');
        $this->assertSame($xml, $response->streamedContent());
    }

    public function test_download_retorna_404_quando_nao_ha_xml(): void
    {
        Storage::fake('local');
        [$owner, $company] = $this->createOwnerWithCompany();

        $cte = $this->createCte($company, ['xml_path' => null]);

        $this->actingAs($owner)
            ->get(route('cte.xml', ['cte' => $cte->getKey()]))
            ->assertNotFound();
    }

    private function createCte(Company $company, array $overrides = []): CteDocument
    {
        static $seq = 0;
        $seq++;

        return app(TenantContext::class)->runFor($company, function () use ($overrides, $seq): CteDocument {
            return CteDocument::query()->create(array_merge([
                'access_key' => str_pad((string) $seq, 44, '0', STR_PAD_LEFT),
                'number' => $seq,
                'series' => 1,
                'cte_type' => CteType::Normal->value,
                'service_type' => CteServiceType::Normal->value,
                'issued_at' => CarbonImmutable::now(),
                'issuer_name' => 'EMITENTE LTDA',
                'sender_name' => 'REMETENTE LTDA',
                'recipient_name' => 'DESTINATARIO LTDA',
                'origin_city' => 'São Paulo',
                'origin_state' => 'SP',
                'destination_city' => 'Rio de Janeiro',
                'destination_state' => 'RJ',
                'total_value_cents' => 100000,
                'receivable_value_cents' => 90000,
                'applied_share_percent' => 90,
                'cargo_weight_kg' => 15000,
                'status' => CteStatus::Authorized->value,
            ], $overrides));
        });
    }

    /**
     * @return array{User, Company}
     */
    private function createOwnerWithCompany(): array
    {
        $owner = User::factory()->create();

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Agregado',
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        GroupLicense::query()->create([
            'group_id' => $group->getKey(),
            'status' => GroupLicenseStatus::Active,
            'trial_starts_at' => now()->subDays(30),
            'activated_at' => now()->subDays(20),
            'monthly_price_cents' => 9900,
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '11222333000181',
            'legal_name' => 'Transportadora Agregada LTDA',
            'trade_name' => 'Agregada',
            'tax_regime' => 'simples',
        ]);

        app(SeedDefaultFinancialCategories::class)->execute($company);

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

        return [$owner, $company];
    }
}
