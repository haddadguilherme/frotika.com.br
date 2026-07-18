<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\CreateDriver;
use App\Domain\Fleet\Data\DriverData;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Fleet\StoreDriverRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class StoreDriverController
{
    public function __invoke(StoreDriverRequest $request, CreateDriver $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de cadastrar motoristas.');
        }

        $driver = $action->execute($user, $company, DriverData::fromArray($request->validated()));

        return redirect()
            ->route('drivers.show', ['driver' => $driver->getKey()])
            ->with('status', 'Motorista cadastrado com sucesso.');
    }
}
