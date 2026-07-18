<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
            self::Maintenance => 'Em manutenção',
            self::Sold => 'Vendido',
        };
    }
}
