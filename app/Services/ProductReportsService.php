<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Concentra los cálculos del informe de productos de la tienda (Top vendidos, mayor margen, etc.).
 * Nuevas tablas del informe: añadir métodos en esta clase, no un servicio por tabla.
 */
class ProductReportsService
{
    public const VENTAS_7D = '7d';

    public const VENTAS_1M = '1m';

    public const VENTAS_3M = '3m';

    public const VENTAS_SIEMPRE = 'siempre';

    /**
     * Top productos por unidades vendidas (facturas no anuladas; incluye pendientes de pago).
     *
     * @return Collection<int, array{nombre: string, sku: string|null, cantidad: int}>
     */
    public function topMasVendidos(Store $store, string $range = self::VENTAS_7D, int $limit = 10): Collection
    {
        if (! in_array($range, [self::VENTAS_7D, self::VENTAS_1M, self::VENTAS_3M, self::VENTAS_SIEMPRE], true)) {
            $range = self::VENTAS_7D;
        }

        [$desde, $hasta] = $this->dateRangeForVentas($store, $range);

        $lines = DB::table('invoice_details')
            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_details.product_id', '=', 'products.id')
            ->where('invoices.store_id', $store->id)
            ->where('invoices.status', '!=', 'VOID')
            ->whereNotNull('invoice_details.product_id')
            ->when($desde, fn ($q) => $q->where('invoices.created_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('invoices.created_at', '<=', $hasta))
            ->select([
                'invoice_details.product_id',
                'invoice_details.product_name',
                'invoice_details.quantity',
                'products.type',
                'products.name as product_base_name',
                'products.sku as product_sku',
            ])
            ->get();

        /** @var array<string, array{product_id: int, product_name: string, product_base_name: string, product_type: ?string, product_sku: ?string, qty: int}> $groups */
        $groups = [];

        foreach ($lines as $line) {
            $isSerialized = $line->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED;
            $key = $isSerialized
                ? 's:'.$line->product_id
                : 'o:'.$line->product_id."\0".$line->product_name;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'product_id' => (int) $line->product_id,
                    'product_name' => (string) $line->product_name,
                    'product_base_name' => (string) $line->product_base_name,
                    'product_type' => $line->type,
                    'product_sku' => $line->product_sku,
                    'qty' => 0,
                ];
            }
            $groups[$key]['qty'] += (int) $line->quantity;
        }

        uasort($groups, fn ($a, $b) => $b['qty'] <=> $a['qty']);
        $top = array_slice($groups, 0, $limit, true);

        if ($top === []) {
            return collect();
        }

        $productIds = collect($top)->pluck('product_id')->unique()->values();
        $products = Product::with('variants')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($top as $g) {
            $product = $products->get($g['product_id']);
            $nombre = $g['product_type'] === MovimientoInventario::PRODUCT_TYPE_SERIALIZED
                ? $g['product_base_name']
                : $g['product_name'];

            $sku = $this->resolveSkuForGroup($product, $g['product_type'], $g['product_name'], $g['product_sku']);

            $out[] = [
                'nombre' => $nombre,
                'sku' => $sku,
                'cantidad' => $g['qty'],
            ];
        }

        return collect($out);
    }

    /**
     * Top filas por % de margen bruto actual (sin rango de fechas): simples por producto,
     * lote por variante, serializado por unidad disponible en inventario.
     *
     * @return Collection<int, array{nombre: string, sku: string|null, costo: float|null, precio: float|null, margen_pct: float|null}>
     */
    public function topMayorMargen(Store $store, int $limit = 10): Collection
    {
        $candidates = [];

        $simples = Product::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('type', 'simple')->orWhereNull('type')->orWhere('type', '');
            })
            ->get();

        foreach ($simples as $p) {
            $precio = $p->price !== null ? (float) $p->price : null;
            $costo = $p->cost !== null ? (float) $p->cost : null;
            $sort = $this->marginPercentForSort($p->margin !== null ? (float) $p->margin : null, $costo, $precio);
            $candidates[] = [
                'nombre' => $p->name,
                'sku' => $p->sku,
                'costo' => $costo,
                'precio' => $precio,
                'margen_pct' => $this->marginPercentForDisplay($p->margin !== null ? (float) $p->margin : null, $costo, $precio),
                '_sort' => $sort,
            ];
        }

        $variants = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', function ($q) use ($store) {
                $q->where('store_id', $store->id)
                    ->where('is_active', true)
                    ->where('type', MovimientoInventario::PRODUCT_TYPE_BATCH);
            })
            ->with('product')
            ->get();

        foreach ($variants as $v) {
            $product = $v->product;
            if (! $product) {
                continue;
            }
            $nombre = $product->name;
            $dn = $v->display_name;
            if ($dn !== '' && $dn !== '—') {
                $nombre .= ' ('.$dn.')';
            }
            $precio = (float) $v->selling_price;
            $costo = $v->cost_reference !== null ? (float) $v->cost_reference : null;
            $margenStored = $v->margin !== null ? (float) $v->margin : null;
            $sort = $this->marginPercentForSort($margenStored, $costo, $precio);
            $candidates[] = [
                'nombre' => $nombre,
                'sku' => $v->sku ?: $product->sku,
                'costo' => $costo,
                'precio' => $precio,
                'margen_pct' => $this->marginPercentForDisplay($margenStored, $costo, $precio),
                '_sort' => $sort,
            ];
        }

        $items = ProductItem::query()
            ->where('store_id', $store->id)
            ->where('status', ProductItem::STATUS_AVAILABLE)
            ->whereHas('product', function ($q) use ($store) {
                $q->where('store_id', $store->id)
                    ->where('is_active', true)
                    ->where('type', MovimientoInventario::PRODUCT_TYPE_SERIALIZED);
            })
            ->with(['product.category.attributes'])
            ->get();

        foreach ($items as $pi) {
            $product = $pi->product;
            if (! $product) {
                continue;
            }
            $attrNames = $product->category
                ? $product->category->attributes->pluck('name', 'id')->all()
                : [];
            $featStr = ProductVariant::formatFeaturesWithAttributeNames($pi->features ?? [], $attrNames);
            $nombre = $product->name;
            if ($featStr !== '') {
                $nombre .= ' ('.$featStr.')';
            }
            $nombre .= ' — Serial: '.($pi->serial_number ?? '');

            $precio = $pi->price !== null ? (float) $pi->price : ($product->price !== null ? (float) $product->price : null);
            $costo = $pi->cost !== null ? (float) $pi->cost : ($product->cost !== null ? (float) $product->cost : null);
            $margenStored = $pi->margin !== null ? (float) $pi->margin : ($product->margin !== null ? (float) $product->margin : null);
            $sort = $this->marginPercentForSort($margenStored, $costo, $precio);
            $candidates[] = [
                'nombre' => $nombre,
                'sku' => $pi->serial_number ?: $product->sku,
                'costo' => $costo,
                'precio' => $precio,
                'margen_pct' => $this->marginPercentForDisplay($margenStored, $costo, $precio),
                '_sort' => $sort,
            ];
        }

        usort($candidates, function ($a, $b) {
            $cmp = $b['_sort'] <=> $a['_sort'];

            return $cmp !== 0 ? $cmp : strcmp($a['nombre'], $b['nombre']);
        });

        $slice = array_slice($candidates, 0, $limit);
        $out = [];
        foreach ($slice as $row) {
            unset($row['_sort']);
            $out[] = $row;
        }

        return collect($out);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon} En UTC para comparar con columnas de BD.
     */
    public function dateRangeForVentas(Store $store, string $range): array
    {
        $tz = ($store->timezone && trim((string) $store->timezone) !== '')
            ? $store->timezone
            : (string) config('app.timezone', 'UTC');

        $now = Carbon::now($tz);

        if ($range === self::VENTAS_SIEMPRE) {
            return [null, null];
        }

        $desdeLocal = match ($range) {
            self::VENTAS_7D => $now->copy()->subDays(7)->startOfDay(),
            self::VENTAS_1M => $now->copy()->subDays(30)->startOfDay(),
            self::VENTAS_3M => $now->copy()->subDays(90)->startOfDay(),
            default => $now->copy()->subDays(7)->startOfDay(),
        };
        $hastaLocal = $now->copy()->endOfDay();

        return [$desdeLocal->utc(), $hastaLocal->utc()];
    }

    private function resolveSkuForGroup(?Product $product, ?string $type, string $lineProductName, ?string $fallbackSku): ?string
    {
        if (! $product) {
            return $fallbackSku;
        }

        if ($type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED || $this->isSimpleProductType($type)) {
            return $product->sku;
        }

        if ($type !== MovimientoInventario::PRODUCT_TYPE_BATCH) {
            return $product->sku;
        }

        $prefix = $product->name.' (';
        $suffix = ')';
        if (! str_starts_with($lineProductName, $prefix) || ! str_ends_with($lineProductName, $suffix)) {
            return $product->sku;
        }

        $inner = substr($lineProductName, strlen($prefix), -strlen($suffix));

        foreach ($product->variants as $v) {
            $dn = $v->display_name;
            if ($dn === $inner || ($dn === '—' && $inner === '—')) {
                return $v->sku ?: $product->sku;
            }
        }

        return $product->sku;
    }

    private function isSimpleProductType(?string $type): bool
    {
        return $type === null || $type === '' || $type === 'simple';
    }

    /**
     * Orden: mayor margen primero; sin dato útil al final.
     */
    private function marginPercentForSort(?float $storedMargin, ?float $cost, ?float $price): float
    {
        if ($storedMargin !== null) {
            return $storedMargin;
        }
        if ($price !== null && $price > 0 && $cost !== null) {
            return (($price - $cost) / $price) * 100;
        }

        return -1e9;
    }

    private function marginPercentForDisplay(?float $storedMargin, ?float $cost, ?float $price): ?float
    {
        if ($storedMargin !== null) {
            return round($storedMargin, 2);
        }
        if ($price !== null && $price > 0 && $cost !== null) {
            return round((($price - $cost) / $price) * 100, 2);
        }

        return null;
    }
}
