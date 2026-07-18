<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\UpdateManualFinancialEntry;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Finance\UpdateFinancialEntryRequest;
use Illuminate\Http\RedirectResponse;

final class UpdateFinancialEntryController
{
    public function __invoke(UpdateFinancialEntryRequest $request, int $entry, UpdateManualFinancialEntry $action): RedirectResponse
    {
        $model = FinancialEntry::query()->findOrFail($entry);
        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $data = $request->validated();
        $data['type'] = $this->resolveType((int) $data['financial_category_id']);

        $action->execute($company, (int) $model->getKey(), $data);

        return redirect()
            ->route('financial-entries.show', ['entry' => $model->getKey()])
            ->with('status', 'Lançamento atualizado com sucesso.');
    }

    private function resolveType(int $categoryId): string
    {
        $category = FinancialCategory::query()->find($categoryId);

        return $category?->type->value ?? '';
    }
}
