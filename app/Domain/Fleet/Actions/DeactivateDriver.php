<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Models\Driver;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

final class DeactivateDriver
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, Driver $driver): void
    {
        Gate::forUser($actor)->authorize('delete', $driver);

        $this->tenant->runFor($company, function () use ($driver): void {
            $driver->delete();
        });
    }
}
