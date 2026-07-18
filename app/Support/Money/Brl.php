<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Conversao de entrada em reais (formulario pt-BR) para centavos inteiros.
 * Dinheiro nunca vira float na base (regra 1); esta e a unica porta de entrada
 * de um valor digitado para o inteiro em centavos que o dominio espera.
 */
final class Brl
{
    public static function toCents(int|string|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $raw = trim($value);

        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.-]/', '', $raw) ?? '';

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            // pt-BR: ponto e milhar, virgula e decimal.
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (int) round(((float) $normalized) * 100);
    }
}
