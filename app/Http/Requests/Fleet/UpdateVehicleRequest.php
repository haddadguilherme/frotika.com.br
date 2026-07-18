<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet;

use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Support\Facades\Gate;

final class UpdateVehicleRequest extends VehicleRequest
{
    public function authorize(): bool
    {
        $vehicle = Vehicle::query()->find($this->route('vehicle'));

        return $vehicle instanceof Vehicle && Gate::allows('update', $vehicle);
    }
}
