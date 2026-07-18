<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Enums\DriverStatus;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Tenancy\Models\Company;
use App\Support\Cpf\Cpf;
use App\Support\Tenancy\TenantContext;

/**
 * Garante um motorista mínimo pelo CPF vindo do XML do CT-e, para vincular o
 * documento a um cadastro real (espelha ProvisionVehicleByPlate). CPF inválido
 * ou ausente → não vincula (o CT-e mantém só o snapshot em texto). Não
 * sobrescreve o nome de um motorista já cadastrado — a edição manual manda.
 */
final class ProvisionDriverByCpf
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(Company $company, ?string $cpf, ?string $name): ?Driver
    {
        $digits = $cpf === null ? '' : Cpf::digits($cpf);

        if ($digits === '' || ! Cpf::isValid($digits)) {
            return null;
        }

        return $this->tenant->runFor($company, function () use ($digits, $name): Driver {
            $driver = Driver::query()->where('cpf', $digits)->first();

            if ($driver instanceof Driver) {
                return $driver;
            }

            /** @var Driver $driver */
            $driver = Driver::query()->create([
                'name' => $this->resolveName($name, $digits),
                'cpf' => $digits,
                'status' => DriverStatus::Active->value,
            ]);

            return $driver;
        });
    }

    private function resolveName(?string $name, string $cpf): string
    {
        $name = $name === null ? '' : trim($name);

        if ($name !== '') {
            return mb_substr($name, 0, 120);
        }

        return 'Motorista '.Cpf::format($cpf);
    }
}
