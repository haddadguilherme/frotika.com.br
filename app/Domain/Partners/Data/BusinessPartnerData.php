<?php

declare(strict_types=1);

namespace App\Domain\Partners\Data;

use App\Domain\Partners\Enums\BusinessPartnerDocumentType;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Support\Cnpj\Cnpj;

final readonly class BusinessPartnerData
{
    public function __construct(
        public string $legalName,
        public BusinessPartnerKind $kind,
        public ?string $document = null,
        public ?string $tradeName = null,
        public ?float $defaultFreightSharePercent = null,
        public ?string $stateRegistration = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $zipCode = null,
        public ?string $street = null,
        public ?string $number = null,
        public ?string $complement = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $ibgeCode = null,
        public ?string $notes = null,
        public bool $active = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $percent = $data['default_freight_share_percent'] ?? null;

        return new self(
            legalName: (string) $data['legal_name'],
            kind: BusinessPartnerKind::from((string) $data['kind']),
            document: $data['document'] ?? null,
            tradeName: $data['trade_name'] ?? null,
            defaultFreightSharePercent: $percent === null ? null : (float) $percent,
            stateRegistration: $data['state_registration'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            street: $data['street'] ?? null,
            number: $data['number'] ?? null,
            complement: $data['complement'] ?? null,
            district: $data['district'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            ibgeCode: $data['ibge_code'] ?? null,
            notes: $data['notes'] ?? null,
            active: (bool) ($data['active'] ?? true),
        );
    }

    public function documentDigits(): ?string
    {
        if ($this->document === null) {
            return null;
        }

        $digits = Cnpj::digits($this->document);

        return $digits === '' ? null : $digits;
    }

    public function documentType(): BusinessPartnerDocumentType
    {
        return BusinessPartnerDocumentType::fromDigits($this->documentDigits());
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'document' => $this->documentDigits(),
            'document_type' => $this->documentType()->value,
            'legal_name' => $this->legalName,
            'trade_name' => $this->tradeName,
            'kind' => $this->kind->value,
            'default_freight_share_percent' => $this->defaultFreightSharePercent,
            'state_registration' => $this->stateRegistration,
            'phone' => $this->phone,
            'email' => $this->email,
            'zip_code' => $this->zipCode,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'ibge_code' => $this->ibgeCode,
            'notes' => $this->notes,
            'active' => $this->active,
        ];
    }
}
