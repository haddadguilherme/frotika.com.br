<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

final class DeactivateVehicle
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, Vehicle $vehicle): void
    {
        Gate::forUser($actor)->authorize('delete', $vehicle);

        $this->tenant->runFor($company, function () use ($vehicle): void {
            $vehicle->setAttribute('status', VehicleStatus::Inactive->value);
            $vehicle->save();
            $vehicle->delete();
        });
    }
}
