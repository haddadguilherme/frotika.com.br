<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fleet\Models\VehicleOdometerReading;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Registra uma leitura de hodômetro do veículo — a base manual do cálculo de
 * km do mês (híbrido com as leituras dos abastecimentos/manutenções). Uma
 * leitura por dia; regravar o mesmo dia atualiza o valor.
 */
final class RegisterOdometerReading
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(
        User $actor,
        Company $company,
        int $vehicleId,
        string $readOn,
        int $odometer,
        ?string $note = null,
    ): VehicleOdometerReading {
        return $this->tenant->runFor($company, function () use ($actor, $vehicleId, $readOn, $odometer, $note): VehicleOdometerReading {
            $vehicle = Vehicle::query()->find($vehicleId);

            if (! $vehicle instanceof Vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Veículo não encontrado nesta empresa.',
                ]);
            }

            Gate::forUser($actor)->authorize('update', $vehicle);

            /** @var VehicleOdometerReading $reading */
            $reading = VehicleOdometerReading::query()->updateOrCreate(
                ['vehicle_id' => $vehicleId, 'read_on' => $readOn],
                ['odometer' => $odometer, 'note' => $note, 'created_by' => $actor->getKey()],
            );

            if ($odometer > (int) $vehicle->getAttribute('odometer_current')) {
                $vehicle->forceFill(['odometer_current' => $odometer])->save();
            }

            return $reading;
        });
    }
}
