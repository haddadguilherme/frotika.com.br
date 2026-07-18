<?php

declare(strict_types=1);

namespace App\Domain\Trips\Enums;

enum CteType: string
{
    case Normal = 'normal';
    case Complementary = 'complementary';
    case Annulment = 'annulment';
    case Substitute = 'substitute';

    public static function fromCode(string $code): self
    {
        return match (trim($code)) {
            '0' => self::Normal,
            '1' => self::Complementary,
            '2' => self::Annulment,
            '3' => self::Substitute,
            default => self::Normal,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Complementary => 'Complementar',
            self::Annulment => 'Anulação',
            self::Substitute => 'Substituto',
        };
    }
}
