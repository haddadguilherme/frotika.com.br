<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Models\FinancialEntry;
use App\Http\Controllers\Finance\Concerns\ProvidesFinancialEntryFormData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowEditFinancialEntryController
{
    use ProvidesFinancialEntryFormData;

    public function __invoke(Request $request, int $entry): View|RedirectResponse
    {
        $model = FinancialEntry::query()->findOrFail($entry);

        Gate::authorize('update', $model);

        if ($model->getAttribute('sourceable_type') !== null) {
            return redirect()
                ->route('financial-entries.show', ['entry' => $model->getKey()])
                ->with('warning', 'Lançamento sincronizado só pode ser alterado na origem (CT-e, abastecimento…). É possível apenas dar baixa.');
        }

        if ($model->getAttribute('transfer_pair_id') !== null) {
            return redirect()
                ->route('financial-entries.show', ['entry' => $model->getKey()])
                ->with('warning', 'Transferências entre contas devem ser editadas na tela de transferências.');
        }

        return view('financial-entries.edit', [
            ...$this->formData(),
            'entry' => $model,
        ]);
    }
}
