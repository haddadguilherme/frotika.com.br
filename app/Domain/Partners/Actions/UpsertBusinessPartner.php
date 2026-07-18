<?php

declare(strict_types=1);

namespace App\Domain\Partners\Actions;

use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Trips\Data\CtePartyData;
use App\Support\Tenancy\TenantContext;

/**
 * Cadastro único de parceiros a partir de uma parte de CT-e. Deduplica por
 * documento dentro da empresa. Enriquece: só preenche campos que ainda estão
 * vazios no registro existente e nunca rebaixa o `kind` já definido — para a
 * importação automática não sobrescrever dado editado à mão.
 */
final class UpsertBusinessPartner
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function fromParty(Company $company, CtePartyData $party, BusinessPartnerKind $kind): BusinessPartner
    {
        return $this->tenant->runFor($company, function () use ($party, $kind): BusinessPartner {
            $existing = $party->document === null
                ? null
                : BusinessPartner::query()->where('document', $party->document)->first();

            if ($existing === null) {
                /** @var BusinessPartner $partner */
                $partner = BusinessPartner::query()->create([
                    'document' => $party->document,
                    'document_type' => $party->documentType->value,
                    'legal_name' => $party->legalName,
                    'trade_name' => $party->tradeName,
                    'kind' => $kind->value,
                    'state_registration' => $party->stateRegistration,
                    'phone' => $party->phone,
                    'zip_code' => $party->zipCode,
                    'street' => $party->street,
                    'number' => $party->number,
                    'complement' => $party->complement,
                    'district' => $party->district,
                    'city' => $party->city,
                    'state' => $party->state,
                    'ibge_code' => $party->ibgeCode,
                    'active' => true,
                ]);

                return $partner;
            }

            $this->enrich($existing, $party, $kind);

            return $existing;
        });
    }

    private function enrich(BusinessPartner $partner, CtePartyData $party, BusinessPartnerKind $kind): void
    {
        $fillIfEmpty = [
            'trade_name' => $party->tradeName,
            'state_registration' => $party->stateRegistration,
            'phone' => $party->phone,
            'zip_code' => $party->zipCode,
            'street' => $party->street,
            'number' => $party->number,
            'complement' => $party->complement,
            'district' => $party->district,
            'city' => $party->city,
            'state' => $party->state,
            'ibge_code' => $party->ibgeCode,
        ];

        foreach ($fillIfEmpty as $attribute => $value) {
            if ($value !== null && $partner->getAttribute($attribute) === null) {
                $partner->setAttribute($attribute, $value);
            }
        }

        if ($partner->kind === BusinessPartnerKind::Other && $kind !== BusinessPartnerKind::Other) {
            $partner->setAttribute('kind', $kind->value);
        }

        if ($partner->isDirty()) {
            $partner->save();
        }
    }
}
