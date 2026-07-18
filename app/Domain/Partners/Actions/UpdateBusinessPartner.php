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

final class UpdateBusinessPartner
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, BusinessPartner $partner, BusinessPartnerData $data): BusinessPartner
    {
        Gate::forUser($actor)->authorize('update', $partner);

        return $this->tenant->runFor($company, function () use ($partner, $data): BusinessPartner {
            $document = $data->documentDigits();

            if ($document !== null) {
                $clash = BusinessPartner::query()
                    ->where('document', $document)
                    ->whereKeyNot($partner->getKey())
                    ->exists();

                if ($clash) {
                    throw ValidationException::withMessages([
                        'document' => 'Já existe outro parceiro com este documento nesta empresa.',
                    ]);
                }
            }

            $partner->fill($data->toAttributes());
            $partner->save();

            return $partner;
        });
    }
}
