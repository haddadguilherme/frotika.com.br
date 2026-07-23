<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleFinancingType: string
{
    case BankLoan = 'bank_loan';
    case Consortium = 'consortium';
    case Leasing = 'leasing';

    public function label(): string
    {
        return match ($this) {
            self::BankLoan => 'Financiamento bancário',
            self::Consortium => 'Consórcio',
            self::Leasing => 'Leasing',
        };
    }
}
