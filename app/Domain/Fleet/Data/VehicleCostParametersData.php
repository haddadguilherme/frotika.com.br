<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Data;

use App\Support\Money\Brl;

/**
 * Parâmetros de reserva de um veículo (ou o padrão da empresa, com
 * vehicle_id nulo). Todo campo é opcional: nulo significa "herda o padrão da
 * empresa" (ou zero, no próprio padrão). Reservas em R$/km; pró-labore em % da
 * receita líquida; salário do motorista em centavos/mês.
 */
final readonly class VehicleCostParametersData
{
    public function __construct(
        public ?float $oilReservePerKm = null,
        public ?float $tireReservePerKm = null,
        public ?float $prudentialReservePerKm = null,
        public ?int $driverSalaryCents = null,
        public ?float $prolaborePercent = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            oilReservePerKm: self::nullableFloat($data['oil_reserve_per_km'] ?? null),
            tireReservePerKm: self::nullableFloat($data['tire_reserve_per_km'] ?? null),
            prudentialReservePerKm: self::nullableFloat($data['prudential_reserve_per_km'] ?? null),
            driverSalaryCents: Brl::toCents(self::stringOrNull($data['driver_salary'] ?? null)),
            prolaborePercent: self::nullableFloat($data['prolabore_percent'] ?? null),
        );
    }

    /**
     * @return array<string, int|float|null>
     */
    public function toAttributes(): array
    {
        return [
            'oil_reserve_per_km' => $this->oilReservePerKm,
            'tire_reserve_per_km' => $this->tireReservePerKm,
            'prudential_reserve_per_km' => $this->prudentialReservePerKm,
            'driver_salary_cents' => $this->driverSalaryCents,
            'prolabore_percent' => $this->prolaborePercent,
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

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }
}
