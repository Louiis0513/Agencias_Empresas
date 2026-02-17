<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    /**
     * Normaliza features (array o null) a un string JSON determinista para comparar variantes.
     * Se mantiene como utilidad para migración y resolución de variantes por features.
     */
    public static function detectorDeVariantesEnLotes(?array $features): string
    {
        if ($features === null || $features === []) {
            return '';
        }
        $normalized = [];
        foreach ($features as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $key = is_numeric($k) ? (string) (int) $k : (string) $k;
            $normalized[$key] = (string) $v;
        }
        ksort($normalized, SORT_STRING);
        return json_encode($normalized);
    }

    /**
     * Busca o crea un ProductVariant a partir de un product_variant_id explícito
     * o de un array de features. Retorna el ProductVariant resuelto.
     *
     * @param  int  $productId
     * @param  int|null  $productVariantId  ID directo de la variante (preferido)
     * @param  array|null  $features  Features para buscar/crear si no hay ID
     * @return ProductVariant|null
     */
    public static function resolverVariante(int $productId, ?int $productVariantId = null, ?array $features = null): ?ProductVariant
    {
        if ($productVariantId) {
            return ProductVariant::where('id', $productVariantId)
                ->where('product_id', $productId)
                ->first();
        }

        if ($features === null || empty($features)) {
            return null;
        }

        $key = self::detectorDeVariantesEnLotes($features);
        if ($key === '') {
            return null;
        }

        $normalized = json_decode($key, true);

        // Buscar entre las variantes existentes
        $variants = ProductVariant::where('product_id', $productId)->get();
        foreach ($variants as $variant) {
            if ($variant->normalized_key === $key) {
                return $variant;
            }
        }

        // No encontrada: crear nueva
        return ProductVariant::create([
            'product_id' => $productId,
            'features' => $normalized,
            'cost_reference' => 0,
            'price' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Resuelve el costo unitario para el registro de MovimientoInventario.
     */
    protected function resolveUnitCostForMovement(array $datos, string $type, int $quantity, bool $isBatch): ?float
    {
        if (isset($datos['unit_cost']) && $datos['unit_cost'] !== '' && $datos['unit_cost'] !== null) {
            return (float) $datos['unit_cost'];
        }

        if ($type === MovimientoInventario::TYPE_ENTRADA && $quantity > 0) {
            if ($isBatch && ! empty($datos['batch_data']['items'])) {
                $totalCost = 0;
                foreach ($datos['batch_data']['items'] as $item) {
                    $qty = (int) ($item['quantity'] ?? 0);
                    $cost = (float) ($item['cost'] ?? $item['unit_cost'] ?? 0);
                    $totalCost += $qty * $cost;
                }
                return $totalCost > 0 ? round($totalCost / $quantity, 2) : null;
            }
            if (! empty($datos['serial_items'])) {
                $sum = 0;
                $count = 0;
                foreach ($datos['serial_items'] as $row) {
                    $c = (float) ($row['cost'] ?? 0);
                    $sum += $c;
                    $count++;
                }
                return $count > 0 ? round($sum / $count, 2) : null;
            }
        }

        return null;
    }

    /**
     * Actualiza el stock del producto según el tipo de movimiento.
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
     * Recalcula el costo ponderado del producto desde la fuente de verdad.
     */
    public function actualizarCostoPonderado(Product $product, ?array $entradaItems = null): void
    {
        if ($product->isSerialized()) {
            $items = ProductItem::where('product_id', $product->id)
                ->where('store_id', $product->store_id)
                ->where('status', ProductItem::STATUS_AVAILABLE)
                ->get();
            $total = $items->sum('cost');
            $qty = $items->count();
        } elseif ($product->isBatch() && ! empty($entradaItems)) {
            $newTotalQty = 0;
            $newTotalCost = 0.0;
            foreach ($entradaItems as $item) {
                $itemQty = (int) ($item['quantity'] ?? 0);
                if ($itemQty <= 0) {
                    continue;
                }
                $itemCost = (float) ($item['unit_cost'] ?? $item['cost'] ?? 0);
                $newTotalQty += $itemQty;
                $newTotalCost += $itemQty * $itemCost;
            }

            if ($newTotalQty > 0) {
                $currentStock = (int) $product->stock;
                $oldStock = max($currentStock - $newTotalQty, 0);
                $oldCost = (float) $product->cost;
                if ($currentStock > 0) {
                    if ($oldStock > 0) {
                        $product->cost = round((($oldStock * $oldCost) + $newTotalCost) / $currentStock, 2);
                    } else {
                        $product->cost = round($newTotalCost / $currentStock, 2);
                    }
                } else {
                    $product->cost = 0.0;
                }
                $product->save();
                $this->actualizarCostReferencePorVariantes($product, $entradaItems);
                return;
            }
        } elseif ($product->isBatch() || $product->type === 'simple' || empty($product->type)) {
            $total = BatchItem::whereHas('batch', function ($q) use ($product) {
                $q->where('product_id', $product->id)->where('store_id', $product->store_id);
            })->get()->sum(fn (BatchItem $bi) => $bi->quantity * (float) $bi->unit_cost);
            $qty = BatchItem::whereHas('batch', function ($q) use ($product) {
                $q->where('product_id', $product->id)->where('store_id', $product->store_id);
            })->sum('quantity');
        } else {
            return;
        }

        $product->cost = $qty > 0 ? (float) round($total / $qty, 2) : 0.0;
        $product->save();

        if ($product->isBatch()) {
            $this->actualizarCostReferencePorVariantes($product);
        }
    }

    /**
     * Actualiza cost_reference de cada variante que tenga batch_items.
     */
    protected function actualizarCostReferencePorVariantes(Product $product, ?array $entradaItems = null): void
    {
        if (! empty($entradaItems)) {
            $variantsToUpdate = [];
            foreach ($entradaItems as $item) {
                $variantId = (int) ($item['product_variant_id'] ?? 0);
                $qty = (int) ($item['quantity'] ?? 0);
                if ($variantId < 1 || $qty <= 0) {
                    continue;
                }
                $unitCost = (float) ($item['unit_cost'] ?? $item['cost'] ?? 0);
                if (! isset($variantsToUpdate[$variantId])) {
                    $variantsToUpdate[$variantId] = ['quantity' => 0, 'total_cost' => 0.0];
                }
                $variantsToUpdate[$variantId]['quantity'] += $qty;
                $variantsToUpdate[$variantId]['total_cost'] += $qty * $unitCost;
            }

            foreach ($variantsToUpdate as $variantId => $entry) {
                $variant = ProductVariant::where('id', $variantId)
                    ->where('product_id', $product->id)
                    ->first();
                if (! $variant) {
                    continue;
                }
                $newQty = $entry['quantity'];
                $newCost = $entry['total_cost'];
                if ($newQty <= 0) {
                    continue;
                }
                $currentVariantStock = (int) BatchItem::where('product_variant_id', $variantId)
                    ->whereHas('batch', fn ($q) => $q->where('product_id', $product->id)->where('store_id', $product->store_id))
                    ->sum('quantity');
                $oldVariantStock = max($currentVariantStock - $newQty, 0);
                $oldCostRef = (float) $variant->cost_reference;
                if ($currentVariantStock > 0) {
                    if ($oldVariantStock > 0) {
                        $avgCost = round((($oldVariantStock * $oldCostRef) + $newCost) / $currentVariantStock, 2);
                    } else {
                        $avgCost = round($newCost / $newQty, 2);
                    }
                } else {
                    $avgCost = 0.0;
                }
                $variant->update(['cost_reference' => $avgCost]);
            }

            return;
        }

        $variantTotals = BatchItem::select([
            'product_variant_id',
            DB::raw('SUM(quantity) as total_qty'),
            DB::raw('SUM(quantity * unit_cost) as total_cost'),
        ])
            ->whereNotNull('product_variant_id')
            ->whereHas('batch', function ($q) use ($product) {
                $q->where('product_id', $product->id)->where('store_id', $product->store_id);
            })
            ->groupBy('product_variant_id')
            ->get()
            ->each(function ($row) use ($product) {
                $variantId = (int) $row->product_variant_id;
                if ($variantId < 1 || (int) $row->total_qty === 0) {
                    return;
                }

                $avgCost = (float) round($row->total_cost / (int) $row->total_qty, 2);
                ProductVariant::where('id', $variantId)
                    ->where('product_id', $product->id)
                    ->update(['cost_reference' => $avgCost]);
            });
    }

    /**
     * Registra un movimiento de inventario.
     *
     * Batch ENTRADA: batch_data {reference, expiration_date?, items [{quantity, cost, product_variant_id|features}]}.
     * Batch SALIDA: batch_item_id (directo) o product_variant_id (FIFO dentro de la variante).
     */
    public function registrarMovimiento(Store $store, int $userId, array $datos, array $serialNumbers = []): MovimientoInventario
    {
        return DB::transaction(function () use ($store, $userId, $datos, $serialNumbers) {
            $product = Product::where('id', $datos['product_id'])
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();
            $resolvedEntradaItems = null;

            $type = $datos['type'];
            $quantity = (int) $datos['quantity'];
            if ($quantity < 1) {
                throw new Exception('La cantidad debe ser al menos 1.');
            }

            // Salida: validar stock disponible
            if ($type === MovimientoInventario::TYPE_SALIDA && $product->isProductoInventario()) {
                $itemsToValidate = [];
                if ($product->isSerialized()) {
                    $serials = array_values(array_filter(array_map('trim', $serialNumbers)));
                    if (count($serials) !== $quantity) {
                        throw new Exception(
                            "Producto serializado: se intentan mover {$quantity} unidades pero se enviaron " . count($serials) . " número(s) de serie."
                        );
                    }
                    $itemsToValidate[] = ['product_id' => $product->id, 'serial_numbers' => $serials];
                } elseif ($product->isBatch()) {
                    if (empty($datos['batch_item_id'])) {
                        throw new Exception('Para salida de un producto por lote se debe especificar el ID del ítem (variante) a descontar.');
                    }
                    $itemsToValidate[] = ['product_id' => $product->id, 'batch_item_id' => (int) $datos['batch_item_id'], 'quantity' => $quantity];
                } else {
                    $item = ['product_id' => $product->id, 'quantity' => $quantity];
                    if (! empty($datos['batch_item_id'])) {
                        $item['batch_item_id'] = (int) $datos['batch_item_id'];
                    }
                    $itemsToValidate[] = $item;
                }
                $this->validarStockDisponible($store, $itemsToValidate);
            }

            // Productos simples: sin variantes, lote para trazabilidad
            if ($product->type === 'simple' || empty($product->type)) {
                $unitCost = isset($datos['unit_cost']) && $datos['unit_cost'] !== '' && $datos['unit_cost'] !== null
                    ? (float) $datos['unit_cost']
                    : 0.0;

                if ($type === MovimientoInventario::TYPE_ENTRADA) {
                    $reference = trim($datos['reference'] ?? '');
                    if ($reference === '') {
                        $reference = 'INI-' . date('Y');
                    }
                    $batch = Batch::firstOrCreate(
                        [
                            'store_id'   => $store->id,
                            'product_id' => $product->id,
                            'reference'  => $reference,
                        ],
                        ['expiration_date' => null]
                    );
                    BatchItem::create([
                        'batch_id'           => $batch->id,
                        'product_variant_id' => null,
                        'quantity'           => $quantity,
                        'unit_cost'          => $unitCost,
                    ]);
                } else {
                    $batchItemId = $datos['batch_item_id'] ?? null;
                    if ($batchItemId) {
                        $batchItem = BatchItem::where('id', $batchItemId)
                            ->whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $product->id))
                            ->firstOrFail();
                        $batchItem->decrement('quantity', $quantity);
                    } else {
                        $batchItems = BatchItem::whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $product->id))
                            ->where('quantity', '>', 0)
                            ->with('batch')
                            ->get()
                            ->sortBy(fn (BatchItem $bi) => $bi->batch->created_at->format('Y-m-d H:i:s') . '-' . $bi->id);
                        $remaining = $quantity;
                        foreach ($batchItems as $bi) {
                            if ($remaining <= 0) {
                                break;
                            }
                            $take = min($bi->quantity, $remaining);
                            $bi->decrement('quantity', $take);
                            $remaining -= $take;
                        }
                    }
                }

                $this->actualizarStock($product, $type, $quantity);
                if ($type === MovimientoInventario::TYPE_ENTRADA) {
                    $this->actualizarCostoPonderado($product);
                }

                $mov = MovimientoInventario::create([
                    'store_id'    => $store->id,
                    'user_id'     => $userId,
                    'product_id'  => $product->id,
                    'purchase_id' => $datos['purchase_id'] ?? null,
                    'type'        => $type,
                    'quantity'    => $quantity,
                    'description' => $datos['description'] ?? null,
                    'unit_cost'   => $unitCost,
                ]);

                return $mov;
            }

            // Serializado o por lotes
            $allowedTypes = [MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH];
            if (! in_array($product->type, $allowedTypes)) {
                throw new Exception(
                    "El producto «{$product->name}» no es apto para inventario."
                );
            }

            $isSerialized = $product->isSerialized();

            if ($isSerialized) {
                if ($type === MovimientoInventario::TYPE_ENTRADA) {
                    $serialItems = $datos['serial_items'] ?? [];
                    if (empty($serialItems) || ! is_array($serialItems)) {
                        throw new Exception('Producto serializado: se requieren serial_items.');
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
                        $productItemData = [
                            'store_id'        => $store->id,
                            'product_id'      => $product->id,
                            'serial_number'   => $serial,
                            'cost'            => (float) ($row['cost'] ?? 0),
                            'status'          => ProductItem::STATUS_AVAILABLE,
                            'batch'           => $reference,
                            'expiration_date' => $row['expiration_date'] ?? null,
                            'features'        => $row['features'] ?? null,
                        ];
                        if (isset($row['price']) && $row['price'] !== '' && $row['price'] !== null) {
                            $productItemData['price'] = (float) $row['price'];
                        }
                        ProductItem::create($productItemData);
                    }
                } else {
                    $serials = array_values(array_filter(array_map('trim', $serialNumbers)));
                    foreach ($serials as $serial) {
                        $item = ProductItem::where('store_id', $store->id)
                            ->where('product_id', $product->id)
                            ->where('serial_number', $serial)
                            ->firstOrFail();
                        $item->update(['status' => ProductItem::STATUS_SOLD]);
                    }
                }
            } elseif ($product->isBatch()) {
                if ($type === MovimientoInventario::TYPE_ENTRADA) {
                    if (empty($datos['batch_data']) || ! is_array($datos['batch_data'])) {
                        throw new Exception('Para productos por lote se requieren los datos del lote.');
                    }
                    $resolvedEntradaItems = [];
                    $batchInfo = $datos['batch_data'];
                    $ref = trim($batchInfo['reference'] ?? '');
                    if ($ref === '') {
                        $ref = 'INI-' . date('Y');
                    }
                    $batchInfo['reference'] = $ref;
                    if (empty($batchInfo['items']) || ! is_array($batchInfo['items'])) {
                        throw new Exception('El lote debe contener una lista de items.');
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
                            'expiration_date' => $batchInfo['expiration_date'] ?? null,
                        ]
                    );
                    if (isset($batchInfo['expiration_date']) && $batchInfo['expiration_date']) {
                        $batch->update(['expiration_date' => $batchInfo['expiration_date']]);
                    }

                    // Agrupar batch_items existentes por product_variant_id
                    $existingItems = $batch->batchItems()->get()->keyBy('product_variant_id');

                    foreach ($batchInfo['items'] as $itemData) {
                        $qty = (int) ($itemData['quantity'] ?? 0);
                        if ($qty < 1) {
                            continue;
                        }
                        $unitCost = (float) ($itemData['cost'] ?? $itemData['unit_cost'] ?? $datos['unit_cost'] ?? 0);

                        // Resolver la variante: por ID directo o por features
                        $variantId = $itemData['product_variant_id'] ?? null;
                        $features = $itemData['features'] ?? null;
                        $variant = self::resolverVariante($product->id, $variantId ? (int) $variantId : null, $features);

                        if (! $variant) {
                            throw new Exception('No se pudo resolver la variante para un item del lote.');
                        }

                        if ($existingItems->has($variant->id)) {
                            $existingItems->get($variant->id)->increment('quantity', $qty);
                        } else {
                            $newItem = BatchItem::create([
                                'batch_id'           => $batch->id,
                                'product_variant_id' => $variant->id,
                                'quantity'           => $qty,
                                'unit_cost'          => $unitCost,
                            ]);
                            $existingItems->put($variant->id, $newItem);
                        }
                        $resolvedEntradaItems[] = [
                            'product_variant_id' => $variant->id,
                            'quantity' => $qty,
                            'unit_cost' => $unitCost,
                        ];
                    }
                } else {
                    $batchItem = BatchItem::where('id', $datos['batch_item_id'])
                        ->whereHas('batch', function ($q) use ($store, $product) {
                            $q->where('store_id', $store->id)->where('product_id', $product->id);
                        })
                        ->firstOrFail();
                    $batchItem->decrement('quantity', $quantity);
                }
            }

            $unitCostForMov = $this->resolveUnitCostForMovement($datos, $type, $quantity, $product->isBatch());

            $mov = MovimientoInventario::create([
                'store_id'    => $store->id,
                'user_id'     => $userId,
                'product_id'  => $product->id,
                'purchase_id' => $datos['purchase_id'] ?? null,
                'type'        => $type,
                'quantity'    => $quantity,
                'description' => $datos['description'] ?? null,
                'unit_cost'   => $unitCostForMov,
            ]);

            $this->actualizarStock($product, $type, $quantity);
            if ($type === MovimientoInventario::TYPE_ENTRADA) {
                $this->actualizarCostoPonderado($product, $resolvedEntradaItems);
            }

            return $mov;
        });
    }

    /**
     * Salida FIFO sin variante específica (productos simples o lote general).
     */
    public function registrarSalidaPorCantidadFIFO(Store $store, int $userId, int $productId, int $quantity, ?string $description = null): void
    {
        if ($quantity < 1) {
            return;
        }

        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $product->isProductoInventario()) {
            return;
        }

        if ($product->stock < $quantity) {
            throw new Exception(
                "Stock insuficiente en «{$product->name}». Actual: {$product->stock}, solicitado: {$quantity}."
            );
        }

        if ($product->isBatch() || $product->type === 'simple' || empty($product->type)) {
            $batchItems = BatchItem::where('quantity', '>', 0)
                ->whereHas('batch', function ($q) use ($store, $productId) {
                    $q->where('store_id', $store->id)->where('product_id', $productId);
                })
                ->with('batch')
                ->get()
                ->sortBy(fn (BatchItem $bi) => $bi->batch->created_at->format('Y-m-d H:i:s') . '-' . $bi->id);

            $remaining = $quantity;
            foreach ($batchItems as $batchItem) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min($batchItem->quantity, $remaining);
                $this->registrarMovimiento($store, $userId, [
                    'product_id'    => $productId,
                    'type'          => MovimientoInventario::TYPE_SALIDA,
                    'quantity'      => $take,
                    'description'   => $description,
                    'batch_item_id' => $batchItem->id,
                    'unit_cost'     => $batchItem->unit_cost,
                ], []);
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new Exception(
                    "Stock insuficiente en «{$product->name}». No hay suficiente en los lotes disponibles."
                );
            }
        } else {
            $items = ProductItem::where('store_id', $store->id)
                ->where('product_id', $productId)
                ->where('status', ProductItem::STATUS_AVAILABLE)
                ->orderBy('id')
                ->limit($quantity)
                ->get();

            if ($items->count() < $quantity) {
                throw new Exception(
                    "Stock insuficiente en «{$product->name}» (serializado)."
                );
            }

            $serialNumbers = $items->pluck('serial_number')->values()->all();
            $this->registrarMovimiento($store, $userId, [
                'product_id'  => $productId,
                'type'        => MovimientoInventario::TYPE_SALIDA,
                'quantity'    => $quantity,
                'description' => $description,
            ], $serialNumbers);
        }
    }

    /**
     * Salida por seriales (productos serializados).
     */
    public function registrarSalidaPorSeriales(Store $store, int $userId, int $productId, array $serialNumbers, ?string $description = null): void
    {
        $serialNumbers = array_values(array_filter(array_map('trim', $serialNumbers)));
        if (empty($serialNumbers)) {
            return;
        }

        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if (! $product->isSerialized()) {
            throw new Exception("El producto «{$product->name}» no es serializado.");
        }

        $this->registrarMovimiento($store, $userId, [
            'product_id'  => $productId,
            'type'        => MovimientoInventario::TYPE_SALIDA,
            'quantity'    => count($serialNumbers),
            'description' => $description,
        ], $serialNumbers);
    }

    /**
     * Salida por variante (producto lote): descuenta cantidad FIFO entre los BatchItems
     * de la variante identificada por product_variant_id.
     *
     * @param  int  $productVariantId  ID de la variante en product_variants
     * @throws Exception Si no hay stock suficiente
     */
    public function registrarSalidaPorVarianteFIFO(Store $store, int $userId, int $productId, int $productVariantId, int $quantity, ?string $description = null): void
    {
        if ($quantity < 1) {
            return;
        }

        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $product->isBatch()) {
            throw new Exception("El producto «{$product->name}» no es por lote.");
        }

        $batchItems = BatchItem::where('product_variant_id', $productVariantId)
            ->where('quantity', '>', 0)
            ->whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $productId))
            ->with('batch')
            ->get()
            ->sortBy(fn (BatchItem $bi) => $bi->batch->created_at->format('Y-m-d H:i:s') . '-' . $bi->id)
            ->values();

        $remaining = $quantity;
        foreach ($batchItems as $batchItem) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((int) $batchItem->quantity, $remaining);
            $this->registrarMovimiento($store, $userId, [
                'product_id'    => $productId,
                'type'          => MovimientoInventario::TYPE_SALIDA,
                'quantity'      => $take,
                'description'   => $description,
                'batch_item_id' => $batchItem->id,
                'unit_cost'     => $batchItem->unit_cost,
            ], []);
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new Exception(
                "Stock insuficiente en la variante seleccionada de «{$product->name}». Solicitado: {$quantity}."
            );
        }
    }

    /**
     * Stock disponible según tipo de producto.
     *
     * @param  int|null  $productVariantId  Para lote: ID de la variante
     */
    public function stockDisponible(Store $store, int $productId, ?int $batchItemId = null, ?string $serialNumber = null, ?int $productVariantId = null): array
    {
        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->first();

        if (! $product) {
            return ['disponible' => false, 'cantidad' => 0, 'status' => null];
        }

        $type = $product->type ?: 'simple';

        switch ($type) {
            case MovimientoInventario::PRODUCT_TYPE_SERIALIZED:
                return $this->consultarStockSerializado($store, $product, $serialNumber);

            case MovimientoInventario::PRODUCT_TYPE_BATCH:
                return $this->consultarStockLote($store, $product, $batchItemId, $productVariantId);

            case 'simple':
            default:
                if ($product->batches()->exists()) {
                    return $this->consultarStockLote($store, $product, null, null);
                }
                return $this->consultarStockSimple($product);
        }
    }

    protected function consultarStockSimple(Product $product): array
    {
        $cantidad = (int) $product->stock;
        return [
            'disponible' => $cantidad > 0,
            'cantidad'   => $cantidad,
            'status'     => null,
        ];
    }

    protected function consultarStockSerializado(Store $store, Product $product, ?string $serialNumber): array
    {
        if ($serialNumber !== null && $serialNumber !== '') {
            $item = ProductItem::where('store_id', $store->id)
                ->where('product_id', $product->id)
                ->where('serial_number', trim($serialNumber))
                ->first();

            if (! $item) {
                return ['disponible' => false, 'cantidad' => 0, 'status' => null];
            }

            $disponible = $item->status === ProductItem::STATUS_AVAILABLE;
            return [
                'disponible' => $disponible,
                'cantidad'   => $disponible ? 1 : 0,
                'status'     => $item->status,
            ];
        }

        $cantidadTotal = ProductItem::where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->where('status', ProductItem::STATUS_AVAILABLE)
            ->count();

        return [
            'disponible' => $cantidadTotal > 0,
            'cantidad'   => $cantidadTotal,
            'status'     => null,
        ];
    }

    /**
     * Stock por lote: por batch_item_id, por product_variant_id, o general.
     */
    protected function consultarStockLote(Store $store, Product $product, ?int $batchItemId, ?int $productVariantId): array
    {
        // Sub-caso 1: por batch_item_id concreto
        if ($batchItemId !== null && $batchItemId > 0) {
            $batchItem = BatchItem::where('id', $batchItemId)
                ->whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $product->id))
                ->first();

            if (! $batchItem) {
                return ['disponible' => false, 'cantidad' => 0, 'status' => null];
            }

            $cantidad = (int) $batchItem->quantity;
            return [
                'disponible' => $cantidad > 0,
                'cantidad'   => $cantidad,
                'status'     => null,
            ];
        }

        // Sub-caso 2: por product_variant_id
        if ($productVariantId !== null && $productVariantId > 0) {
            $cantidad = BatchItem::where('product_variant_id', $productVariantId)
                ->whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $product->id))
                ->where('quantity', '>', 0)
                ->sum('quantity');

            return [
                'disponible' => $cantidad > 0,
                'cantidad'   => (int) $cantidad,
                'status'     => null,
            ];
        }

        // Sub-caso 3: stock total
        $cantidadTotal = BatchItem::whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $product->id))
            ->where('quantity', '>', 0)
            ->sum('quantity');

        return [
            'disponible' => $cantidadTotal > 0,
            'cantidad'   => (int) $cantidadTotal,
            'status'     => null,
        ];
    }

    /**
     * Precio para un ítem según su tipo. Batch: lee de ProductVariant.
     */
    public function precioParaItem(Store $store, int $productId, string $type, ?int $productVariantId = null, ?array $serialNumbers = null): float
    {
        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->first();

        if (! $product) {
            return 0.0;
        }

        $productPrice = (float) ($product->price ?? 0);

        if ($type === 'serialized' && is_array($serialNumbers) && ! empty($serialNumbers)) {
            $serial = trim((string) $serialNumbers[0]);
            if ($serial !== '') {
                $item = ProductItem::where('store_id', $store->id)
                    ->where('product_id', $productId)
                    ->where('serial_number', $serial)
                    ->first();
                if ($item && $item->price !== null && (float) $item->price > 0) {
                    return (float) $item->price;
                }
            }
            return $productPrice;
        }

        if ($type === 'batch' && $productVariantId) {
            $variant = ProductVariant::where('id', $productVariantId)
                ->where('product_id', $productId)
                ->first();
            if ($variant) {
                return $variant->selling_price;
            }
            return $productPrice;
        }

        return $productPrice;
    }

    /**
     * Valida stock disponible para una lista de ítems.
     */
    public function validarStockDisponible(Store $store, array $items): void
    {
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId < 1) {
                continue;
            }

            $product = Product::where('id', $productId)
                ->where('store_id', $store->id)
                ->first();

            if (! $product) {
                throw new Exception("El producto #{$productId} no existe o no pertenece a esta tienda.");
            }

            if (! $product->isProductoInventario()) {
                continue;
            }

            $serialNumbers = $item['serial_numbers'] ?? null;
            if (is_array($serialNumbers) && ! empty($serialNumbers)) {
                foreach ($serialNumbers as $serial) {
                    $serial = trim((string) $serial);
                    if ($serial === '') {
                        continue;
                    }
                    $r = $this->stockDisponible($store, $productId, null, $serial);
                    if (! $r['disponible']) {
                        if ($r['status'] === null) {
                            throw new Exception("El serial «{$serial}» no existe en el inventario de «{$product->name}».");
                        }
                        throw new Exception("El ítem con serial «{$serial}» no está disponible (Estado: {$r['status']}).");
                    }
                }
            } else {
                $quantity = (int) ($item['quantity'] ?? 0);
                if ($quantity < 1) {
                    continue;
                }
                $productVariantId = $item['product_variant_id'] ?? null;
                if ($productVariantId) {
                    $r = $this->stockDisponible($store, $productId, null, null, (int) $productVariantId);
                } else {
                    $batchItemId = isset($item['batch_item_id']) && $item['batch_item_id'] !== '' ? (int) $item['batch_item_id'] : null;
                    $r = $this->stockDisponible($store, $productId, $batchItemId, null);
                }
                if ($r['cantidad'] < $quantity) {
                    $actual = $r['cantidad'];
                    throw new Exception(
                        "Stock insuficiente en «{$product->name}». Actual: {$actual}, solicitado: {$quantity}."
                    );
                }
            }
        }
    }

    /**
     * Lista movimientos de inventario con filtros.
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
     * Productos aptos para movimientos de inventario.
     */
    public function productosConInventario(Store $store): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('store_id', $store->id)
            ->where(function ($q) {
                $q->whereIn('type', ['simple', MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH])
                    ->orWhereNull('type')
                    ->orWhere('type', '');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'sku', 'stock', 'cost', 'type']);
    }

    /**
     * Busca productos de inventario por término.
     */
    public function buscarProductosInventario(Store $store, string $term, int $limit = 15): \Illuminate\Support\Collection
    {
        $query = Product::where('store_id', $store->id)
            ->whereIn('type', ['simple', MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH])
            ->where('is_active', true);

        if (strlen(trim($term)) >= 2) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . trim($term) . '%')
                    ->orWhere('sku', 'like', '%' . trim($term) . '%')
                    ->orWhere('barcode', 'like', '%' . trim($term) . '%');
            });
        }

        return $query->orderBy('name')->limit($limit)->get(['id', 'name', 'sku', 'stock', 'cost', 'type']);
    }
}
