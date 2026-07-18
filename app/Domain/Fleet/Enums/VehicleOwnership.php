<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleOwnership: string
{
    case Own = 'own';
    case Leased = 'leased';
    case Aggregate = 'aggregate';
    case ThirdParty = 'third_party';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Próprio',
            self::Leased => 'Arrendado',
            self::Aggregate => 'Agregado',
            self::ThirdParty => 'Terceiro',
        };
    }
}
