<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners;

use App\Domain\Partners\Models\BusinessPartner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowBusinessPartnerController
{
    public function __invoke(Request $request, int $partner): View
    {
        $model = BusinessPartner::query()->findOrFail($partner);

        Gate::authorize('view', $model);

        return view('partners.show', [
            'partner' => $model,
            'canManage' => Gate::allows('update', $model),
        ]);
    }
}
