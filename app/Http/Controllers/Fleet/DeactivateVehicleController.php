<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\DeactivateVehicle;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DeactivateVehicleController
{
    public function __invoke(Request $request, int $vehicle, DeactivateVehicle $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = Vehicle::query()->findOrFail($vehicle);
        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $action->execute($user, $company, $model);

        return redirect()
            ->route('vehicles.index')
            ->with('status', 'Veículo desativado.');
    }
}
