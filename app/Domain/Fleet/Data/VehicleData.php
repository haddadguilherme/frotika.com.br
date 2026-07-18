<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Data;

use App\Domain\Fleet\Enums\VehicleBodyType;
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
        public ?int $axles = null,
        public ?VehicleBodyType $bodyType = null,
        public ?int $tareKg = null,
        public ?int $capacityKg = null,
        public ?float $capacityM3 = null,
        public ?VehicleFuelType $fuelType = null,
        public ?int $tankCapacityL = null,
        public int $odometerInitial = 0,
        public ?string $acquisitionDate = null,
        public ?int $acquisitionValueCents = null,
        public ?int $residualValueCents = null,
        public ?int $depreciationMonths = null,
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
            axles: self::nullableInt($data['axles'] ?? null),
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
            residualValueCents: self::nullableInt($data['residual_value_cents'] ?? null),
            depreciationMonths: self::nullableInt($data['depreciation_months'] ?? null),
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
            'axles' => $this->axles,
            'body_type' => $this->bodyType?->value,
            'tare_kg' => $this->tareKg,
            'capacity_kg' => $this->capacityKg,
            'capacity_m3' => $this->capacityM3,
            'fuel_type' => $this->fuelType?->value,
            'tank_capacity_l' => $this->tankCapacityL,
            'odometer_initial' => $this->odometerInitial,
            'acquisition_date' => $this->acquisitionDate,
            'acquisition_value_cents' => $this->acquisitionValueCents,
            'residual_value_cents' => $this->residualValueCents,
            'depreciation_months' => $this->depreciationMonths,
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

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
