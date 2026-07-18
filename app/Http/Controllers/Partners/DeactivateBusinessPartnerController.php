<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners;

use App\Domain\Partners\Actions\DeactivateBusinessPartner;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DeactivateBusinessPartnerController
{
    public function __invoke(Request $request, int $partner, DeactivateBusinessPartner $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = BusinessPartner::query()->findOrFail($partner);
        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $action->execute($user, $company, $model);

        return redirect()
            ->route('partners.index')
            ->with('status', 'Parceiro desativado.');
    }
}
