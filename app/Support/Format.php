<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Cnpj\Cnpj;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Formatação pt-BR centralizada (seção 14.3 do blueprint). Nenhuma view usa
 * number_format solto — tudo passa por aqui, para que o mesmo valor leia igual
 * na tabela, no card e no DRE.
 *
 * O símbolo "R$" vem embutido em money(); em tabela/card o número usa
 * moneyDecimal() e o "R$" fica num <span class="unit"> à parte.
 */
final class Format
{
    /** Sinal de menos tipográfico (U+2212), não hífen. */
    private const MINUS = '−';

    /**
     * Valor monetário em centavos com símbolo. Negativo sempre com sinal;
     * sign=true força o "+" também nos positivos (para margens/deltas).
     */
    public static function money(int $cents, bool $sign = false): string
    {
        $prefix = '';

        if ($cents < 0) {
            $prefix = self::MINUS;
        } elseif ($sign) {
            $prefix = '+';
        }

        return $prefix.'R$ '.self::moneyDecimal(abs($cents) / 100);
    }

    /**
     * Número decimal pt-BR sem símbolo (para a coluna numérica com o "R$" à parte).
     */
    public static function moneyDecimal(float|int $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    /**
     * Litros com 3 casas — o cupom do posto tem fração de litro.
     */
    public static function liters(float|int $value): string
    {
        return self::moneyDecimal($value, 3).' L';
    }

    /**
     * Quilometragem inteira, agrupada por milhar.
     */
    public static function km(float|int $value): string
    {
        return number_format((float) $value, 0, ',', '.').' km';
    }

    /**
     * Consumo km/l com 2 casas. Sem tanque cheio anterior o consumo é null,
     * e null nunca é zero — vira travessão.
     */
    public static function consumption(float|int|null $value): string
    {
        if ($value === null) {
            return self::MINUS;
        }

        return self::moneyDecimal($value, 2).' km/l';
    }

    public static function percent(float|int $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.').'%';
    }

    public static function plate(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    /**
     * Telefone/celular/WhatsApp a partir dos dígitos gravados na base.
     * Celular (11 dígitos) vira (xx) x xxxx-xxxx; fixo (10 dígitos) vira
     * (xx) xxxx-xxxx. Outros tamanhos (dado incompleto ou legado) voltam só
     * com os dígitos — nunca inventa separador em cima de número torto.
     */
    public static function phone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) === 11) {
            return sprintf(
                '(%s) %s %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 1),
                substr($digits, 3, 4),
                substr($digits, 7, 4),
            );
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6, 4),
            );
        }

        return $digits;
    }

    public static function cnpj(string $value): string
    {
        return Cnpj::format($value);
    }

    public static function cpf(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

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
     * Chave de acesso de 44 dígitos em blocos de 4.
     */
    public static function cteKey(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return trim(chunk_split($digits, 4, ' '));
    }

    public static function date(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    public static function dateTime(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('d/m/Y H:i');
    }
}
