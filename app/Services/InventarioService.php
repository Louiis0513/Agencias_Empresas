<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    /**
     * Actualiza el stock del producto según el tipo de movimiento.
     * ENTRADA: stock += quantity.
     * SALIDA: valida stock >= quantity, luego stock -= quantity.
     *
     * @throws Exception Si es SALIDA y no hay stock suficiente
     */
    public function actualizarStock(Product $product, string $type, int $quantity): void
    {
        if ($type === MovimientoInventario::TYPE_SALIDA) {
            if ($product->stock < $quantity) {
                throw new Exception(
                    "Stock insuficiente en «{$product->name}». Actual: {$product->stock}, solicitado: {$quantity}."
                );
            }
            $product->stock -= $quantity;
        } else {
            $product->stock += $quantity;
        }
        $product->save();
    }

    /**
     * Registra un movimiento de inventario y actualiza el stock.
     * Solo productos con type = 'producto' pueden tener movimientos.
     *
     * @throws Exception Si el producto no es tipo producto, o salida sin stock suficiente
     */
    public function registrarMovimiento(Store $store, int $userId, array $datos): MovimientoInventario
    {
        return DB::transaction(function () use ($store, $userId, $datos) {
            $product = Product::where('id', $datos['product_id'])
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->type !== MovimientoInventario::PRODUCT_TYPE_INVENTARIO) {
                throw new Exception(
                    "El producto «{$product->name}» no es de tipo producto. Solo los productos con type «producto» tienen movimientos de inventario."
                );
            }

            $type = $datos['type'];
            $quantity = (int) $datos['quantity'];
            if ($quantity < 1) {
                throw new Exception('La cantidad debe ser al menos 1.');
            }

            $mov = MovimientoInventario::create([
                'store_id'    => $store->id,
                'user_id'     => $userId,
                'product_id'  => $product->id,
                'purchase_id' => $datos['purchase_id'] ?? null,
                'type'        => $type,
                'quantity'    => $quantity,
                'description' => $datos['description'] ?? null,
                'unit_cost'   => isset($datos['unit_cost']) ? (float) $datos['unit_cost'] : null,
            ]);

            $this->actualizarStock($product, $type, $quantity);

            return $mov;
        });
    }

    /**
     * Lista movimientos de inventario con filtros.
     *
     * @param array $filtros product_id, type, fecha_desde, fecha_hasta, per_page
     */
    public function listarMovimientos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = MovimientoInventario::deTienda($store->id)
            ->with(['product:id,store_id,name,sku,stock', 'user:id,name'])
            ->orderByDesc('created_at');

        if (! empty($filtros['product_id'])) {
            $query->porProducto((int) $filtros['product_id']);
        }
        if (! empty($filtros['type'])) {
            $query->porTipo($filtros['type']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Productos de la tienda con type = 'producto' (aptos para movimientos de inventario).
     */
    public function productosConInventario(Store $store): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('store_id', $store->id)
            ->where('type', MovimientoInventario::PRODUCT_TYPE_INVENTARIO)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'sku', 'stock', 'cost']);
    }
}
