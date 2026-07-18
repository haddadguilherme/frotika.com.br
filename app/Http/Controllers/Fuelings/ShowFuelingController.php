<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fuelings;

use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fuelings\Models\Fueling;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class ShowFuelingController
{
    public function __invoke(int $fueling): View
    {
        $model = Fueling::query()
            ->with(['vehicle:id,plate,type', 'driver:id,name', 'station:id,legal_name,trade_name'])
            ->findOrFail($fueling);

        Gate::authorize('view', $model);

        $entry = FinancialEntry::query()
            ->where('sourceable_type', Fueling::class)
            ->where('sourceable_id', $model->getKey())
            ->first();

        return view('fuelings.show', [
            'fueling' => $model,
            'entry' => $entry,
            'canManage' => Gate::allows('update', $model),
        ]);
    }
}
