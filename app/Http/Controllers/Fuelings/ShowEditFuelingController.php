<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fuelings;

use App\Domain\Fleet\Models\Driver;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fuelings\Models\Fueling;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class ShowEditFuelingController
{
    public function __invoke(int $fueling): View
    {
        $model = Fueling::query()->findOrFail($fueling);

        Gate::authorize('update', $model);

        return view('fuelings.edit', [
            'fueling' => $model,
            'vehicles' => Vehicle::query()->orderBy('plate')->get(['id', 'plate']),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
            'stations' => BusinessPartner::query()
                ->where('kind', BusinessPartnerKind::GasStation->value)
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'trade_name']),
        ]);
    }
}
