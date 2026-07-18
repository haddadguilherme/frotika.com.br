<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Trips\Models\CteDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowCteController
{
    public function __invoke(Request $request, int $cte): View
    {
        $document = CteDocument::query()
            ->with(['vehicle', 'trailer', 'partners'])
            ->findOrFail($cte);

        Gate::authorize('view', $document);

        $entry = FinancialEntry::query()
            ->where('sourceable_type', CteDocument::class)
            ->where('sourceable_id', $document->getKey())
            ->first();

        return view('cte.show', [
            'document' => $document,
            'entry' => $entry,
        ]);
    }
}
