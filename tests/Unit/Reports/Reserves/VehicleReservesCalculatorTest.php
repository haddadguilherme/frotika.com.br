<?php

declare(strict_types=1);

namespace Tests\Unit\Reports\Reserves;

use App\Domain\Reports\Reserves\ReserveParameters;
use App\Domain\Reports\Reserves\VehicleReservesCalculator;
use PHPUnit\Framework\TestCase;

final class VehicleReservesCalculatorTest extends TestCase
{
    public function test_calcula_todas_as_reservas_em_centavos_negativos(): void
    {
        $params = new ReserveParameters(
            tireSetPriceCents: 800_000, // R$ 8.000 o jogo
            tireLifeKm: 100_000,        // 8 centavos/km
            oilChangeCostCents: 60_000, // R$ 600 a troca
            oilIntervalKm: 15_000,      // 4 centavos/km
            prudentialPercent: 5.0,
            driverSalaryCents: 300_000, // R$ 3.000/mês
            ownerProlaboreCents: 200_000, // R$ 2.000/mês
        );

        $result = (new VehicleReservesCalculator)->calculate(
            $params,
            km: 10_000,
            months: 1.0,
            netRevenueCents: 1_000_000,
        );

        $this->assertSame(-80_000, $result['tire_cents']);
        $this->assertSame(-40_000, $result['oil_cents']);
        $this->assertSame(-50_000, $result['prudential_cents']);
        $this->assertSame(-300_000, $result['driver_salary_cents']);
        $this->assertSame(-200_000, $result['owner_prolabore_cents']);
        $this->assertSame(-670_000, $result['total_cents']);
    }

    public function test_sem_km_nao_reserva_pneu_nem_oleo(): void
    {
        $params = new ReserveParameters(
            tireSetPriceCents: 800_000,
            tireLifeKm: 100_000,
            oilChangeCostCents: 60_000,
            oilIntervalKm: 15_000,
        );

        $result = (new VehicleReservesCalculator)->calculate($params, km: 0, months: 1.0, netRevenueCents: 500_000);

        $this->assertSame(0, $result['tire_cents']);
        $this->assertSame(0, $result['oil_cents']);
        $this->assertSame(0, $result['total_cents']);
    }

    public function test_prudencial_zero_sobre_receita_nao_positiva(): void
    {
        $params = new ReserveParameters(prudentialPercent: 10.0);

        $prejuizo = (new VehicleReservesCalculator)->calculate($params, km: 5_000, months: 1.0, netRevenueCents: -100_000);
        $this->assertSame(0, $prejuizo['prudential_cents']);

        $lucro = (new VehicleReservesCalculator)->calculate($params, km: 5_000, months: 1.0, netRevenueCents: 100_000);
        $this->assertSame(-10_000, $lucro['prudential_cents']);
    }

    public function test_provisoes_mensais_sao_proporcionais_aos_meses(): void
    {
        $params = new ReserveParameters(
            driverSalaryCents: 300_000,
            ownerProlaboreCents: 200_000,
        );

        $result = (new VehicleReservesCalculator)->calculate($params, km: 0, months: 0.5, netRevenueCents: 0);

        $this->assertSame(-150_000, $result['driver_salary_cents']);
        $this->assertSame(-100_000, $result['owner_prolabore_cents']);
        $this->assertSame(-250_000, $result['total_cents']);
    }

    public function test_sem_parametros_nao_gera_reserva(): void
    {
        $result = (new VehicleReservesCalculator)->calculate(
            new ReserveParameters,
            km: 10_000,
            months: 1.0,
            netRevenueCents: 1_000_000,
        );

        $this->assertSame(0, $result['total_cents']);
    }
}
