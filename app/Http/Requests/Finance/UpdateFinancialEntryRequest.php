<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\FinancialEntry;
use Illuminate\Support\Facades\Gate;

final class UpdateFinancialEntryRequest extends FinancialEntryRequest
{
    public function authorize(): bool
    {
        $entry = FinancialEntry::query()->find($this->route('entry'));

        return $entry instanceof FinancialEntry && Gate::allows('update', $entry);
    }
}
