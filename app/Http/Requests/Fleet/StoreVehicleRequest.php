<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet;

use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Support\Facades\Gate;

final class StoreVehicleRequest extends VehicleRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Vehicle::class);
    }
}
