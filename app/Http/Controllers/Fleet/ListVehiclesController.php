<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fleet\Models\Vehicle;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ListVehiclesController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Vehicle::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $typeFilter = VehicleType::tryFrom((string) $request->query('type', ''));
        $statusFilter = VehicleStatus::tryFrom((string) $request->query('status', ''));
        $onlyProvisioned = $request->boolean('provisioned');

        $provisionedCount = Vehicle::query()
            ->where('provisioned', true)
            ->count();

        $vehicles = Vehicle::query()
            ->when($onlyProvisioned, fn ($query) => $query->where('provisioned', true))
            ->when($typeFilter !== null, fn ($query) => $query->where('type', $typeFilter?->value))
            ->when($statusFilter !== null, fn ($query) => $query->where('status', $statusFilter?->value))
            ->orderBy('plate')
            ->get(['id', 'plate', 'type', 'status', 'brand', 'model', 'year_model', 'ownership', 'provisioned']);

        return view('vehicles.index', [
            'vehicles' => $vehicles,
            'canManage' => Gate::allows('create', Vehicle::class),
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'onlyProvisioned' => $onlyProvisioned,
            'provisionedCount' => $provisionedCount,
        ]);
    }
}
