<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Trips\Models\CteDocument;
use App\Domain\Trips\Models\CteImportBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowCteImportResultController
{
    public function __invoke(Request $request, string $batch): View
    {
        Gate::authorize('viewAny', CteDocument::class);

        // O CompanyScope garante que só o lote da empresa ativa é encontrado.
        $model = CteImportBatch::query()
            ->where('uuid', $batch)
            ->firstOrFail();

        return view('cte.import-result', [
            'batch' => $model,
        ]);
    }
}
