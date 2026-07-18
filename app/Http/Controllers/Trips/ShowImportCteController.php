<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Trips\Models\CteDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowImportCteController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('create', CteDocument::class);

        return view('cte.import');
    }
}
