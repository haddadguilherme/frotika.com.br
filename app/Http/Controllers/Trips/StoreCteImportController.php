<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Tenancy\Models\Company;
use App\Domain\Trips\Actions\ImportCte;
use App\Domain\Trips\Cte\Exceptions\InvalidCteException;
use App\Http\Requests\Trips\ImportCteRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class StoreCteImportController
{
    public function __invoke(ImportCteRequest $request, ImportCte $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de importar CT-e.');
        }

        $file = $request->file('xml');
        $contents = $file === null ? '' : (string) file_get_contents($file->getRealPath());

        try {
            $cte = $action->execute($user, $company, $contents, $file?->getClientOriginalName());
        } catch (InvalidCteException $exception) {
            return back()
                ->withErrors(['xml' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cte.show', ['cte' => $cte->getKey()])
            ->with('status', 'CT-e importado com sucesso.');
    }
}
