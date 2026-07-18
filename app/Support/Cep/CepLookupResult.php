<?php

declare(strict_types=1);

namespace App\Support\Cep;

final readonly class CepLookupResult
{
    public function __construct(
        public CepLookupStatus $status,
        public ?CepData $data = null,
    ) {}

    public static function found(CepData $data): self
    {
        return new self(CepLookupStatus::Found, $data);
    }

    public static function notFound(): self
    {
        return new self(CepLookupStatus::NotFound);
    }

    public static function unavailable(): self
    {
        return new self(CepLookupStatus::Unavailable);
    }
}
