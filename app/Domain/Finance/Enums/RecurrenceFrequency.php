<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum RecurrenceFrequency: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Weekly => 'Semanal',
            self::Yearly => 'Anual',
        };
    }
}
