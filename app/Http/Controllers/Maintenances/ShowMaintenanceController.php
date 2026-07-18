<?php

declare(strict_types=1);

namespace App\Http\Controllers\Maintenances;

use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Maintenances\Models\Maintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class ShowMaintenanceController
{
    public function __invoke(int $maintenance): View
    {
        $model = Maintenance::query()
            ->with(['vehicle:id,plate,type', 'supplier:id,legal_name,trade_name'])
            ->findOrFail($maintenance);

        Gate::authorize('view', $model);

        $entry = FinancialEntry::query()
            ->where('sourceable_type', Maintenance::class)
            ->where('sourceable_id', $model->getKey())
            ->first();

        return view('maintenances.show', [
            'maintenance' => $model,
            'entry' => $entry,
            'canManage' => Gate::allows('update', $model),
        ]);
    }
}
