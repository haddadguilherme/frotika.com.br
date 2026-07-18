<?php

declare(strict_types=1);

namespace App\Http\Controllers\Maintenances;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Maintenances\Models\Maintenance;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class ShowCreateMaintenanceController
{
    public function __invoke(): View|RedirectResponse
    {
        Gate::authorize('create', Maintenance::class);

        $vehicles = Vehicle::query()->orderBy('plate')->get(['id', 'plate']);

        if ($vehicles->isEmpty()) {
            return redirect()
                ->route('vehicles.index')
                ->with('warning', 'Cadastre um veículo antes de lançar manutenções.');
        }

        return view('maintenances.create', [
            'maintenance' => null,
            'vehicles' => $vehicles,
            'workshops' => $this->workshops(),
        ]);
    }

    /**
     * @return Collection<int, BusinessPartner>
     */
    private function workshops(): Collection
    {
        return BusinessPartner::query()
            ->whereIn('kind', [BusinessPartnerKind::Workshop->value, BusinessPartnerKind::Parts->value])
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'trade_name', 'kind']);
    }
}
