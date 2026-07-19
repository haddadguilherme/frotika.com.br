<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

final class VehicleOdometerReading extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vehicle_id' => 'integer',
            'read_on' => 'date',
            'odometer' => 'integer',
        ];
    }
}
