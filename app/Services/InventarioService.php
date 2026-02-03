<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    /**
     * Normaliza features (array o null) a string para comparar variantes en lotes.
     * Misma variante (ej. talla S) da el mismo key aunque el orden de claves cambie.
     */
    public static function normalizeFeaturesForComparison(?array $features): string
    {
        if ($features === null || $features === []) {
            return '';
        }
        ksort($features);
        return json_encode($features);
    }

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
     * Registra un movimiento de inventario.
     * Arquitectura binaria: Serializado (product_items) o Por lotes (batches + batch_items).
     * Regla de oro: Nada entra sin referencia de compra (o INI-YYYY para carga inicial).
     *
     * @param  array  $datos  product_id, type, quantity, description, purchase_id(opcional).
     *                        Serializado ENTRADA: serial_items [{serial_number, cost, features}], reference (obligatorio).
     *                        Batch ENTRADA: batch_data {reference, expiration_date?, items [{quantity, cost, features}]}.
     * @param  array  $serialNumbers  Serializado SALIDA: lista de seriales a descontar.
     */
    public function registrarMovimiento(Store $store, int $userId, array $datos, array $serialNumbers = []): MovimientoInventario
    {
        return DB::transaction(function () use ($store, $userId, $datos, $serialNumbers) {
            $product = Product::where('id', $datos['product_id'])
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowedTypes = [MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH];
            if (! in_array($product->type, $allowedTypes)) {
                throw new Exception(
                    "El producto «{$product->name}» no es apto para inventario. Solo productos tipo «serialized» o «batch» tienen movimientos."
                );
            }

            $type = $datos['type'];
            $quantity = (int) $datos['quantity'];
            if ($quantity < 1) {
                throw new Exception('La cantidad debe ser al menos 1.');
            }

            $isSerialized = $product->isSerialized();

            if ($isSerialized) {
                if ($type === MovimientoInventario::TYPE_ENTRADA) {
                    $serialItems = $datos['serial_items'] ?? [];
                    if (empty($serialItems) || ! is_array($serialItems)) {
                        throw new Exception('Producto serializado: se requieren serial_items (serial, cost, features por unidad).');
                    }
                    $reference = trim($datos['reference'] ?? '');
                    if ($reference === '') {
                        $reference = 'INI-' . date('Y');
                    }
                    $serialsSeen = [];
                    foreach ($serialItems as $row) {
                        $serial = trim($row['serial_number'] ?? '');
                        if ($serial === '') {
                            throw new Exception('Cada unidad debe tener un número de serie.');
                        }
                        if (isset($serialsSeen[$serial])) {
                            throw new Exception("El serial «{$serial}» está duplicado.");
                        }
                        $serialsSeen[$serial] = true;
                        $exists = ProductItem::where('store_id', $store->id)
                            ->where('product_id', $product->id)
                            ->where('serial_number', $serial)
                            ->exists();
                        if ($exists) {
                            throw new Exception("El serial «{$serial}» ya existe en el inventario.");
                        }
                        ProductItem::create([
                            'store_id'        => $store->id,
                            'product_id'      => $product->id,
                            'serial_number'   => $serial,
                            'cost'            => (float) ($row['cost'] ?? 0),
                            'status'          => ProductItem::STATUS_AVAILABLE,
                            'batch'           => $reference,
                            'expiration_date' => $row['expiration_date'] ?? null,
                            'features'        => $row['features'] ?? null,
                        ]);
                    }
                } else {
                    $serials = array_values(array_filter(array_map('trim', $serialNumbers)));
                    if (count($serials) !== $quantity) {
                        throw new Exception(
                            "Producto serializado: se intentan mover {$quantity} unidades pero se enviaron " . count($serials) . " número(s) de serie."
                        );
                    }
                    foreach ($serials as $serial) {
                        $item = ProductItem::where('store_id', $store->id)
                            ->where('product_id', $product->id)
                            ->where('serial_number', $serial)
                            ->first();
                        if (! $item) {
                            throw new Exception("El serial «{$serial}» no existe en el inventario.");
                        }
                        if ($item->status !== ProductItem::STATUS_AVAILABLE) {
                            throw new Exception("El ítem con serial «{$serial}» no está disponible (Estado: {$item->status}).");
                        }
                        $item->update(['status' => ProductItem::STATUS_SOLD]);
                    }
                }
            } elseif ($product->isBatch()) {
                if (empty($datos['batch_data']) || ! is_array($datos['batch_data'])) {
                    throw new Exception('Para productos por lote se requieren los datos del lote (reference, items).');
                }
                $batchInfo = $datos['batch_data'];
                $ref = trim($batchInfo['reference'] ?? '');
                if ($ref === '') {
                    $ref = 'INI-' . date('Y');
                }
                $batchInfo['reference'] = $ref;

                if ($type === MovimientoInventario::TYPE_ENTRADA) {
                    if (empty($batchInfo['items']) || ! is_array($batchInfo['items'])) {
                        throw new Exception('El lote debe contener una lista de items con variantes y cantidades.');
                    }
                    $sumItems = array_sum(array_column($batchInfo['items'], 'quantity'));
                    if ($sumItems !== $quantity) {
                        throw new Exception("La suma de cantidades del lote ({$sumItems}) no coincide con la cantidad del movimiento ({$quantity}).");
                    }

                    $batch = Batch::firstOrCreate(
                        [
                            'store_id'   => $store->id,
                            'product_id' => $product->id,
                            'reference'  => $batchInfo['reference'],
                        ],
                        [
                            'expiration_date' => isset($batchInfo['expiration_date']) ? $batchInfo['expiration_date'] : null,
                        ]
                    );
                    if (isset($batchInfo['expiration_date']) && $batchInfo['expiration_date']) {
                        $batch->update(['expiration_date' => $batchInfo['expiration_date']]);
                    }

                    $existingItems = $batch->batchItems()->get()->keyBy(function (BatchItem $bi) {
                        return self::normalizeFeaturesForComparison($bi->features);
                    });

                    foreach ($batchInfo['items'] as $itemData) {
                        $qty = (int) ($itemData['quantity'] ?? 0);
                        if ($qty < 1) {
                            continue;
                        }
                        $features = $itemData['features'] ?? null;
                        $key = self::normalizeFeaturesForComparison($features);
                        $unitCost = (float) ($itemData['cost'] ?? $itemData['unit_cost'] ?? $datos['unit_cost'] ?? 0);

                        if ($existingItems->has($key)) {
                            $existingItems->get($key)->increment('quantity', $qty);
                        } else {
                            $newItem = BatchItem::create([
                                'batch_id'  => $batch->id,
                                'quantity'  => $qty,
                                'unit_cost' => $unitCost,
                                'features'  => $features,
                            ]);
                            $existingItems->put($key, $newItem);
                        }
                    }
                } else {
                    if (empty($datos['batch_item_id'])) {
                        throw new Exception('Para salida de un producto por lote se debe especificar el ID del ítem (variante) a descontar.');
                    }
                    $batchItem = BatchItem::where('id', $datos['batch_item_id'])
                        ->whereHas('batch', function ($q) use ($store, $product) {
                            $q->where('store_id', $store->id)->where('product_id', $product->id);
                        })
                        ->firstOrFail();

                    if ($batchItem->quantity < $quantity) {
                        throw new Exception("Stock insuficiente en el lote/variante seleccionado. Disponible: {$batchItem->quantity}, solicitado: {$quantity}.");
                    }
                    $batchItem->decrement('quantity', $quantity);
                }
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
     * Productos de la tienda aptos para movimientos de inventario (serialized o batch).
     */
    public function productosConInventario(Store $store): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('store_id', $store->id)
            ->whereIn('type', [MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'sku', 'stock', 'cost', 'type']);
    }

    /**
     * Busca productos de inventario por término (para buscador en compras).
     */
    public function buscarProductosInventario(Store $store, string $term, int $limit = 15): \Illuminate\Support\Collection
    {
        $query = Product::where('store_id', $store->id)
            ->whereIn('type', [MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH])
            ->where('is_active', true);

        if (strlen(trim($term)) >= 2) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . trim($term) . '%')
                    ->orWhere('sku', 'like', '%' . trim($term) . '%')
                    ->orWhere('barcode', 'like', '%' . trim($term) . '%');
            });
        }

        return $query->orderBy('name')->limit($limit)->get(['id', 'name', 'sku', 'stock', 'cost']);
    }
}
