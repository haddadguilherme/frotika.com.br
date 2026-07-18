<?php

declare(strict_types=1);

namespace App\Domain\Partners\Enums;

enum BusinessPartnerKind: string
{
    case Contractor = 'contractor';
    case Customer = 'customer';
    case Carrier = 'carrier';
    case GasStation = 'gas_station';
    case Workshop = 'workshop';
    case Parts = 'parts';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contractor => 'Contratante',
            self::Customer => 'Cliente',
            self::Carrier => 'Transportadora',
            self::GasStation => 'Posto',
            self::Workshop => 'Oficina',
            self::Parts => 'Peças',
            self::Other => 'Outro',
        };
    }
}
