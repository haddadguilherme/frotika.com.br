<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\FinancialEntry;
use Illuminate\Support\Facades\Gate;

final class StoreFinancialEntryRequest extends FinancialEntryRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', FinancialEntry::class);
    }
}
