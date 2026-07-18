<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleFuelType: string
{
    case DieselS10 = 'diesel_s10';
    case DieselS500 = 'diesel_s500';
    case Gasoline = 'gasoline';
    case Ethanol = 'ethanol';
    case Cng = 'cng';
    case Electric = 'electric';

    public function label(): string
    {
        return match ($this) {
            self::DieselS10 => 'Diesel S10',
            self::DieselS500 => 'Diesel S500',
            self::Gasoline => 'Gasolina',
            self::Ethanol => 'Etanol',
            self::Cng => 'GNV',
            self::Electric => 'Elétrico',
        };
    }
}
