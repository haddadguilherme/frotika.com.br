<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reserves;

use App\Domain\Fleet\Models\VehicleCostParameter;

/**
 * Parâmetros de reserva já resolvidos para um veículo: o valor do próprio
 * veículo quando existe, senão o padrão da empresa, senão zero. Não há
 * lógica de reserva aqui — só o merge veículo↔empresa que o calculador consome.
 */
final readonly class ReserveParameters
{
    public function __construct(
        public int $tireSetPriceCents = 0,
        public int $tireLifeKm = 0,
        public int $oilChangeCostCents = 0,
        public int $oilIntervalKm = 0,
        public float $prudentialPercent = 0.0,
        public int $driverSalaryCents = 0,
        public int $ownerProlaboreCents = 0,
    ) {}

    /**
     * Merge campo a campo: o override do veículo tem prioridade; um campo nulo
     * no veículo cai no padrão da empresa; ausência dos dois vira zero.
     */
    public static function resolve(?VehicleCostParameter $vehicle, ?VehicleCostParameter $default): self
    {
        return new self(
            tireSetPriceCents: self::intField($vehicle, $default, 'tire_set_price_cents'),
            tireLifeKm: self::intField($vehicle, $default, 'tire_life_km'),
            oilChangeCostCents: self::intField($vehicle, $default, 'oil_change_cost_cents'),
            oilIntervalKm: self::intField($vehicle, $default, 'oil_interval_km'),
            prudentialPercent: self::floatField($vehicle, $default, 'prudential_percent'),
            driverSalaryCents: self::intField($vehicle, $default, 'driver_salary_cents'),
            ownerProlaboreCents: self::intField($vehicle, $default, 'owner_prolabore_cents'),
        );
    }

    private static function intField(?VehicleCostParameter $vehicle, ?VehicleCostParameter $default, string $field): int
    {
        $value = $vehicle?->getAttribute($field) ?? $default?->getAttribute($field);

        return $value === null ? 0 : (int) $value;
    }

    private static function floatField(?VehicleCostParameter $vehicle, ?VehicleCostParameter $default, string $field): float
    {
        $value = $vehicle?->getAttribute($field) ?? $default?->getAttribute($field);

        return $value === null ? 0.0 : (float) $value;
    }
}
