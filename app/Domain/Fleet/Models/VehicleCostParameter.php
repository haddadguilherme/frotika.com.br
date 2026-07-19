<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

final class VehicleCostParameter extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vehicle_id' => 'integer',
            'tire_set_price_cents' => 'integer',
            'tire_life_km' => 'integer',
            'oil_change_cost_cents' => 'integer',
            'oil_interval_km' => 'integer',
            'prudential_percent' => 'decimal:2',
            'driver_salary_cents' => 'integer',
            'owner_prolabore_cents' => 'integer',
        ];
    }
}
