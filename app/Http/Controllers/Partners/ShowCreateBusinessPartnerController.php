<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners;

use App\Domain\Partners\Models\BusinessPartner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowCreateBusinessPartnerController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('create', BusinessPartner::class);

        return view('partners.create');
    }
}
