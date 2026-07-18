<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet;

use App\Domain\Fleet\Models\Driver;
use Illuminate\Support\Facades\Gate;

final class UpdateDriverRequest extends DriverRequest
{
    public function authorize(): bool
    {
        $driver = $this->route('driver');

        if (! $driver instanceof Driver) {
            $driver = Driver::query()->find($driver);
        }

        return $driver instanceof Driver && Gate::allows('update', $driver);
    }
}
