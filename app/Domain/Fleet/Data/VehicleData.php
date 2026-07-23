<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Data;

use App\Domain\Fleet\Enums\VehicleBodyType;
use App\Domain\Fleet\Enums\VehicleFinancingType;
use App\Domain\Fleet\Enums\VehicleFuelType;
use App\Domain\Fleet\Enums\VehicleOwnership;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;

final readonly class VehicleData
{
    public function __construct(
        public string $plate,
        public VehicleType $type,
        public VehicleStatus $status,
        public VehicleOwnership $ownership,
        public ?string $brand = null,
        public ?string $model = null,
        public ?int $yearManufacture = null,
        public ?int $yearModel = null,
        public ?string $renavam = null,
        public ?string $chassis = null,
        public ?string $rntrc = null,
        public ?string $engineNumber = null,
        public ?int $axles = null,
        public ?float $axleDistanceM = null,
        public ?int $tireCount = null,
        public ?string $tireSize = null,
        public ?VehicleBodyType $bodyType = null,
        public ?int $tareKg = null,
        public ?int $capacityKg = null,
        public ?float $capacityM3 = null,
        public ?VehicleFuelType $fuelType = null,
        public ?int $tankCapacityL = null,
        public int $odometerInitial = 0,
        public ?string $acquisitionDate = null,
        public ?int $acquisitionValueCents = null,
        public ?string $crlvDueAt = null,
        public ?string $anttDueAt = null,
        public ?string $insuranceDueAt = null,
        public bool $isFinanced = false,
        public ?VehicleFinancingType $financingType = null,
        public ?string $creditorName = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            plate: (string) $data['plate'],
            type: VehicleType::from((string) $data['type']),
            status: VehicleStatus::from((string) $data['status']),
            ownership: VehicleOwnership::from((string) $data['ownership']),
            brand: $data['brand'] ?? null,
            model: $data['model'] ?? null,
            yearManufacture: self::nullableInt($data['year_manufacture'] ?? null),
            yearModel: self::nullableInt($data['year_model'] ?? null),
            renavam: $data['renavam'] ?? null,
            chassis: $data['chassis'] ?? null,
            rntrc: $data['rntrc'] ?? null,
            engineNumber: self::nullableString($data['engine_number'] ?? null),
            axles: self::nullableInt($data['axles'] ?? null),
            axleDistanceM: self::nullableFloat($data['axle_distance_m'] ?? null),
            tireCount: self::nullableInt($data['tire_count'] ?? null),
            tireSize: self::nullableString($data['tire_size'] ?? null),
            bodyType: isset($data['body_type']) && $data['body_type'] !== ''
                ? VehicleBodyType::from((string) $data['body_type'])
                : null,
            tareKg: self::nullableInt($data['tare_kg'] ?? null),
            capacityKg: self::nullableInt($data['capacity_kg'] ?? null),
            capacityM3: self::nullableFloat($data['capacity_m3'] ?? null),
            fuelType: isset($data['fuel_type']) && $data['fuel_type'] !== ''
                ? VehicleFuelType::from((string) $data['fuel_type'])
                : null,
            tankCapacityL: self::nullableInt($data['tank_capacity_l'] ?? null),
            odometerInitial: self::nullableInt($data['odometer_initial'] ?? null) ?? 0,
            acquisitionDate: self::nullableString($data['acquisition_date'] ?? null),
            acquisitionValueCents: self::nullableInt($data['acquisition_value_cents'] ?? null),
            crlvDueAt: self::nullableString($data['crlv_due_at'] ?? null),
            anttDueAt: self::nullableString($data['antt_due_at'] ?? null),
            insuranceDueAt: self::nullableString($data['insurance_due_at'] ?? null),
            isFinanced: self::nullableBool($data['is_financed'] ?? null) ?? false,
            financingType: isset($data['financing_type']) && $data['financing_type'] !== ''
                ? VehicleFinancingType::from((string) $data['financing_type'])
                : null,
            creditorName: self::nullableString($data['creditor_name'] ?? null),
            notes: self::nullableString($data['notes'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'plate' => $this->plate,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'ownership' => $this->ownership->value,
            'brand' => $this->brand,
            'model' => $this->model,
            'year_manufacture' => $this->yearManufacture,
            'year_model' => $this->yearModel,
            'renavam' => $this->renavam,
            'chassis' => $this->chassis,
            'rntrc' => $this->rntrc,
            'engine_number' => $this->engineNumber,
            'axles' => $this->axles,
            'axle_distance_m' => $this->axleDistanceM,
            'tire_count' => $this->tireCount,
            'tire_size' => $this->tireSize,
            'body_type' => $this->bodyType?->value,
            'tare_kg' => $this->tareKg,
            'capacity_kg' => $this->capacityKg,
            'capacity_m3' => $this->capacityM3,
            'fuel_type' => $this->fuelType?->value,
            'tank_capacity_l' => $this->tankCapacityL,
            'odometer_initial' => $this->odometerInitial,
            'acquisition_date' => $this->acquisitionDate,
            'acquisition_value_cents' => $this->acquisitionValueCents,
            'crlv_due_at' => $this->crlvDueAt,
            'antt_due_at' => $this->anttDueAt,
            'insurance_due_at' => $this->insuranceDueAt,
            'is_financed' => $this->isFinanced,
            'financing_type' => $this->financingType?->value,
            'creditor_name' => $this->creditorName,
            'notes' => $this->notes,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private static function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
