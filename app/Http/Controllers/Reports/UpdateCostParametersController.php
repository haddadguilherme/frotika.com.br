<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Fleet\Actions\SaveVehicleCostParameters;
use App\Domain\Fleet\Data\VehicleCostParametersData;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Reports\SaveCostParametersRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class UpdateCostParametersController
{
    public function __invoke(SaveCostParametersRequest $request, SaveVehicleCostParameters $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de salvar os parâmetros.');
        }

        $action->execute($user, $company, null, VehicleCostParametersData::fromArray($request->defaultFields()));

        foreach ($request->vehicleFields() as $vehicleId => $fields) {
            $action->execute($user, $company, $vehicleId, VehicleCostParametersData::fromArray($fields));
        }

        return redirect()
            ->route('cost-parameters.edit')
            ->with('status', 'Parâmetros de custo salvos.');
    }
}
