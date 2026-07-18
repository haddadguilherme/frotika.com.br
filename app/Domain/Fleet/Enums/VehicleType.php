<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleType: string
{
    case Tractor = 'tractor';
    case SemiTrailer = 'semi_trailer';
    case Truck = 'truck';
    case Toco = 'toco';
    case Vuc = 'vuc';
    case Utility = 'utility';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tractor => 'Cavalo',
            self::SemiTrailer => 'Carreta',
            self::Truck => 'Truck',
            self::Toco => 'Toco',
            self::Vuc => 'VUC',
            self::Utility => 'Utilitário',
            self::Other => 'Outro',
        };
    }
}
