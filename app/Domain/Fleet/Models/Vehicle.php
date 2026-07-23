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

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plate',
        'type',
        'status',
        'ownership',
        'brand',
        'model',
        'year_manufacture',
        'year_model',
        'renavam',
        'chassis',
        'rntrc',
        'axles',
        'body_type',
        'tare_kg',
        'capacity_kg',
        'capacity_m3',
        'fuel_type',
        'tank_capacity_l',
        'odometer_initial',
        'acquisition_date',
        'acquisition_value_cents',
        'notes',
    ];

    public function hasMinimumRegistrationData(): bool
    {
        return trim((string) $this->getAttribute('brand')) !== ''
            && trim((string) $this->getAttribute('model')) !== ''
            && $this->getAttribute('type') !== null;
    }

    public function markAsComplete(): void
    {
        $this->setAttribute('provisioned', false);
        $this->save();
    }

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
            'provisioned' => 'boolean',
        ];
    }
}
