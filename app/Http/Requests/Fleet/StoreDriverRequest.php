<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet;

use App\Domain\Fleet\Models\Driver;
use Illuminate\Support\Facades\Gate;

final class StoreDriverRequest extends DriverRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Driver::class);
    }
}
