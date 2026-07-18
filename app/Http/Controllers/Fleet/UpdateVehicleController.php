<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\UpdateVehicle;
use App\Domain\Fleet\Data\VehicleData;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Fleet\UpdateVehicleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class UpdateVehicleController
{
    public function __invoke(UpdateVehicleRequest $request, int $vehicle, UpdateVehicle $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = Vehicle::query()->findOrFail($vehicle);
        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $action->execute($user, $company, $model, VehicleData::fromArray($request->validated()));

        return redirect()
            ->route('vehicles.show', ['vehicle' => $model->getKey()])
            ->with('status', 'Veículo atualizado com sucesso.');
    }
}
