<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners;

use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ListBusinessPartnersController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', BusinessPartner::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $kindFilter = BusinessPartnerKind::tryFrom((string) $request->query('kind', ''));

        $partners = BusinessPartner::query()
            ->when($kindFilter !== null, fn ($query) => $query->where('kind', $kindFilter?->value))
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'trade_name', 'document', 'document_type', 'kind', 'city', 'state', 'active']);

        return view('partners.index', [
            'partners' => $partners,
            'canManage' => Gate::allows('create', BusinessPartner::class),
            'kindFilter' => $kindFilter,
        ]);
    }
}
