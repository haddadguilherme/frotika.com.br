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

final class CreateVehicle
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, VehicleData $data): Vehicle
    {
        Gate::forUser($actor)->authorize('create', Vehicle::class);

        return $this->tenant->runFor($company, function () use ($data): Vehicle {
            if (Vehicle::query()->where('plate', $data->plate)->exists()) {
                throw ValidationException::withMessages([
                    'plate' => 'Já existe um veículo com esta placa nesta empresa.',
                ]);
            }

            $attributes = $data->toAttributes();

            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->create($attributes);

            // Cadastro manual sempre é veículo completo.
            $vehicle->setAttribute('provisioned', false);
            // odometer_current é denormalizado e fica fora do fillable por segurança.
            $vehicle->setAttribute('odometer_current', $data->odometerInitial);
            $vehicle->save();

            return $vehicle;
        });
    }
}
