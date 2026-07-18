<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Enums\DriverStatus;
use App\Domain\Fleet\Models\Driver;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ListDriversController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Driver::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $statusFilter = DriverStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));
        $onlyAlerts = $request->boolean('alerts');

        $drivers = Driver::query()
            ->when($statusFilter !== null, fn ($query) => $query->where('status', $statusFilter?->value))
            ->when($search !== '', function ($query) use ($search) {
                $digits = preg_replace('/\D+/', '', $search) ?? '';

                return $query->where(function ($inner) use ($search, $digits) {
                    $inner->where('name', 'like', '%'.$search.'%');

                    if ($digits !== '') {
                        $inner->orWhere('cpf', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->when($onlyAlerts, fn ($query) => $query
                ->whereNotNull('cnh_expires_at')
                ->whereDate('cnh_expires_at', '<=', now()->addDays(Driver::CNH_ALERT_DAYS)))
            ->orderBy('name')
            ->get();

        return view('drivers.index', [
            'drivers' => $drivers,
            'canManage' => Gate::allows('create', Driver::class),
            'statusFilter' => $statusFilter,
            'search' => $search,
            'onlyAlerts' => $onlyAlerts,
            'statuses' => DriverStatus::cases(),
        ]);
    }
}
