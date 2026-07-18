<?php

declare(strict_types=1);

namespace App\Domain\Trips\Actions;

use App\Domain\Fleet\Actions\ProvisionVehicleByPlate;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Partners\Actions\UpsertBusinessPartner;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Cte\CteParser;
use App\Domain\Trips\Data\CteData;
use App\Domain\Trips\Enums\CtePartyRole;
use App\Domain\Trips\Models\CteDocument;
use App\Models\User;
use App\Support\Cnpj\Cnpj;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Importa um XML de CT-e: cadastra os parceiros (emitente = contratante),
 * provisiona os veículos por placa, guarda o XML privado por grupo e grava o
 * documento — que dispara a receita no fluxo via observer. Sem bloqueio por
 * emitente: a empresa do sistema costuma ser o agregado subcontratado
 * (ADR-004), então o vínculo é pelo upload + placa, não pelo CNPJ do emit.
 */
final class ImportCte
{
    public function __construct(
        private readonly CteParser $parser,
        private readonly UpsertBusinessPartner $upsertBusinessPartner,
        private readonly ProvisionVehicleByPlate $provisionVehicleByPlate,
    ) {}

    public function execute(User $actor, Company $company, string $xml, ?string $originalName = null): CteDocument
    {
        Gate::forUser($actor)->authorize('create', CteDocument::class);

        $data = $this->parser->parse($xml);

        return DB::transaction(function () use ($actor, $company, $data, $xml): CteDocument {
            $partners = $this->syncPartners($company, $data);

            $percent = $this->resolveSharePercent($company, $data, $partners);

            $tractor = $this->provisionVehicleByPlate->execute($company, $data->tractorPlate, VehicleType::Tractor);
            $trailer = $this->provisionVehicleByPlate->execute($company, $data->trailerPlate, VehicleType::SemiTrailer);

            $xmlPath = $this->storeXml($company, $data, $xml);

            $taker = $data->taker();

            /** @var CteDocument $cte */
            $cte = CteDocument::query()->updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'access_key' => $data->accessKey,
                ],
                [
                    'layout_version' => $data->layoutVersion,
                    'model' => $data->model,
                    'number' => $data->number,
                    'series' => $data->series,
                    'cte_type' => $data->cteType->value,
                    'service_type' => $data->serviceType->value,
                    'modal' => $data->modal,
                    'cfop' => $data->cfop,
                    'operation_nature' => $data->operationNature,
                    'issued_at' => $data->issuedAt,
                    'issuer_document' => $data->issuer->document,
                    'issuer_name' => $data->issuer->legalName,
                    'origin_city' => $data->originCity,
                    'origin_state' => $data->originState,
                    'origin_ibge' => $data->originIbge,
                    'destination_city' => $data->destinationCity,
                    'destination_state' => $data->destinationState,
                    'destination_ibge' => $data->destinationIbge,
                    'taker_role' => $data->takerRole?->value,
                    'taker_document' => $taker?->document,
                    'taker_name' => $taker?->legalName,
                    'sender_document' => $data->sender?->document,
                    'sender_name' => $data->sender?->legalName,
                    'recipient_document' => $data->recipient?->document,
                    'recipient_name' => $data->recipient?->legalName,
                    'total_value_cents' => $data->totalValueCents,
                    'receivable_value_cents' => $data->receivableValueCents,
                    'icms_value_cents' => $data->icmsValueCents,
                    'cargo_value_cents' => $data->cargoValueCents,
                    'cargo_weight_kg' => $data->cargoWeightKg,
                    'cargo_description' => $data->cargoDescription,
                    'applied_share_percent' => $percent,
                    'rntrc' => $data->rntrc,
                    'referenced_key' => $data->referencedKey,
                    'status' => $data->status->value,
                    'protocol_number' => $data->protocolNumber,
                    'vehicle_id' => $tractor?->getKey(),
                    'trailer_vehicle_id' => $trailer?->getKey(),
                    'driver_name' => $data->driverName,
                    'driver_cpf' => $data->driverCpf,
                    'xml_path' => $xmlPath,
                    'xml_hash' => hash('sha256', $xml),
                    'raw' => $data->toRaw(),
                    'imported_by' => $actor->getKey(),
                    'imported_at' => now(),
                ],
            );

            $this->syncPartnerRoles($cte, $data, $partners);

            return $cte;
        });
    }

    /**
     * @return array<string, BusinessPartner>
     */
    private function syncPartners(Company $company, CteData $data): array
    {
        $partners = [];

        foreach ($data->parties() as $role => $party) {
            $partners[$role] = $this->upsertBusinessPartner->fromParty(
                $company,
                $party,
                $this->kindForRole($role),
            );
        }

        return $partners;
    }

    /**
     * @param  array<string, BusinessPartner>  $partners
     */
    private function syncPartnerRoles(CteDocument $cte, CteData $data, array $partners): void
    {
        $cte->partners()->detach();

        foreach (array_keys($data->parties()) as $role) {
            $partner = $partners[$role] ?? null;

            if ($partner instanceof BusinessPartner) {
                $cte->partners()->attach($partner->getKey(), ['role' => $role]);
            }
        }
    }

    private function kindForRole(string $role): BusinessPartnerKind
    {
        return $role === CtePartyRole::Issuer->value
            ? BusinessPartnerKind::Contractor
            : BusinessPartnerKind::Customer;
    }

    /**
     * Percentual do frete que a empresa (agregado) recebe. Se a empresa é a
     * própria emitente, é transporte direto = 100%. Caso contrário, usa o
     * percentual cadastrado na contratante (emitente) ou o padrão da config.
     *
     * @param  array<string, BusinessPartner>  $partners
     */
    private function resolveSharePercent(Company $company, CteData $data, array $partners): float
    {
        $companyCnpj = Cnpj::digits((string) $company->getAttribute('cnpj'));

        if ($data->issuer->document !== null && $data->issuer->document === $companyCnpj) {
            return 100.0;
        }

        $contractor = $partners[CtePartyRole::Issuer->value] ?? null;
        $percent = $contractor?->getAttribute('default_freight_share_percent');

        if ($percent !== null) {
            return (float) $percent;
        }

        return (float) config('cte.default_freight_share_percent', 100);
    }

    private function storeXml(Company $company, CteData $data, string $xml): string
    {
        $group = Group::query()->find($company->getAttribute('group_id'));
        $groupUuid = $group?->getAttribute('uuid') ?? 'sem-grupo';

        $path = sprintf(
            'grupos/%s/cte/%s/%s/%s.xml',
            $groupUuid,
            $data->issuedAt->format('Y'),
            $data->issuedAt->format('m'),
            $data->accessKey,
        );

        Storage::disk($this->disk())->put($path, $xml);

        return $path;
    }

    private function disk(): string
    {
        return (string) config('cte.storage_disk', 'local');
    }
}
