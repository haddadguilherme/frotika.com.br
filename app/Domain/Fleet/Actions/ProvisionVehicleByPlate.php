<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Support\Tenancy\TenantContext;

/**
 * Garante um veículo mínimo por placa para vincular ao CT-e. Quando a placa não
 * existe, cria um registro "provisionado" (stub) que o dono completa depois; o
 * tipo é um palpite (cavalo/carreta) que não é sobrescrito se já houver veículo.
 */
final class ProvisionVehicleByPlate
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(Company $company, ?string $plate, VehicleType $type): ?Vehicle
    {
        $normalized = $this->normalize($plate);

        if ($normalized === null) {
            return null;
        }

        return $this->tenant->runFor($company, function () use ($normalized, $type): Vehicle {
            $vehicle = Vehicle::query()->where('plate', $normalized)->first();

            if ($vehicle instanceof Vehicle) {
                return $vehicle;
            }

            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->create([
                'plate' => $normalized,
                'type' => $type->value,
                'status' => VehicleStatus::Active->value,
            ]);

            $vehicle->setAttribute('provisioned', true);
            $vehicle->save();

            return $vehicle;
        });
    }

    private function normalize(?string $plate): ?string
    {
        if ($plate === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '');

        return $normalized === '' ? null : mb_substr($normalized, 0, 8);
    }
}
