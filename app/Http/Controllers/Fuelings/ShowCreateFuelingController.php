<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fuelings;

use App\Domain\Fleet\Models\Driver;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fuelings\Models\Fueling;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class ShowCreateFuelingController
{
    public function __invoke(): View|RedirectResponse
    {
        Gate::authorize('create', Fueling::class);

        $vehicles = Vehicle::query()->orderBy('plate')->get(['id', 'plate']);

        if ($vehicles->isEmpty()) {
            return redirect()
                ->route('vehicles.index')
                ->with('warning', 'Cadastre um veículo antes de lançar abastecimentos.');
        }

        return view('fuelings.create', [
            'fueling' => null,
            'vehicles' => $vehicles,
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
            'stations' => BusinessPartner::query()
                ->where('kind', BusinessPartnerKind::GasStation->value)
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'trade_name']),
        ]);
    }
}
