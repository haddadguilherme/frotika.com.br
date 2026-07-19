<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reserves;

use App\Domain\Fleet\Models\VehicleCostParameter;

/**
 * Parâmetros de reserva já resolvidos para um veículo: o valor do próprio
 * veículo quando existe, senão o padrão da empresa, senão zero. Não há
 * lógica de reserva aqui — só o merge veículo↔empresa que o calculador consome.
 * Reservas em R$/km; pró-labore em % da receita; salário em centavos/mês.
 */
final readonly class ReserveParameters
{
    public function __construct(
        public float $oilReservePerKm = 0.0,
        public float $tireReservePerKm = 0.0,
        public float $prudentialReservePerKm = 0.0,
        public int $driverSalaryCents = 0,
        public float $prolaborePercent = 0.0,
    ) {}

    /**
     * Merge campo a campo: o override do veículo tem prioridade; um campo nulo
     * no veículo cai no padrão da empresa; ausência dos dois vira zero.
     */
    public static function resolve(?VehicleCostParameter $vehicle, ?VehicleCostParameter $default): self
    {
        return new self(
            oilReservePerKm: self::floatField($vehicle, $default, 'oil_reserve_per_km'),
            tireReservePerKm: self::floatField($vehicle, $default, 'tire_reserve_per_km'),
            prudentialReservePerKm: self::floatField($vehicle, $default, 'prudential_reserve_per_km'),
            driverSalaryCents: self::intField($vehicle, $default, 'driver_salary_cents'),
            prolaborePercent: self::floatField($vehicle, $default, 'prolabore_percent'),
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
