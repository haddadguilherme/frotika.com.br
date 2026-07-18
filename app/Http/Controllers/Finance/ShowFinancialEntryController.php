<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowFinancialEntryController
{
    public function __invoke(Request $request, int $entry): View
    {
        $model = FinancialEntry::query()
            ->with(['category:id,code,name', 'bankAccount:id,name', 'author:id,name'])
            ->findOrFail($entry);

        Gate::authorize('view', $model);

        $vehicle = $model->getAttribute('vehicle_id') !== null
            ? Vehicle::query()->find($model->getAttribute('vehicle_id'))
            : null;

        return view('financial-entries.show', [
            'entry' => $model,
            'vehicle' => $vehicle,
            'accounts' => BankAccount::query()->where('active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('update', $model),
            'isSynced' => $model->getAttribute('sourceable_type') !== null,
        ]);
    }
}
