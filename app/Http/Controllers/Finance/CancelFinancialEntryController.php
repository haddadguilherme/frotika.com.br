<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\CancelFinancialEntry;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CancelFinancialEntryController
{
    public function __invoke(Request $request, int $entry, CancelFinancialEntry $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = FinancialEntry::query()->findOrFail($entry);

        Gate::authorize('delete', $model);

        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $action->execute($company, (int) $model->getKey());

        return redirect()
            ->route('financial-entries.index')
            ->with('status', 'Lançamento cancelado.');
    }
}
