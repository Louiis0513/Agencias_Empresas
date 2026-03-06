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

if (! function_exists('format_product_name_for_receipt')) {
    /**
     * Convierte product_name (formato admin) a descripción para recibo.
     * "Ambientador (Marca: Glade, Color: Morado)" → "Ambientador Glade Morado"
     * "Producto - Serial: SN1 (Marca: Y); Serial: SN2" → "Producto Y SN1 SN2"
     * Usado como fallback cuando receipt_description es null (facturas antiguas).
     */
    function format_product_name_for_receipt(?string $productName): string
    {
        if ($productName === null || $productName === '') {
            return '';
        }

        $base = $productName;
        $serials = [];

        // Extraer parte de seriales: " - Serial: X; Serial: Y" o " - Serial: X, Y"
        if (preg_match('/\s+-\s+Serial:\s*(.+)$/s', $base, $m)) {
            $serialPart = $m[1];
            $base = trim((string) preg_replace('/\s+-\s+Serial:\s*.+$/s', '', $base));

            // Extraer seriales: "SN1 (Marca: Y); SN2" → SN1, SN2
            $chunks = preg_split('/;\s*Serial:\s*/', $serialPart);
            foreach ($chunks as $chunk) {
                if (preg_match('/^([^\s(]+)/', trim($chunk), $sm)) {
                    $serials[] = $sm[1];
                }
            }
            if (empty($serials) && trim($serialPart) !== '') {
                $serials = array_map('trim', explode(',', $serialPart));
            }
        }

        // Extraer valores entre paréntesis: "Marca: Glade, Color: Morado" → Glade, Morado
        $values = [];
        if (preg_match('/\s*\(([^)]+)\)\s*$/', $base, $m)) {
            $base = trim((string) preg_replace('/\s*\([^)]+\)\s*$/', '', $base));
            $pairs = explode(',', $m[1]);
            foreach ($pairs as $pair) {
                if (preg_match('/:\s*(.+)$/', trim($pair), $pm)) {
                    $values[] = trim($pm[1]);
                }
            }
        }

        $parts = array_filter([$base, implode(' ', $values), implode(' ', $serials)]);

        return trim(implode(' ', $parts));
    }
}
