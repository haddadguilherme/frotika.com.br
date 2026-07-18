<?php

declare(strict_types=1);

namespace App\Domain\Trips\Cte\Exceptions;

use RuntimeException;

final class InvalidCteException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('O arquivo enviado não é um XML de CT-e válido.');
    }

    public static function missingInfCte(): self
    {
        return new self('Não foi possível localizar o grupo infCte no XML.');
    }

    public static function missingAccessKey(): self
    {
        return new self('Não foi possível extrair a chave de acesso do CT-e.');
    }
}
