<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowEditVehicleController
{
    public function __invoke(Request $request, int $vehicle): View
    {
        $model = Vehicle::query()->findOrFail($vehicle);

        Gate::authorize('update', $model);

        return view('vehicles.edit', [
            'vehicle' => $model,
        ]);
    }
}
