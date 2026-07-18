<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Data\DriverData;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateDriver
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, Driver $driver, DriverData $data): Driver
    {
        Gate::forUser($actor)->authorize('update', $driver);

        return $this->tenant->runFor($company, function () use ($driver, $data): Driver {
            if ($data->cpf !== null && Driver::query()->where('cpf', $data->cpf)->whereKeyNot($driver->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'cpf' => 'Já existe um motorista com este CPF nesta empresa.',
                ]);
            }

            $driver->update($data->toAttributes());

            return $driver->refresh();
        });
    }
}
