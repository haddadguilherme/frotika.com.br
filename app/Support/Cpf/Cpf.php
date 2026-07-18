<?php

declare(strict_types=1);

namespace App\Support\Cpf;

/**
 * Utilitário de CPF: normalização, validação dos dígitos verificadores e
 * formatação pt-BR. Fonte única para não duplicar a regra do checksum.
 */
final class Cpf
{
    /**
     * Mantém apenas os dígitos.
     */
    public static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Formata no padrão 000.000.000-00 a partir de qualquer entrada.
     */
    public static function format(string $value): string
    {
        $digits = self::digits($value);

        if (strlen($digits) !== 11) {
            return $digits;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2),
        );
    }

    /**
     * Valida os 11 dígitos e os dois dígitos verificadores.
     */
    public static function isValid(string $value): bool
    {
        $cpf = self::digits($value);

        if (preg_match('/^\d{11}$/', $cpf) !== 1) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        $firstDigit = self::checkDigit(substr($cpf, 0, 9), 10);
        $secondDigit = self::checkDigit(substr($cpf, 0, 10), 11);

        return $cpf[9] === (string) $firstDigit && $cpf[10] === (string) $secondDigit;
    }

    private static function checkDigit(string $base, int $startWeight): int
    {
        $sum = 0;
        $weight = $startWeight;

        foreach (str_split($base) as $digit) {
            $sum += ((int) $digit) * $weight;
            $weight--;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
