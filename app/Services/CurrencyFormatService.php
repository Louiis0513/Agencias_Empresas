<?php

namespace App\Services;

class CurrencyFormatService
{
    /** Monedas que no usan decimales (centavos) en uso cotidiano */
    private const NO_DECIMAL_CURRENCIES = ['COP', 'CLP', 'JPY'];

    /**
     * Formatea un monto según la moneda.
     * COP/CLP: 368.000 (punto miles, sin decimales)
     * USD/MXN: 368,000.00 (coma miles, 2 decimales)
     */
    public function format(float $amount, ?string $currency = 'COP'): string
    {
        $currency = strtoupper($currency ?? 'COP');
        $decimals = in_array($currency, self::NO_DECIMAL_CURRENCIES) ? 0 : 2;

        if ($decimals === 0) {
            return number_format((int) round($amount), 0, ',', '.');
        }

        return number_format($amount, 2, '.', ',');
    }

    /**
     * Formatea con símbolo de moneda (ej: $368.000)
     */
    public function formatWithSymbol(float $amount, ?string $currency = 'COP'): string
    {
        return $this->getSymbol($currency) . $this->format($amount, $currency);
    }

    public function getSymbol(?string $currency = 'COP'): string
    {
        return match (strtoupper($currency ?? 'COP')) {
            'USD', 'MXN', 'ARS', 'CLP', 'COP' => '$',
            'PEN' => 'S/',
            default => '$',
        };
    }

    /**
     * Parsea un valor formateado (ej: "16.000" o "16,000.00") a float.
     */
    public function parseFromFormatted(string $value, ?string $currency = 'COP'): float
    {
        $currency = strtoupper($currency ?? 'COP');
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        $noDecimals = in_array($currency, self::NO_DECIMAL_CURRENCIES);

        if ($noDecimals) {
            // COP: "16.000" → 16000 (quitar puntos de miles)
            $cleaned = str_replace('.', '', $value);
            $cleaned = str_replace(',', '', $cleaned);
        } else {
            // USD: "16,000.00" → 16000.00 (coma miles, punto decimal)
            $cleaned = str_replace(',', '', $value);
        }

        return (float) $cleaned;
    }
}
