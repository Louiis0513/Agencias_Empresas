<?php

namespace App\Support;

/**
 * Convierte montos a texto en español (mayúsculas) para comprobantes.
 */
final class MoneyToWordsEs
{
    private const UNIDADES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];

    private const ESPECIALES = [
        10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
        16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
    ];

    public static function moneyToWords(float $amount, string $currency = 'COP'): string
    {
        $amount = round($amount, 2);
        if ($amount < 0) {
            $amount = abs($amount);
        }

        $entero = (int) floor($amount);
        $frac = (int) round(($amount - $entero) * 100);

        $texto = mb_strtoupper(self::enteroEnLetras($entero), 'UTF-8');
        if ($entero === 1) {
            $texto = 'UN';
        }

        $moneda = match ($currency) {
            'USD' => $entero === 1 ? 'DÓLAR' : 'DÓLARES',
            default => $entero === 1 ? 'PESO' : 'PESOS',
        };

        $out = trim($texto.' '.$moneda);
        if ($frac > 0) {
            $out .= ' CON '.str_pad((string) $frac, 2, '0', STR_PAD_LEFT).'/100';
        }

        return $out;
    }

    private static function enteroEnLetras(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }

        return trim(self::millones($n));
    }

    /** 1_000_000 en adelante */
    private static function millones(int $n): string
    {
        if ($n >= 1_000_000) {
            $m = intdiv($n, 1_000_000);
            $r = $n % 1_000_000;
            $izq = $m === 1 ? 'un millón' : trim(self::miles($m)).' millones';

            return $r ? $izq.' '.trim(self::miles($r)) : $izq;
        }

        return self::miles($n);
    }

    /** 1_000 .. 999_999 */
    private static function miles(int $n): string
    {
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            $r = $n % 1000;
            $izq = $k === 1 ? 'mil' : trim(self::sub1000($k)).' mil';

            return $r ? $izq.' '.trim(self::sub1000($r)) : $izq;
        }

        return self::sub1000($n);
    }

    private static function sub1000(int $n): string
    {
        if ($n === 0) {
            return '';
        }
        if ($n >= 100) {
            $c = intdiv($n, 100);
            $rest = $n % 100;
            $centenas = match ($c) {
                1 => $rest === 0 ? 'cien' : 'ciento',
                2 => 'doscientos',
                3 => 'trescientos',
                4 => 'cuatrocientos',
                5 => 'quinientos',
                6 => 'seiscientos',
                7 => 'setecientos',
                8 => 'ochocientos',
                default => 'novecientos',
            };

            return $rest ? $centenas.' '.self::sub100($rest) : $centenas;
        }

        return self::sub100($n);
    }

    private static function sub100(int $n): string
    {
        if ($n >= 10 && $n <= 19) {
            return self::ESPECIALES[$n];
        }
        if ($n === 20) {
            return 'veinte';
        }
        if ($n > 20 && $n < 30) {
            return 'veinti'.self::UNIDADES[$n - 20];
        }
        if ($n === 30) {
            return 'treinta';
        }
        if ($n > 30 && $n < 40) {
            return 'treinta y '.self::UNIDADES[$n - 30];
        }
        if ($n === 40) {
            return 'cuarenta';
        }
        if ($n > 40 && $n < 50) {
            return 'cuarenta y '.self::UNIDADES[$n - 40];
        }
        if ($n === 50) {
            return 'cincuenta';
        }
        if ($n > 50 && $n < 60) {
            return 'cincuenta y '.self::UNIDADES[$n - 50];
        }
        if ($n === 60) {
            return 'sesenta';
        }
        if ($n > 60 && $n < 70) {
            return 'sesenta y '.self::UNIDADES[$n - 60];
        }
        if ($n === 70) {
            return 'setenta';
        }
        if ($n > 70 && $n < 80) {
            return 'setenta y '.self::UNIDADES[$n - 70];
        }
        if ($n === 80) {
            return 'ochenta';
        }
        if ($n > 80 && $n < 90) {
            return 'ochenta y '.self::UNIDADES[$n - 80];
        }
        if ($n === 90) {
            return 'noventa';
        }
        if ($n > 90 && $n < 100) {
            return 'noventa y '.self::UNIDADES[$n - 90];
        }

        return self::UNIDADES[$n];
    }
}
