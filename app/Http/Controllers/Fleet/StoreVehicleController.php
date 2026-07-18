<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\CreateVehicle;
use App\Domain\Fleet\Data\VehicleData;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Fleet\StoreVehicleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class StoreVehicleController
{
    public function __invoke(StoreVehicleRequest $request, CreateVehicle $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de cadastrar veículos.');
        }

        $vehicle = $action->execute($user, $company, VehicleData::fromArray($request->validated()));

        return redirect()
            ->route('vehicles.show', ['vehicle' => $vehicle->getKey()])
            ->with('status', 'Veículo cadastrado com sucesso.');
    }
}
