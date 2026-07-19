<?php

declare(strict_types=1);

namespace Tests\Feature\Trips;

use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Finance\Actions\SeedDefaultFinancialCategories;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Enums\CteImportBatchStatus;
use App\Domain\Trips\Enums\CteImportItemStatus;
use App\Domain\Trips\Events\CteBulkImportCompleted;
use App\Domain\Trips\Models\CteImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BulkImportCteTest extends TestCase
{
    use RefreshDatabase;

    private function xml(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/Cte/cte-hi-transportes.xml'));
    }

    private function validUpload(string $name = 'cte.xml'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->xml());
    }

    private function invalidUpload(string $name = 'nota-fiscal.xml'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, '<?xml version="1.0"?><NFe><infNFe></infNFe></NFe>');
    }

    public function test_lote_com_multiplos_arquivos_e_processado_e_notifica_ao_concluir(): void
    {
        Storage::fake('local');
        Event::fake([CteBulkImportCompleted::class]);
        [$owner] = $this->createOwnerWithCompany();

        $response = $this
            ->actingAs($owner)
            ->post(route('cte.import.store'), ['xmls' => [$this->validUpload('a.xml'), $this->validUpload('b.xml')]]);

        $batch = CteImportBatch::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('cte.import.result', ['batch' => $batch->getAttribute('uuid')]));

        // Fila sync roda os dois jobs na hora: lote concluído com dois processados.
        $this->assertSame(CteImportBatchStatus::Completed, $batch->status);
        $this->assertSame(2, $batch->total_files);
        $this->assertSame(2, $batch->processed_files);
        $this->assertSame(2, $batch->imported_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertCount(2, $batch->results);

        // Mesmo XML é idempotente: dois arquivos iguais viram um único CT-e.
        $this->assertDatabaseCount('cte_documents', 1);

        Event::assertDispatched(CteBulkImportCompleted::class, function (CteBulkImportCompleted $event) use ($owner, $batch): bool {
            return $event->userId === (int) $owner->getKey()
                && $event->uuid === (string) $batch->getAttribute('uuid')
                && $event->imported === 2
                && $event->failed === 0;
        });
    }

    public function test_arquivo_invalido_nao_derruba_os_demais(): void
    {
        Storage::fake('local');
        Event::fake([CteBulkImportCompleted::class]);
        [$owner] = $this->createOwnerWithCompany();

        $this
            ->actingAs($owner)
            ->post(route('cte.import.store'), ['xmls' => [$this->validUpload('bom.xml'), $this->invalidUpload('ruim.xml')]])
            ->assertRedirect();

        $batch = CteImportBatch::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(CteImportBatchStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->imported_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertDatabaseCount('cte_documents', 1);

        $statuses = array_column($batch->results, 'status', 'file');
        $this->assertSame(CteImportItemStatus::Imported->value, $statuses['bom.xml']);
        $this->assertSame(CteImportItemStatus::Failed->value, $statuses['ruim.xml']);

        Event::assertDispatched(CteBulkImportCompleted::class);
    }

    public function test_lote_so_com_invalidos_conclui_e_notifica_sem_criar_cte(): void
    {
        Storage::fake('local');
        Event::fake([CteBulkImportCompleted::class]);
        [$owner] = $this->createOwnerWithCompany();

        $this
            ->actingAs($owner)
            ->post(route('cte.import.store'), ['xmls' => [$this->invalidUpload('x.xml')]])
            ->assertRedirect();

        $batch = CteImportBatch::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(CteImportBatchStatus::Completed, $batch->status);
        $this->assertSame(0, $batch->imported_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertDatabaseCount('cte_documents', 0);

        Event::assertDispatched(CteBulkImportCompleted::class);
    }

    public function test_recusa_mais_de_vinte_arquivos(): void
    {
        Storage::fake('local');
        [$owner] = $this->createOwnerWithCompany();

        $files = [];
        for ($i = 0; $i < 21; $i++) {
            $files[] = $this->validUpload("cte-{$i}.xml");
        }

        $this
            ->actingAs($owner)
            ->post(route('cte.import.store'), ['xmls' => $files])
            ->assertSessionHasErrors(['xmls']);

        $this->assertDatabaseCount('cte_import_batches', 0);
        $this->assertDatabaseCount('cte_documents', 0);
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
