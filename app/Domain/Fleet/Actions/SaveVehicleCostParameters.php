<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Data\VehicleCostParametersData;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fleet\Models\VehicleCostParameter;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Grava os parâmetros de reserva de um veículo, ou o padrão da empresa quando
 * $vehicleId é nulo. Regra de negócio fica aqui (Action de método único); a UI
 * só valida e chama.
 */
final class SaveVehicleCostParameters
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(
        User $actor,
        Company $company,
        ?int $vehicleId,
        VehicleCostParametersData $data,
    ): VehicleCostParameter {
        return $this->tenant->runFor($company, function () use ($actor, $vehicleId, $data): VehicleCostParameter {
            if ($vehicleId === null) {
                // Padrão da empresa: mesma autoridade de gestão da frota.
                Gate::forUser($actor)->authorize('create', Vehicle::class);
            } else {
                $vehicle = Vehicle::query()->find($vehicleId);

                if (! $vehicle instanceof Vehicle) {
                    throw ValidationException::withMessages([
                        'vehicle_id' => 'Veículo não encontrado nesta empresa.',
                    ]);
                }

                Gate::forUser($actor)->authorize('update', $vehicle);
            }

            /** @var VehicleCostParameter $parameter */
            $parameter = VehicleCostParameter::query()->updateOrCreate(
                ['vehicle_id' => $vehicleId],
                $data->toAttributes(),
            );

            return $parameter;
        });
    }
}
