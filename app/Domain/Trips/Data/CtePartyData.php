<?php

declare(strict_types=1);

namespace App\Domain\Trips\Data;

use App\Domain\Partners\Enums\BusinessPartnerDocumentType;

final readonly class CtePartyData
{
    public function __construct(
        public ?string $document,
        public BusinessPartnerDocumentType $documentType,
        public string $legalName,
        public ?string $tradeName = null,
        public ?string $stateRegistration = null,
        public ?string $phone = null,
        public ?string $zipCode = null,
        public ?string $street = null,
        public ?string $number = null,
        public ?string $complement = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $ibgeCode = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'document' => $this->document,
            'document_type' => $this->documentType->value,
            'legal_name' => $this->legalName,
            'trade_name' => $this->tradeName,
            'state_registration' => $this->stateRegistration,
            'phone' => $this->phone,
            'zip_code' => $this->zipCode,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'ibge_code' => $this->ibgeCode,
        ];
    }
}
