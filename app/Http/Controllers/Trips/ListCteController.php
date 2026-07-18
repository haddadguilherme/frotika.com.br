<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Trips\Models\CteDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ListCteController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', CteDocument::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $documents = CteDocument::query()
            ->with('vehicle:id,plate')
            ->orderByDesc('issued_at')
            ->limit(200)
            ->get();

        return view('cte.index', [
            'documents' => $documents,
            'canImport' => Gate::allows('create', CteDocument::class),
        ]);
    }
}
