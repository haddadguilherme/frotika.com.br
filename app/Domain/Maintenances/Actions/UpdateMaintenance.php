<?php

declare(strict_types=1);

namespace App\Domain\Maintenances\Actions;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Maintenances\Data\MaintenanceData;
use App\Domain\Maintenances\Models\Maintenance;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateMaintenance
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, Maintenance $maintenance, MaintenanceData $data): Maintenance
    {
        Gate::forUser($actor)->authorize('update', $maintenance);

        return $this->tenant->runFor($company, function () use ($company, $maintenance, $data): Maintenance {
            $vehicle = Vehicle::query()->find($data->vehicleId);

            if (! $vehicle instanceof Vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Selecione um veículo válido da empresa ativa.',
                ]);
            }

            if ($data->supplierId !== null && ! BusinessPartner::query()->whereKey($data->supplierId)->exists()) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Selecione uma oficina válida da empresa ativa.',
                ]);
            }

            $attributes = $data->toAttributes();
            $attributes['company_id'] = $company->getKey();

            $maintenance->update($attributes);

            if ($data->odometer !== null && $data->odometer > (int) $vehicle->getAttribute('odometer_current')) {
                $vehicle->forceFill(['odometer_current' => $data->odometer])->save();
            }

            return $maintenance->refresh();
        });
    }
}
