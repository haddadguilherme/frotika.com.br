<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Models\FinancialEntry;
use App\Http\Controllers\Finance\Concerns\ProvidesFinancialEntryFormData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowCreateFinancialEntryController
{
    use ProvidesFinancialEntryFormData;

    public function __invoke(Request $request): View
    {
        Gate::authorize('create', FinancialEntry::class);

        return view('financial-entries.create', [
            ...$this->formData(),
            'entry' => null,
        ]);
    }
}
