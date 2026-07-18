<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum BankAccountType: string
{
    case Cash = 'cash';
    case Checking = 'checking';
    case Savings = 'savings';
    case Digital = 'digital';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Caixa (dinheiro)',
            self::Checking => 'Conta corrente',
            self::Savings => 'Poupança',
            self::Digital => 'Conta digital',
            self::Other => 'Outra',
        };
    }
}
