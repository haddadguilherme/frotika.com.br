<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fleet\Enums\VehicleBodyType;
use App\Domain\Fleet\Enums\VehicleFinancingType;
use App\Domain\Fleet\Enums\VehicleFuelType;
use App\Domain\Fleet\Enums\VehicleOwnership;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
final class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(VehicleType::cases());
        $isTrailerLike = $type !== VehicleType::Tractor;
        $isFinanced = fake()->boolean(35);

        return [
            'plate' => strtoupper(fake()->regexify('[A-Z]{3}[0-9][A-Z0-9][0-9]{2}')),
            'type' => $type->value,
            'status' => VehicleStatus::Active->value,
            'ownership' => fake()->randomElement(VehicleOwnership::cases())->value,
            'brand' => fake()->randomElement(['Scania', 'Volvo', 'DAF', 'Iveco', 'Mercedes-Benz']),
            'model' => fake()->randomElement(['R 450', 'FH 540', 'XF 480', 'S-Way', 'Actros 2651']),
            'year_manufacture' => fake()->numberBetween(2014, 2026),
            'year_model' => fake()->numberBetween(2015, 2027),
            'renavam' => fake()->numerify('###########'),
            'chassis' => strtoupper(fake()->bothify('9BWZZZ######?????')),
            'rntrc' => fake()->numerify('########'),
            'engine_number' => strtoupper(fake()->bothify('MTR-#####??')),
            'axles' => fake()->numberBetween(2, 9),
            'axle_distance_m' => fake()->randomFloat(2, 2.50, 8.50),
            'tire_count' => fake()->numberBetween(6, 22),
            'tire_size' => fake()->randomElement(['295/80R22.5', '275/80R22.5', '215/75R17.5']),
            'body_type' => $isTrailerLike ? fake()->randomElement(VehicleBodyType::cases())->value : null,
            'tare_kg' => fake()->numberBetween(6500, 12000),
            'capacity_kg' => fake()->numberBetween(12000, 35000),
            'capacity_m3' => $isTrailerLike ? fake()->randomFloat(3, 12, 85) : null,
            'fuel_type' => fake()->randomElement(VehicleFuelType::cases())->value,
            'tank_capacity_l' => fake()->numberBetween(250, 900),
            'odometer_initial' => fake()->numberBetween(120000, 980000),
            'odometer_current' => fake()->numberBetween(120000, 980000),
            'acquisition_date' => fake()->dateTimeBetween('-10 years', '-4 months')->format('Y-m-d'),
            'acquisition_value_cents' => fake()->numberBetween(22000000, 78000000),
            'crlv_due_at' => fake()->dateTimeBetween('-1 month', '+10 months')->format('Y-m-d'),
            'antt_due_at' => fake()->dateTimeBetween('-1 month', '+10 months')->format('Y-m-d'),
            'insurance_due_at' => fake()->dateTimeBetween('-1 month', '+10 months')->format('Y-m-d'),
            'is_financed' => $isFinanced,
            'financing_type' => $isFinanced ? fake()->randomElement(VehicleFinancingType::cases())->value : null,
            'creditor_name' => $isFinanced ? fake()->randomElement(['Banco do Brasil', 'Itaú BBA', 'Bradesco Financiamentos', 'Sicoob']) : null,
            'provisioned' => false,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
