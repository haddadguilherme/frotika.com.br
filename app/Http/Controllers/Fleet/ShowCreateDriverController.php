<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Models\Driver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class ShowCreateDriverController
{
    public function __invoke(): View
    {
        Gate::authorize('create', Driver::class);

        return view('drivers.create', ['driver' => null]);
    }
}
