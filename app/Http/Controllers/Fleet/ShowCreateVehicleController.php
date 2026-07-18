<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowCreateVehicleController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('vehicles.create');
    }
}
