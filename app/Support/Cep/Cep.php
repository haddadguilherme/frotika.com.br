<?php

declare(strict_types=1);

namespace App\Support\Cep;

/**
 * Utilitário de CEP: normalização, validação de formato (8 dígitos) e
 * formatação pt-BR. Fonte única para não duplicar a regra em vários lugares.
 */
final class Cep
{
    /**
     * Mantém apenas os dígitos.
     */
    public static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Formata no padrão 00000-000 a partir de qualquer entrada.
     */
    public static function format(string $value): string
    {
        $digits = self::digits($value);

        if (strlen($digits) !== 8) {
            return $digits;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
    }

    /**
     * Valida o formato: exatamente 8 dígitos. O CEP não tem dígito verificador,
     * então a existência é confirmada apenas pela consulta ao webservice.
     */
    public static function isValid(string $value): bool
    {
        return preg_match('/^\d{8}$/', self::digits($value)) === 1;
    }
}
