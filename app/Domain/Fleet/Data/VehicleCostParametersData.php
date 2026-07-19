<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Data;

use App\Support\Money\Brl;

/**
 * Parâmetros de reserva de um veículo (ou o padrão da empresa, com
 * vehicle_id nulo). Todo campo é opcional: nulo significa "herda o padrão da
 * empresa" (ou zero, no próprio padrão).
 */
final readonly class VehicleCostParametersData
{
    public function __construct(
        public ?int $tireSetPriceCents = null,
        public ?int $tireLifeKm = null,
        public ?int $oilChangeCostCents = null,
        public ?int $oilIntervalKm = null,
        public ?float $prudentialPercent = null,
        public ?int $driverSalaryCents = null,
        public ?int $ownerProlaboreCents = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tireSetPriceCents: Brl::toCents(self::stringOrNull($data['tire_set_price'] ?? null)),
            tireLifeKm: self::nullableInt($data['tire_life_km'] ?? null),
            oilChangeCostCents: Brl::toCents(self::stringOrNull($data['oil_change_cost'] ?? null)),
            oilIntervalKm: self::nullableInt($data['oil_interval_km'] ?? null),
            prudentialPercent: self::nullableFloat($data['prudential_percent'] ?? null),
            driverSalaryCents: Brl::toCents(self::stringOrNull($data['driver_salary'] ?? null)),
            ownerProlaboreCents: Brl::toCents(self::stringOrNull($data['owner_prolabore'] ?? null)),
        );
    }

    /**
     * @return array<string, int|float|null>
     */
    public function toAttributes(): array
    {
        return [
            'tire_set_price_cents' => $this->tireSetPriceCents,
            'tire_life_km' => $this->tireLifeKm,
            'oil_change_cost_cents' => $this->oilChangeCostCents,
            'oil_interval_km' => $this->oilIntervalKm,
            'prudential_percent' => $this->prudentialPercent,
            'driver_salary_cents' => $this->driverSalaryCents,
            'owner_prolabore_cents' => $this->ownerProlaboreCents,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
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

        return (float) str_replace(',', '.', (string) $value);
    }
}
