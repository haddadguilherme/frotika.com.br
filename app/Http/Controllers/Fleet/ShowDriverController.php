<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Models\Driver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class ShowDriverController
{
    public function __invoke(int $driver): View
    {
        $model = Driver::query()->findOrFail($driver);

        Gate::authorize('view', $model);

        return view('drivers.show', [
            'driver' => $model,
            'canManage' => Gate::allows('update', $model),
        ]);
    }
}
