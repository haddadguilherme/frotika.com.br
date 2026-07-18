<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Data;

final readonly class CompanyData
{
    public function __construct(
        public string $legalName,
        public string $tradeName,
        public string $cnpj,
        public string $taxRegime = 'simples',
        public ?string $stateRegistration = null,
        public ?string $rntrc = null,
        public ?string $zipCode = null,
        public ?string $street = null,
        public ?string $number = null,
        public ?string $complement = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $ibgeCode = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'legal_name' => $this->legalName,
            'trade_name' => $this->tradeName,
            'cnpj' => $this->cnpj,
            'tax_regime' => $this->taxRegime,
            'state_registration' => $this->stateRegistration,
            'rntrc' => $this->rntrc,
            'zip_code' => $this->zipCode,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'ibge_code' => $this->ibgeCode,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
