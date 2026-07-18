<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\CreateManualFinancialEntry;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Finance\StoreFinancialEntryRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class StoreFinancialEntryController
{
    public function __invoke(StoreFinancialEntryRequest $request, CreateManualFinancialEntry $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de lançar.');
        }

        $data = $request->validated();
        $data['type'] = $this->resolveType((int) $data['financial_category_id']);

        $action->execute($company, (int) $user->getKey(), $data);

        return redirect()
            ->route('financial-entries.index')
            ->with('status', 'Lançamento registrado com sucesso.');
    }

    private function resolveType(int $categoryId): string
    {
        $category = FinancialCategory::query()->find($categoryId);

        return $category?->type->value ?? '';
    }
}
