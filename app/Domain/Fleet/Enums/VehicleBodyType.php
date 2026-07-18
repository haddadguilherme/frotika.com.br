<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleBodyType: string
{
    case Sider = 'sider';
    case Bau = 'bau';
    case Graneleiro = 'graneleiro';
    case Tanque = 'tanque';
    case Prancha = 'prancha';
    case Frigorifico = 'frigorifico';
    case Cacamba = 'cacamba';
    case PortaContainer = 'porta_container';

    public function label(): string
    {
        return match ($this) {
            self::Sider => 'Sider',
            self::Bau => 'Baú',
            self::Graneleiro => 'Graneleiro',
            self::Tanque => 'Tanque',
            self::Prancha => 'Prancha',
            self::Frigorifico => 'Frigorífico',
            self::Cacamba => 'Caçamba',
            self::PortaContainer => 'Porta-contêiner',
        };
    }
}
