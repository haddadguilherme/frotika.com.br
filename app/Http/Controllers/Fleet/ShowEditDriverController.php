<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Models\Driver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class ShowEditDriverController
{
    public function __invoke(int $driver): View
    {
        $model = Driver::query()->findOrFail($driver);

        Gate::authorize('update', $model);

        return view('drivers.edit', ['driver' => $model]);
    }
}
