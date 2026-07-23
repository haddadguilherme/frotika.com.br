<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Data\VehicleData;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateVehicle
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, Vehicle $vehicle, VehicleData $data): Vehicle
    {
        Gate::forUser($actor)->authorize('update', $vehicle);

        return $this->tenant->runFor($company, function () use ($vehicle, $data): Vehicle {
            $clash = Vehicle::query()
                ->where('plate', $data->plate)
                ->whereKeyNot($vehicle->getKey())
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'plate' => 'Já existe outro veículo com esta placa nesta empresa.',
                ]);
            }

            $wasProvisioned = (bool) $vehicle->getAttribute('provisioned');
            $attributes = $data->toAttributes();
            // odometer_current é denormalizado (viagens/abastecimentos) — não sobrescreve.
            unset($attributes['odometer_current']);

            $vehicle->fill($attributes);
            $vehicle->save();

            if ($wasProvisioned && $vehicle->hasMinimumRegistrationData()) {
                $vehicle->markAsComplete();
            }

            return $vehicle;
        });
    }
}
