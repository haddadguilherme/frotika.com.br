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
            oilReservePerKm: 0.07,        // R$ 0,07/km
            tireReservePerKm: 0.2513,     // R$ 0,2513/km
            prudentialReservePerKm: 0.20, // R$ 0,20/km
            driverSalaryCents: 300_000,   // R$ 3.000/mês
            prolaborePercent: 5.0,        // 5% da receita líquida
        );

        $result = (new VehicleReservesCalculator)->calculate(
            $params,
            km: 10_000,
            months: 1.0,
            netRevenueCents: 1_000_000,
        );

        $this->assertSame(-70_000, $result['oil_cents']);
        $this->assertSame(-251_300, $result['tire_cents']);
        $this->assertSame(-200_000, $result['prudential_cents']);
        $this->assertSame(-300_000, $result['driver_salary_cents']);
        $this->assertSame(-50_000, $result['prolabore_cents']);
        $this->assertSame(-871_300, $result['total_cents']);
    }

    public function test_sem_km_nao_reserva_pneu_oleo_nem_prudencial(): void
    {
        $params = new ReserveParameters(
            oilReservePerKm: 0.07,
            tireReservePerKm: 0.2513,
            prudentialReservePerKm: 0.20,
        );

        $result = (new VehicleReservesCalculator)->calculate($params, km: 0, months: 1.0, netRevenueCents: 500_000);

        $this->assertSame(0, $result['oil_cents']);
        $this->assertSame(0, $result['tire_cents']);
        $this->assertSame(0, $result['prudential_cents']);
        $this->assertSame(0, $result['total_cents']);
    }

    public function test_prolabore_zero_sobre_receita_nao_positiva(): void
    {
        $params = new ReserveParameters(prolaborePercent: 10.0);

        $prejuizo = (new VehicleReservesCalculator)->calculate($params, km: 5_000, months: 1.0, netRevenueCents: -100_000);
        $this->assertSame(0, $prejuizo['prolabore_cents']);

        $lucro = (new VehicleReservesCalculator)->calculate($params, km: 5_000, months: 1.0, netRevenueCents: 100_000);
        $this->assertSame(-10_000, $lucro['prolabore_cents']);
    }

    public function test_salario_e_proporcional_aos_meses(): void
    {
        $params = new ReserveParameters(driverSalaryCents: 300_000);

        $result = (new VehicleReservesCalculator)->calculate($params, km: 0, months: 0.5, netRevenueCents: 0);

        $this->assertSame(-150_000, $result['driver_salary_cents']);
        $this->assertSame(-150_000, $result['total_cents']);
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
