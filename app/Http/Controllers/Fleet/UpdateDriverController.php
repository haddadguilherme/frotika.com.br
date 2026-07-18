<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\UpdateDriver;
use App\Domain\Fleet\Data\DriverData;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Fleet\UpdateDriverRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class UpdateDriverController
{
    public function __invoke(UpdateDriverRequest $request, int $driver, UpdateDriver $action): RedirectResponse
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

        $action->execute($user, $company, $model, DriverData::fromArray($request->validated()));

        return redirect()
            ->route('drivers.show', ['driver' => $model->getKey()])
            ->with('status', 'Motorista atualizado com sucesso.');
    }
}
