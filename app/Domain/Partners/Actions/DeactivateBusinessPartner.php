<?php

declare(strict_types=1);

namespace App\Domain\Partners\Actions;

use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

final class DeactivateBusinessPartner
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, BusinessPartner $partner): void
    {
        Gate::forUser($actor)->authorize('delete', $partner);

        $this->tenant->runFor($company, function () use ($partner): void {
            $partner->setAttribute('active', false);
            $partner->save();
            $partner->delete();
        });
    }
}
