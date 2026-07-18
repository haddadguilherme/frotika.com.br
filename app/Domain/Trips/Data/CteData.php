<?php

declare(strict_types=1);

namespace App\Domain\Trips\Data;

use App\Domain\Trips\Enums\CtePartyRole;
use App\Domain\Trips\Enums\CteServiceType;
use App\Domain\Trips\Enums\CteStatus;
use App\Domain\Trips\Enums\CteTakerRole;
use App\Domain\Trips\Enums\CteType;
use Carbon\CarbonImmutable;

final readonly class CteData
{
    public function __construct(
        public string $accessKey,
        public ?string $layoutVersion,
        public ?int $model,
        public int $number,
        public int $series,
        public CteType $cteType,
        public CteServiceType $serviceType,
        public ?CteTakerRole $takerRole,
        public ?string $modal,
        public ?string $cfop,
        public ?string $operationNature,
        public CarbonImmutable $issuedAt,
        public CtePartyData $issuer,
        public ?CtePartyData $sender,
        public ?CtePartyData $dispatcher,
        public ?CtePartyData $receiver,
        public ?CtePartyData $recipient,
        public ?string $originCity,
        public ?string $originState,
        public ?string $originIbge,
        public ?string $destinationCity,
        public ?string $destinationState,
        public ?string $destinationIbge,
        public int $totalValueCents,
        public int $receivableValueCents,
        public int $icmsValueCents,
        public ?int $cargoValueCents,
        public ?string $cargoWeightKg,
        public ?string $cargoDescription,
        public ?string $rntrc,
        public ?string $referencedKey,
        public CteStatus $status,
        public ?string $protocolNumber,
        public ?string $tractorPlate,
        public ?string $trailerPlate,
        public ?string $driverName,
        public ?string $driverCpf,
    ) {}

    /**
     * Partes do CT-e indexadas pelo papel, ignorando as ausentes.
     *
     * @return array<string, CtePartyData>
     */
    public function parties(): array
    {
        $parties = [CtePartyRole::Issuer->value => $this->issuer];

        if ($this->sender !== null) {
            $parties[CtePartyRole::Sender->value] = $this->sender;
        }

        if ($this->dispatcher !== null) {
            $parties[CtePartyRole::Dispatcher->value] = $this->dispatcher;
        }

        if ($this->receiver !== null) {
            $parties[CtePartyRole::Receiver->value] = $this->receiver;
        }

        if ($this->recipient !== null) {
            $parties[CtePartyRole::Recipient->value] = $this->recipient;
        }

        return $parties;
    }

    /**
     * Documento da parte que corresponde ao papel do tomador (para preencher
     * taker_document/taker_name no documento).
     */
    public function taker(): ?CtePartyData
    {
        return match ($this->takerRole) {
            CteTakerRole::Sender => $this->sender,
            CteTakerRole::Dispatcher => $this->dispatcher,
            CteTakerRole::Receiver => $this->receiver,
            CteTakerRole::Recipient => $this->recipient,
            default => null,
        };
    }

    /**
     * Snapshot normalizado para persistir em `cte_documents.raw`.
     *
     * @return array<string, mixed>
     */
    public function toRaw(): array
    {
        $parties = [];

        foreach ($this->parties() as $role => $party) {
            $parties[$role] = $party->toArray();
        }

        return [
            'access_key' => $this->accessKey,
            'layout_version' => $this->layoutVersion,
            'model' => $this->model,
            'number' => $this->number,
            'series' => $this->series,
            'cte_type' => $this->cteType->value,
            'service_type' => $this->serviceType->value,
            'taker_role' => $this->takerRole?->value,
            'modal' => $this->modal,
            'cfop' => $this->cfop,
            'operation_nature' => $this->operationNature,
            'issued_at' => $this->issuedAt->toIso8601String(),
            'parties' => $parties,
            'origin' => ['city' => $this->originCity, 'state' => $this->originState, 'ibge' => $this->originIbge],
            'destination' => ['city' => $this->destinationCity, 'state' => $this->destinationState, 'ibge' => $this->destinationIbge],
            'total_value_cents' => $this->totalValueCents,
            'receivable_value_cents' => $this->receivableValueCents,
            'icms_value_cents' => $this->icmsValueCents,
            'cargo_value_cents' => $this->cargoValueCents,
            'cargo_weight_kg' => $this->cargoWeightKg,
            'cargo_description' => $this->cargoDescription,
            'rntrc' => $this->rntrc,
            'referenced_key' => $this->referencedKey,
            'status' => $this->status->value,
            'protocol_number' => $this->protocolNumber,
            'tractor_plate' => $this->tractorPlate,
            'trailer_plate' => $this->trailerPlate,
            'driver_name' => $this->driverName,
            'driver_cpf' => $this->driverCpf,
        ];
    }
}
