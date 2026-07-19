<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Trips\Models\CteDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
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

        $today = CarbonImmutable::now();
        $from = $request->date('from') ?: $today->startOfMonth();
        $to = $request->date('to') ?: $today->endOfMonth();
        $fromDate = CarbonImmutable::parse($from->format('Y-m-d'));
        $toDate = CarbonImmutable::parse($to->format('Y-m-d'));

        if ($toDate->lt($fromDate)) {
            $toDate = $fromDate;
        }

        $filters = [
            'from' => $fromDate->format('Y-m-d'),
            'to' => $toDate->format('Y-m-d'),
        ];

        $documents = CteDocument::query()
            ->with('vehicle:id,plate')
            ->whereBetween('issued_at', [$fromDate->startOfDay(), $toDate->endOfDay()])
            ->orderByDesc('issued_at')
            ->limit(500)
            ->get();

        $totals = [
            'total_value_cents' => (int) $documents->sum(fn (CteDocument $d): int => (int) $d->getAttribute('total_value_cents')),
            'net_value_cents' => (int) $documents->sum(fn (CteDocument $d): int => (int) $d->getAttribute('receivable_value_cents')),
            'weight_kg' => (float) $documents->sum(fn (CteDocument $d): float => (float) $d->getAttribute('cargo_weight_kg')),
        ];

        return view('cte.index', [
            'documents' => $documents,
            'filters' => $filters,
            'totals' => $totals,
            'canImport' => Gate::allows('create', CteDocument::class),
        ]);
    }
}
