<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Domain\Tenancy\Actions\CreateCompany;
use App\Domain\Tenancy\Data\CompanyData;
use App\Http\Requests\Tenancy\StoreCompanyRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class CreateCompanyController
{
    public function __invoke(StoreCompanyRequest $request, CreateCompany $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $request->validated();

        $company = $action->execute($user, new CompanyData(
            legalName: $validated['legal_name'],
            tradeName: $validated['trade_name'],
            cnpj: $validated['cnpj'],
            taxRegime: $validated['tax_regime'] ?? 'simples',
            stateRegistration: $validated['state_registration'] ?? null,
            rntrc: $validated['rntrc'] ?? null,
            zipCode: $validated['zip_code'] ?? null,
            street: $validated['street'] ?? null,
            number: $validated['number'] ?? null,
            complement: $validated['complement'] ?? null,
            district: $validated['district'] ?? null,
            city: $validated['city'] ?? null,
            state: $validated['state'] ?? null,
            ibgeCode: $validated['ibge_code'] ?? null,
            phone: $validated['phone'] ?? null,
            email: $validated['email'] ?? null,
        ));

        return redirect()
            ->route('companies.show', ['company' => $company->getKey()])
            ->with('status', 'Empresa cadastrada com sucesso.');
    }
}
