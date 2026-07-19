<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\RegisterOdometerReading;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Fleet\StoreOdometerReadingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class StoreOdometerReadingController
{
    public function __invoke(
        StoreOdometerReadingRequest $request,
        int $vehicle,
        RegisterOdometerReading $action,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de registrar leituras.');
        }

        $action->execute(
            $user,
            $company,
            $vehicle,
            (string) $request->date('read_on')->format('Y-m-d'),
            $request->integer('odometer'),
            $request->input('note'),
        );

        return redirect()
            ->route('vehicles.show', ['vehicle' => $vehicle])
            ->with('status', 'Leitura de hodômetro registrada.');
    }
}
