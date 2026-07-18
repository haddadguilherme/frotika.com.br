<?php

declare(strict_types=1);

namespace App\Support\Cep;

/**
 * Endereço normalizado a partir da resposta do ViaCEP.
 */
final readonly class CepData
{
    public function __construct(
        public string $zipCode,
        public ?string $street = null,
        public ?string $complement = null,
        public ?string $district = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $ibgeCode = null,
    ) {}

    /**
     * CEP "genérico" é o da cidade toda: o ViaCEP não devolve logradouro nem
     * bairro. Nesse caso o usuário precisa digitar rua e bairro manualmente.
     */
    public function isGeneric(): bool
    {
        return $this->street === null && $this->district === null;
    }

    /**
     * Payload enxuto que o formulário de cadastro consome.
     *
     * @return array<string, string|null>
     */
    public function toFormPayload(): array
    {
        return [
            'zip_code' => $this->zipCode,
            'street' => $this->street,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'ibge_code' => $this->ibgeCode,
        ];
    }
}
