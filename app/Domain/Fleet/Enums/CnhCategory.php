<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum CnhCategory: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case AB = 'AB';
    case AC = 'AC';
    case AD = 'AD';
    case AE = 'AE';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A — Motocicletas',
            self::B => 'B — Automóveis',
            self::C => 'C — Caminhões',
            self::D => 'D — Ônibus',
            self::E => 'E — Combinação de veículos',
            self::AB => 'AB — Moto + automóvel',
            self::AC => 'AC — Moto + caminhão',
            self::AD => 'AD — Moto + ônibus',
            self::AE => 'AE — Moto + combinação',
        };
    }
}
