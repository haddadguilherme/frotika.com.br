<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners;

use App\Domain\Partners\Actions\UpdateBusinessPartner;
use App\Domain\Partners\Data\BusinessPartnerData;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Partners\UpdateBusinessPartnerRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class UpdateBusinessPartnerController
{
    public function __invoke(UpdateBusinessPartnerRequest $request, int $partner, UpdateBusinessPartner $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = BusinessPartner::query()->findOrFail($partner);
        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $action->execute($user, $company, $model, BusinessPartnerData::fromArray($request->validated()));

        return redirect()
            ->route('partners.show', ['partner' => $model->getKey()])
            ->with('status', 'Parceiro atualizado com sucesso.');
    }
}
