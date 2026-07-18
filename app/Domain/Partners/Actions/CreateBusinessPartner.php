<?php

declare(strict_types=1);

namespace App\Domain\Partners\Actions;

use App\Domain\Partners\Data\BusinessPartnerData;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateBusinessPartner
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, BusinessPartnerData $data): BusinessPartner
    {
        Gate::forUser($actor)->authorize('create', BusinessPartner::class);

        return $this->tenant->runFor($company, function () use ($data): BusinessPartner {
            $document = $data->documentDigits();

            if ($document !== null && BusinessPartner::query()->where('document', $document)->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe um parceiro com este documento nesta empresa.',
                ]);
            }

            /** @var BusinessPartner $partner */
            $partner = BusinessPartner::query()->create($data->toAttributes());

            return $partner;
        });
    }
}
