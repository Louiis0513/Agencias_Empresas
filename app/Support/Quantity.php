<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\Rule;

class Quantity
{
    public const SCALE = 2;

    public static function normalize(mixed $value): float
    {
        return round((float) $value, self::SCALE);
    }

    public static function format(mixed $value): string
    {
        return number_format(self::normalize($value), self::SCALE, '.', '');
    }

    public static function validationRulesForMode(string $mode, bool $required = true): array
    {
        $rules = $required ? ['required'] : ['nullable'];

        if ($mode === Product::QUANTITY_MODE_DECIMAL) {
            return array_merge($rules, [
                'numeric',
                'min:0.01',
                'regex:/^\d+(\.\d{1,2})?$/',
            ]);
        }

        return array_merge($rules, ['integer', 'min:1']);
    }

    public static function isValidForMode(mixed $value, string $mode, bool $allowZero = false): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        $qty = self::normalize($value);

        if ($mode === Product::QUANTITY_MODE_DECIMAL) {
            if (! preg_match('/^\d+(\.\d{1,2})?$/', (string) $value)) {
                return false;
            }
            return $allowZero ? $qty >= 0 : $qty >= 0.01;
        }

        if ((float) floor((float) $value) !== (float) $value) {
            return false;
        }

        return $allowZero ? $qty >= 0 : $qty >= 1;
    }

    public static function quantityModeRules(): array
    {
        return ['required', 'string', Rule::in([Product::QUANTITY_MODE_UNIT, Product::QUANTITY_MODE_DECIMAL])];
    }

    public static function stepRulesForMode(string $mode): array
    {
        if ($mode === Product::QUANTITY_MODE_DECIMAL) {
            return ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'];
        }

        return ['required', 'numeric', 'in:1,1.0,1.00'];
    }

    public static function normalizeStockForProduct(Product $product, mixed $stock): float
    {
        $mode = $product->usesDecimalQuantity()
            ? Product::QUANTITY_MODE_DECIMAL
            : Product::QUANTITY_MODE_UNIT;

        return self::normalizeStockByMode($mode, $stock);
    }

    public static function displayStockForProduct(Product $product, mixed $stock): string
    {
        $mode = $product->usesDecimalQuantity()
            ? Product::QUANTITY_MODE_DECIMAL
            : Product::QUANTITY_MODE_UNIT;

        return self::displayStockByMode($mode, $stock);
    }

    public static function normalizeStockByMode(string $mode, mixed $stock): float
    {
        if ($mode === Product::QUANTITY_MODE_DECIMAL) {
            return self::normalize($stock);
        }

        return (float) floor((float) $stock);
    }

    public static function displayStockByMode(string $mode, mixed $stock): string
    {
        $normalized = self::normalizeStockByMode($mode, $stock);
        if ($mode === Product::QUANTITY_MODE_DECIMAL) {
            return number_format($normalized, self::SCALE, '.', '');
        }

        return (string) ((int) $normalized);
    }
}
