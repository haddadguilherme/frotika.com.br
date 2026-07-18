<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners;

use App\Domain\Partners\Actions\CreateBusinessPartner;
use App\Domain\Partners\Data\BusinessPartnerData;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Partners\StoreBusinessPartnerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class StoreBusinessPartnerController
{
    public function __invoke(StoreBusinessPartnerRequest $request, CreateBusinessPartner $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de cadastrar parceiros.');
        }

        $partner = $action->execute($user, $company, BusinessPartnerData::fromArray($request->validated()));

        return redirect()
            ->route('partners.show', ['partner' => $partner->getKey()])
            ->with('status', 'Parceiro cadastrado com sucesso.');
    }
}
