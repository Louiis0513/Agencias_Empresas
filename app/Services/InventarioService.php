<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use Exception;

class InventarioService
{
    /**
     * Registra un movimiento de inventario (ledger físico).
     *
     * @param  array{
     *   product_id: int,
     *   bodega_id?: int|null,
     *   fecha: string|\DateTimeInterface,
     *   clase_movimiento: string,
     *   direccion: string,
     *   cantidad: float|int|string,
     *   costo_unitario_entrada?: float|int|string|null,
     *   valor_entrada?: float|int|string|null,
     *   documento?: \Illuminate\Database\Eloquent\Model|null,
     *   documento_etiqueta?: string|null,
     *   descripcion?: string|null
     * }  $datos
     */
    public function registrarMovimiento(Store $store, int $userId, array $datos): MovimientoInventario
    {
        $productId = (int) ($datos['product_id'] ?? 0);
        $product = Product::query()
            ->where('store_id', $store->id)
            ->whereKey($productId)
            ->first();

        if (! $product) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        if (! $product->es_inventariable) {
            throw new Exception('El producto «'.$product->codigo.'» no es inventariable.');
        }

        $direccion = strtoupper(trim((string) ($datos['direccion'] ?? '')));
        if (! in_array($direccion, [MovimientoInventario::DIRECCION_ENTRADA, MovimientoInventario::DIRECCION_SALIDA], true)) {
            throw new Exception('La dirección del movimiento debe ser ENTRADA o SALIDA.');
        }

        $cantidad = round((float) ($datos['cantidad'] ?? 0), 4);
        if ($cantidad <= 0) {
            throw new Exception('La cantidad del movimiento debe ser mayor a 0.');
        }

        $bodegaId = $datos['bodega_id'] ?? null;
        if ($bodegaId) {
            $bodega = Bodega::query()->deStore($store)->whereKey((int) $bodegaId)->first();
            if (! $bodega) {
                throw new Exception('La bodega no pertenece a esta tienda.');
            }
            if (! $bodega->activo) {
                throw new Exception('La bodega «'.$bodega->codigo.'» está inactiva.');
            }
            $bodegaId = $bodega->id;
        } else {
            // Null = «Sin asignar» (estilo Siigo), aunque la tienda maneje bodegas.
            $bodegaId = null;
        }

        $costoUnitario = null;
        $valorEntrada = null;
        if ($direccion === MovimientoInventario::DIRECCION_ENTRADA) {
            $costoUnitario = isset($datos['costo_unitario_entrada'])
                ? round((float) $datos['costo_unitario_entrada'], 4)
                : null;
            $valorEntrada = isset($datos['valor_entrada'])
                ? round((float) $datos['valor_entrada'], 2)
                : null;

            if ($costoUnitario !== null && $costoUnitario < 0) {
                throw new Exception('El costo unitario de entrada no puede ser negativo.');
            }
            if ($valorEntrada === null && $costoUnitario !== null) {
                $valorEntrada = round($cantidad * $costoUnitario, 2);
            }
        }

        $documento = $datos['documento'] ?? null;

        return MovimientoInventario::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'bodega_id' => $bodegaId,
            'fecha' => $datos['fecha'],
            'clase_movimiento' => (string) ($datos['clase_movimiento'] ?? ''),
            'direccion' => $direccion,
            'cantidad' => $cantidad,
            'costo_unitario_entrada' => $costoUnitario,
            'valor_entrada' => $valorEntrada,
            'documento_type' => $documento ? $documento::class : null,
            'documento_id' => $documento?->getKey(),
            'documento_etiqueta' => $datos['documento_etiqueta'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'user_id' => $userId,
        ]);
    }

    public function tieneMovimientosBodega(Bodega $bodega): bool
    {
        return MovimientoInventario::query()
            ->where('store_id', $bodega->store_id)
            ->where('bodega_id', $bodega->id)
            ->exists();
    }

    /**
     * Stock total por producto (suma de todas las bodegas + Sin asignar).
     * ENTRADA suma, SALIDA resta.
     *
     * @param  list<int>  $productIds
     * @return array<int, float> product_id => stock
     */
    public function stockTotalPorProductos(Store $store, array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        if ($ids === []) {
            return [];
        }

        $rows = MovimientoInventario::query()
            ->deStore($store)
            ->whereIn('product_id', $ids)
            ->selectRaw(
                'product_id, COALESCE(SUM(CASE WHEN direccion = ? THEN cantidad ELSE -cantidad END), 0) as stock',
                [MovimientoInventario::DIRECCION_ENTRADA]
            )
            ->groupBy('product_id')
            ->pluck('stock', 'product_id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = round((float) ($rows[$id] ?? 0), 4);
        }

        return $out;
    }

    public function stockTotal(Store $store, Product $product): float
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        if (! $product->es_inventariable) {
            return 0.0;
        }

        return $this->stockTotalPorProductos($store, [$product->id])[$product->id] ?? 0.0;
    }

    /**
     * Desglose de stock por bodega (incluye «Sin asignar» si hay movimientos sin bodega).
     *
     * @return list<array{bodega_id: int|null, codigo: string, nombre: string, cantidad: float}>
     */
    public function stockPorBodega(Store $store, Product $product): array
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        if (! $product->es_inventariable) {
            return [];
        }

        $rows = MovimientoInventario::query()
            ->deStore($store)
            ->where('product_id', $product->id)
            ->selectRaw(
                'bodega_id, COALESCE(SUM(CASE WHEN direccion = ? THEN cantidad ELSE -cantidad END), 0) as stock',
                [MovimientoInventario::DIRECCION_ENTRADA]
            )
            ->groupBy('bodega_id')
            ->get();

        $bodegaIds = $rows->pluck('bodega_id')->filter()->map(fn ($id) => (int) $id)->all();
        $bodegas = $bodegaIds === []
            ? collect()
            : Bodega::query()->deStore($store)->whereIn('id', $bodegaIds)->get(['id', 'codigo', 'nombre'])->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $cantidad = round((float) $row->stock, 4);
            if (abs($cantidad) < 0.00005) {
                continue;
            }

            $bodegaId = $row->bodega_id !== null ? (int) $row->bodega_id : null;
            if ($bodegaId === null) {
                $out[] = [
                    'bodega_id' => null,
                    'codigo' => '—',
                    'nombre' => 'Sin asignar',
                    'cantidad' => $cantidad,
                ];

                continue;
            }

            $bodega = $bodegas->get($bodegaId);
            $out[] = [
                'bodega_id' => $bodegaId,
                'codigo' => (string) ($bodega?->codigo ?? $bodegaId),
                'nombre' => (string) ($bodega?->nombre ?? 'Bodega #'.$bodegaId),
                'cantidad' => $cantidad,
            ];
        }

        usort($out, function (array $a, array $b) {
            if ($a['bodega_id'] === null) {
                return 1;
            }
            if ($b['bodega_id'] === null) {
                return -1;
            }

            return strcmp($a['codigo'], $b['codigo']);
        });

        return $out;
    }

    /**
     * Stock del producto en una bodega concreta (null = «Sin asignar»).
     */
    public function stockEnBodega(Store $store, Product $product, ?int $bodegaId): float
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        if (! $product->es_inventariable) {
            return 0.0;
        }

        $q = MovimientoInventario::query()
            ->deStore($store)
            ->where('product_id', $product->id);

        if ($bodegaId === null) {
            $q->whereNull('bodega_id');
        } else {
            $q->where('bodega_id', $bodegaId);
        }

        $stock = $q->selectRaw(
            'COALESCE(SUM(CASE WHEN direccion = ? THEN cantidad ELSE -cantidad END), 0) as stock',
            [MovimientoInventario::DIRECCION_ENTRADA]
        )->value('stock');

        return round((float) ($stock ?? 0), 4);
    }
}
