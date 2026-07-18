<?php

declare(strict_types=1);

namespace App\Domain\Trips\Enums;

enum CteStatus: string
{
    case Authorized = 'authorized';
    case Canceled = 'canceled';
    case Denied = 'denied';
    case Pending = 'pending';

    /**
     * Deriva o status a partir do cStat do protocolo (protCTe/infProt/cStat).
     */
    public static function fromProtocolCode(?string $code): self
    {
        return match (trim((string) $code)) {
            '100', '150' => self::Authorized,
            '101', '135', '151', '155' => self::Canceled,
            '110', '301', '302' => self::Denied,
            '' => self::Pending,
            default => self::Authorized,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Authorized => 'Autorizado',
            self::Canceled => 'Cancelado',
            self::Denied => 'Denegado',
            self::Pending => 'Pendente',
        };
    }
}
