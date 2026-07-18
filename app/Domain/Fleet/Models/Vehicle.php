<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Models;

use App\Domain\Fleet\Enums\VehicleBodyType;
use App\Domain\Fleet\Enums\VehicleFuelType;
use App\Domain\Fleet\Enums\VehicleOwnership;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property VehicleType $type
 * @property VehicleStatus $status
 * @property VehicleOwnership $ownership
 * @property VehicleBodyType|null $body_type
 * @property VehicleFuelType|null $fuel_type
 */
final class Vehicle extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'status' => VehicleStatus::class,
            'ownership' => VehicleOwnership::class,
            'body_type' => VehicleBodyType::class,
            'fuel_type' => VehicleFuelType::class,
            'year_manufacture' => 'integer',
            'year_model' => 'integer',
            'axles' => 'integer',
            'tare_kg' => 'integer',
            'capacity_kg' => 'integer',
            'capacity_m3' => 'decimal:3',
            'tank_capacity_l' => 'integer',
            'odometer_initial' => 'integer',
            'odometer_current' => 'integer',
            'acquisition_date' => 'date',
            'acquisition_value_cents' => 'integer',
            'residual_value_cents' => 'integer',
            'depreciation_months' => 'integer',
            'provisioned' => 'boolean',
        ];
    }
}
