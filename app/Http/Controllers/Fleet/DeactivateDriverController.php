<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\DeactivateDriver;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DeactivateDriverController
{
    public function __invoke(Request $request, int $driver, DeactivateDriver $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa.');
        }

        $model = Driver::query()->findOrFail($driver);

        $action->execute($user, $company, $model);

        return redirect()
            ->route('drivers.index')
            ->with('status', 'Motorista removido.');
    }
}
