<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reserves;

/**
 * Reservas e provisões de um veículo no período — a camada econômica do DRE
 * (decisão ADR-008). São valores de competência, não-caixa, calculados dos
 * parâmetros do veículo; não passam por financial_entries e não misturam com
 * as compras reais. O resultado é sempre em centavos negativos (custo), para
 * casar com a convenção do restante do DRE.
 *
 * Bases (confirmadas com o cliente):
 *  - óleo, pneus e prudencial = R$/km × km rodados (distância por hodômetro)
 *  - salário do motorista      = R$/mês × meses do período
 *  - pró-labore/retirada do dono = % da receita líquida (só quando positiva)
 */
final class VehicleReservesCalculator
{
    /**
     * @return array{
     *     oil_cents: int,
     *     tire_cents: int,
     *     prudential_cents: int,
     *     driver_salary_cents: int,
     *     prolabore_cents: int,
     *     total_cents: int
     * }
     */
    public function calculate(
        ReserveParameters $params,
        int $km,
        float $months,
        int $netRevenueCents,
    ): array {
        $oil = $km > 0 ? (int) round($params->oilReservePerKm * $km * 100) : 0;
        $tire = $km > 0 ? (int) round($params->tireReservePerKm * $km * 100) : 0;
        $prudential = $km > 0 ? (int) round($params->prudentialReservePerKm * $km * 100) : 0;

        $salary = $months > 0.0 ? (int) round($params->driverSalaryCents * $months) : 0;

        // Pró-labore só sobre receita positiva — não se retira sobre prejuízo.
        $prolabore = ($params->prolaborePercent > 0.0 && $netRevenueCents > 0)
            ? (int) round($netRevenueCents * ($params->prolaborePercent / 100))
            : 0;

        $total = $oil + $tire + $prudential + $salary + $prolabore;

        return [
            'oil_cents' => -$oil,
            'tire_cents' => -$tire,
            'prudential_cents' => -$prudential,
            'driver_salary_cents' => -$salary,
            'prolabore_cents' => -$prolabore,
            'total_cents' => -$total,
        ];
    }
}
