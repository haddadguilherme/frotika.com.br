<?php

declare(strict_types=1);

namespace Tests\Feature\Trips;

use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Finance\Actions\SeedDefaultFinancialCategories;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Models\CteDocument;
use App\Domain\Trips\Models\CteImportBatch;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ImportCteTest extends TestCase
{
    use RefreshDatabase;

    private function xml(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/Cte/cte-hi-transportes.xml'));
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cte.xml', $this->xml());
    }

    public function test_importa_cte_cadastra_parceiros_veiculos_e_lanca_receita(): void
    {
        Storage::fake('local');
        [$owner, $company] = $this->createOwnerWithCompany();

        $response = $this
            ->actingAs($owner)
            ->post(route('cte.import.store'), ['xmls' => [$this->upload()]]);

        $batch = CteImportBatch::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('cte.import.result', ['batch' => $batch->getAttribute('uuid')]));

        $cte = CteDocument::withoutGlobalScopes()->firstOrFail();

        $this->assertDatabaseCount('cte_documents', 1);
        $this->assertSame('52260717624719000520570050000167601000167600', $cte->getAttribute('access_key'));

        // Emitente + remetente/expedidor (mesmo CNPJ) + recebedor/destinatário (mesmo CNPJ) = 3 parceiros únicos.
        $this->assertDatabaseCount('business_partners', 3);
        $this->assertDatabaseHas('business_partners', [
            'document' => '17624719000520',
            'kind' => BusinessPartnerKind::Contractor->value,
        ]);

        // 5 papéis no pivô (issuer, sender, dispatcher, receiver, recipient).
        $this->assertDatabaseCount('cte_document_business_partner', 5);

        // Veículos provisionados por placa.
        $this->assertDatabaseHas('vehicles', ['plate' => 'GXX8D33', 'type' => 'tractor', 'provisioned' => true]);
        $this->assertDatabaseHas('vehicles', ['plate' => 'OWO1F78', 'type' => 'semi_trailer', 'provisioned' => true]);

        // XML guardado em área privada do grupo.
        $group = Group::query()->findOrFail($company->getAttribute('group_id'));
        $expectedPath = sprintf('grupos/%s/cte/2026/07/%s.xml', $group->getAttribute('uuid'), $cte->getAttribute('access_key'));
        Storage::disk('local')->assertExists($expectedPath);
        $this->assertSame($expectedPath, $cte->getAttribute('xml_path'));
        $this->assertSame(hash('sha256', $this->xml()), $cte->getAttribute('xml_hash'));

        // Receita a receber com valor cheio (sem percentual cadastrado => 100%).
        $entry = FinancialEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(FinancialEntryType::Revenue, $entry->type);
        $this->assertSame(FinancialEntryStatus::Forecast, $entry->status);
        $this->assertSame(623889, (int) $entry->getAttribute('amount_cents'));
        $this->assertSame(CteDocument::class, $entry->getAttribute('sourceable_type'));
        $this->assertSame($cte->getKey(), (int) $entry->getAttribute('sourceable_id'));
        $this->assertNull($entry->getAttribute('paid_at'));
        $this->assertSame('2026-07-13', $entry->getAttribute('competence_date')->toDateString());
        $this->assertSame('2026-08-12', $entry->getAttribute('due_date')->toDateString());
    }

    public function test_importa_cte_provisiona_e_vincula_o_motorista(): void
    {
        Storage::fake('local');
        [$owner, $company] = $this->createOwnerWithCompany();

        $this->actingAs($owner)->post(route('cte.import.store'), ['xmls' => [$this->upload()]])->assertRedirect();

        // Motorista provisionado pelo CPF do XML (deduplicado por CPF).
        $this->assertDatabaseHas('drivers', [
            'company_id' => $company->getKey(),
            'cpf' => '09831473612',
            'name' => 'UELINTON CAROLINO DOS SANTOS',
        ]);

        $driver = Driver::withoutGlobalScopes()->where('cpf', '09831473612')->firstOrFail();
        $cte = CteDocument::withoutGlobalScopes()->firstOrFail();
        $entry = FinancialEntry::withoutGlobalScopes()->firstOrFail();

        // Vínculo por chave: CT-e e receita apontam para o mesmo motorista.
        $this->assertSame($driver->getKey(), (int) $cte->getAttribute('driver_id'));
        $this->assertSame($driver->getKey(), (int) $entry->getAttribute('driver_id'));
    }

    public function test_valor_da_receita_e_percentual_do_frete_da_contratante(): void
    {
        Storage::fake('local');
        [$owner, $company] = $this->createOwnerWithCompany();

        // A contratante (emitente) repassa 50% do frete ao agregado.
        app(TenantContext::class)->runFor($company, function (): void {
            BusinessPartner::query()->create([
                'document' => '17624719000520',
                'document_type' => 'cnpj',
                'legal_name' => 'HI TRANSPORTES LTDA',
                'kind' => BusinessPartnerKind::Contractor->value,
                'default_freight_share_percent' => 50,
                'active' => true,
            ]);
        });

        $this
            ->actingAs($owner)
            ->post(route('cte.import.store'), ['xmls' => [$this->upload()]])
            ->assertRedirect();

        $cte = CteDocument::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('50.00', $cte->getAttribute('applied_share_percent'));

        $entry = FinancialEntry::withoutGlobalScopes()->firstOrFail();
        // round(623889 * 50 / 100) = 311945 (metade arredondada).
        $this->assertSame(311945, (int) $entry->getAttribute('amount_cents'));
    }

    public function test_reimportar_o_mesmo_xml_e_idempotente(): void
    {
        Storage::fake('local');
        [$owner, $company] = $this->createOwnerWithCompany();

        $this->actingAs($owner)->post(route('cte.import.store'), ['xmls' => [$this->upload()]]);
        $this->actingAs($owner)->post(route('cte.import.store'), ['xmls' => [$this->upload()]]);

        $this->assertDatabaseCount('cte_documents', 1);
        $this->assertDatabaseCount('financial_entries', 1);
        $this->assertDatabaseCount('business_partners', 3);
        $this->assertDatabaseCount('cte_document_business_partner', 5);
    }

    public function test_cancelar_cte_cancela_o_lancamento(): void
    {
        Storage::fake('local');
        [$owner, $company] = $this->createOwnerWithCompany();

        $this->actingAs($owner)->post(route('cte.import.store'), ['xmls' => [$this->upload()]]);

        app(TenantContext::class)->runFor($company, function (): void {
            CteDocument::query()->firstOrFail()->delete();
        });

        $entry = FinancialEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(FinancialEntryStatus::Canceled, $entry->status);
    }

    public function test_importa_cte_semeando_plano_de_contas_para_empresa_legada(): void
    {
        Storage::fake('local');
        // Empresa legada: criada antes do plano de contas padrão existir.
        [$owner, $company] = $this->createOwnerWithCompany(seedCategories: false);

        $this
            ->actingAs($owner)
            ->post(route('cte.import.store'), ['xmls' => [$this->upload()]])
            ->assertRedirect();

        // O sincronizador semeou o plano de contas e lançou a receita mesmo assim.
        $this->assertDatabaseHas('financial_categories', [
            'company_id' => $company->getKey(),
            'code' => '1.1',
        ]);

        $entry = FinancialEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(FinancialEntryType::Revenue, $entry->type);
        $this->assertSame(623889, (int) $entry->getAttribute('amount_cents'));
    }

    /**
     * @return array{User, Company}
     */
    private function createOwnerWithCompany(bool $seedCategories = true): array
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

        if ($seedCategories) {
            app(SeedDefaultFinancialCategories::class)->execute($company);
        }

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
