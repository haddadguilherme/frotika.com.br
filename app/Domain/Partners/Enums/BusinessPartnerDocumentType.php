<?php

declare(strict_types=1);

namespace App\Domain\Partners\Enums;

enum BusinessPartnerDocumentType: string
{
    case Cnpj = 'cnpj';
    case Cpf = 'cpf';
    case None = 'none';

    /**
     * Classifica o documento pela quantidade de dígitos.
     */
    public static function fromDigits(?string $digits): self
    {
        return match (strlen((string) $digits)) {
            14 => self::Cnpj,
            11 => self::Cpf,
            default => self::None,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Cnpj => 'CNPJ',
            self::Cpf => 'CPF',
            self::None => 'Sem documento',
        };
    }
}
