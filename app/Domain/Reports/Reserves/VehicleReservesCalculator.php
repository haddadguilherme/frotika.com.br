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
 *  - pneu   = preço do jogo ÷ vida útil (R$/km) × km rodados
 *  - óleo   = custo da troca ÷ intervalo (R$/km) × km rodados
 *  - prudencial = % da receita líquida (só quando positiva)
 *  - salário do motorista = R$/mês × meses do período
 *  - pró-labore do dono   = R$/mês × meses do período
 */
final class VehicleReservesCalculator
{
    /**
     * @return array{
     *     oil_cents: int,
     *     tire_cents: int,
     *     prudential_cents: int,
     *     driver_salary_cents: int,
     *     owner_prolabore_cents: int,
     *     total_cents: int
     * }
     */
    public function calculate(
        ReserveParameters $params,
        int $km,
        float $months,
        int $netRevenueCents,
    ): array {
        $tire = ($params->tireLifeKm > 0 && $km > 0)
            ? (int) round(($params->tireSetPriceCents / $params->tireLifeKm) * $km)
            : 0;

        $oil = ($params->oilIntervalKm > 0 && $km > 0)
            ? (int) round(($params->oilChangeCostCents / $params->oilIntervalKm) * $km)
            : 0;

        // Prudencial só sobre receita positiva — não se reserva sobre prejuízo.
        $prudential = ($params->prudentialPercent > 0.0 && $netRevenueCents > 0)
            ? (int) round($netRevenueCents * ($params->prudentialPercent / 100))
            : 0;

        $salary = $months > 0.0 ? (int) round($params->driverSalaryCents * $months) : 0;
        $prolabore = $months > 0.0 ? (int) round($params->ownerProlaboreCents * $months) : 0;

        $total = $tire + $oil + $prudential + $salary + $prolabore;

        return [
            'oil_cents' => -$oil,
            'tire_cents' => -$tire,
            'prudential_cents' => -$prudential,
            'driver_salary_cents' => -$salary,
            'owner_prolabore_cents' => -$prolabore,
            'total_cents' => -$total,
        ];
    }
}
