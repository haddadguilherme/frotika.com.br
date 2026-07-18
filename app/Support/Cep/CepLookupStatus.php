<?php

declare(strict_types=1);

namespace App\Support\Cep;

enum CepLookupStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Found => 'Endereço encontrado',
            self::NotFound => 'CEP não encontrado',
            self::Unavailable => 'Consulta indisponível no momento',
        };
    }
}
