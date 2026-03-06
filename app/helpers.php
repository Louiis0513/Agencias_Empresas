<?php

if (! function_exists('currency_symbol')) {
    function currency_symbol(?string $currency = 'COP'): string
    {
        return app(\App\Services\CurrencyFormatService::class)->getSymbol($currency);
    }
}

if (! function_exists('money')) {
    /**
     * Formatea un monto según la moneda de la tienda.
     * COP: $368.000 (sin decimales, punto miles)
     * USD: $368,000.00 (2 decimales, coma miles)
     */
    function money(float $amount, ?string $currency = 'COP', bool $withSymbol = true): string
    {
        $service = app(\App\Services\CurrencyFormatService::class);

        return $withSymbol
            ? $service->formatWithSymbol($amount, $currency)
            : $service->format($amount, $currency);
    }
}
