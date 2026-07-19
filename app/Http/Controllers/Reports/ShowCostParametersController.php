<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fleet\Models\VehicleCostParameter;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowCostParametersController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Vehicle::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa para editar os parâmetros de custo.');
        }

        $default = VehicleCostParameter::query()->whereNull('vehicle_id')->first();

        $overrides = VehicleCostParameter::query()
            ->whereNotNull('vehicle_id')
            ->get()
            ->keyBy('vehicle_id');

        $vehicles = Vehicle::query()->orderBy('plate')->get(['id', 'plate', 'type']);

        return view('cost-parameters.edit', [
            'default' => $default,
            'overrides' => $overrides,
            'vehicles' => $vehicles,
        ]);
    }
}
