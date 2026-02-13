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
     * Detecta/identifica variantes en lotes: convierte features (array o null) a un string normalizado
     * para comparar variantes. Misma variante (ej. talla S, color Rojo) da el mismo key aunque
     * el orden de claves cambie. Convierte claves y valores a string para evitar diferencias por
     * tipo (int vs string) que causarían duplicados al crear batch_items desde compras vs creación de producto.
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
     * Resuelve el costo unitario para el registro de MovimientoInventario (reportes/valorización).
     * Batch ENTRADA: promedio ponderado de los items del lote.
     * Serializado ENTRADA: promedio de los costos por unidad.
     * SALIDA u otros: usa unit_cost explícito si se pasó.
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
     * Recalcula y actualiza el costo ponderado del producto desde la fuente de verdad.
     * Batch: SUM(qty × unit_cost) / total_qty de todos los batch_items.
     * Serializado: promedio de cost de los product_items disponibles.
     */
    /**
     * Recalcula el costo ponderado del producto desde la fuente de verdad.
     * Serializado: promedio de cost de los product_items disponibles.
     * Batch y simple: SUM(qty × unit_cost) / total_qty de todos los batch_items (simples usan Batch sin variantes).
     */
    public function actualizarCostoPonderado(Product $product): void
    {
        if ($product->isSerialized()) {
            $items = ProductItem::where('product_id', $product->id)
                ->where('store_id', $product->store_id)
                ->where('status', ProductItem::STATUS_AVAILABLE)
                ->get();
            $total = $items->sum('cost');
            $qty = $items->count();
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

            $type = $datos['type'];
            $quantity = (int) $datos['quantity'];
            if ($quantity < 1) {
                throw new Exception('La cantidad debe ser al menos 1.');
            }

            // Salida: validar stock disponible antes de modificar (usa validarStockDisponible → stockDisponible)
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

            // Productos simples: sin variantes, pero en inventario se tratan como lote (Batch + BatchItem)
            // para trazabilidad por compra y costo real (ponderado por entradas).
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
                        'batch_id'  => $batch->id,
                        'quantity'  => $quantity,
                        'unit_cost' => $unitCost,
                        'features'  => null,
                        'price'     => null,
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
                            ->where(function ($q) {
                                $q->where('is_active', true)->orWhereNull('is_active');
                            })
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
                $this->actualizarCostoPonderado($product);

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

            // Productos serializados o por lotes: lógica existente
            $allowedTypes = [MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH];
            if (! in_array($product->type, $allowedTypes)) {
                throw new Exception(
                    "El producto «{$product->name}» no es apto para inventario. Solo productos tipo «serialized» o «batch» tienen movimientos."
                );
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
                        throw new Exception('Para productos por lote se requieren los datos del lote (reference, items).');
                    }
                    $batchInfo = $datos['batch_data'];
                    $ref = trim($batchInfo['reference'] ?? '');
                    if ($ref === '') {
                        $ref = 'INI-' . date('Y');
                    }
                    $batchInfo['reference'] = $ref;
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
                        return self::detectorDeVariantesEnLotes($bi->features);
                    });

                    foreach ($batchInfo['items'] as $itemData) {
                        $qty = (int) ($itemData['quantity'] ?? 0);
                        if ($qty < 1) {
                            continue;
                        }
                        $features = $itemData['features'] ?? null;
                        $key = self::detectorDeVariantesEnLotes($features);
                        $unitCost = (float) ($itemData['cost'] ?? $itemData['unit_cost'] ?? $datos['unit_cost'] ?? 0);

                        if ($existingItems->has($key)) {
                            $existingItems->get($key)->increment('quantity', $qty);
                        } else {
                            $price = isset($itemData['price']) && $itemData['price'] !== '' && $itemData['price'] !== null
                                ? (float) $itemData['price'] : null;
                            $newItem = BatchItem::create([
                                'batch_id'  => $batch->id,
                                'quantity'  => $qty,
                                'unit_cost' => $unitCost,
                                'features'  => $features,
                                'price'     => $price,
                            ]);
                            $existingItems->put($key, $newItem);
                        }
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
            $this->actualizarCostoPonderado($product);

            return $mov;
        });
    }

    /**
     * Registra una salida de inventario por cantidad aplicando FIFO (primero en entrar, primero en salir).
     * Para productos por lote: descuenta de los batch_items más antiguos primero (orden por batch.created_at).
     * Para productos serializados: toma las primeras N unidades disponibles (orden por id).
     * Si el producto no es de inventario (serialized/batch), no hace nada.
     *
     * @param  string|null  $description  Ej: "Venta Factura #123"
     * @return void
     *
     * @throws Exception Si no hay stock suficiente o el producto no es apto para inventario
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
            // FIFO: batch_items activos con stock, ordenados por antigüedad del lote (batch.created_at)
            $batchItems = BatchItem::where('quantity', '>', 0)
                ->whereHas('batch', function ($q) use ($store, $productId) {
                    $q->where('store_id', $store->id)->where('product_id', $productId);
                })
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
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
                    "Stock insuficiente en «{$product->name}». No hay suficiente en los lotes disponibles. Solicitado: {$quantity}."
                );
            }
        } else {
            // Serializado: primeras N unidades disponibles (FIFO por id)
            $items = ProductItem::where('store_id', $store->id)
                ->where('product_id', $productId)
                ->where('status', ProductItem::STATUS_AVAILABLE)
                ->orderBy('id')
                ->limit($quantity)
                ->get();

            if ($items->count() < $quantity) {
                throw new Exception(
                    "Stock insuficiente en «{$product->name}» (serializado). Disponibles: {$items->count()}, solicitado: {$quantity}."
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
     * Registra salida de inventario por números de serie (productos serializados).
     * El cliente eligió qué unidades concretas vender; se descuentan esos ítems.
     *
     * @param  array  $serialNumbers  Números de serie a descontar (deben existir y estar AVAILABLE)
     * @throws Exception Si algún serial no existe o no está disponible
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
            throw new Exception("El producto «{$product->name}» no es serializado; use registro por cantidad.");
        }

        $this->registrarMovimiento($store, $userId, [
            'product_id'  => $productId,
            'type'        => MovimientoInventario::TYPE_SALIDA,
            'quantity'    => count($serialNumbers),
            'description' => $description,
        ], $serialNumbers);
    }

    /**
     * Salida por variante (producto lote): descuenta cantidad aplicando FIFO entre los BatchItems con esas features.
     * El carrito no necesita saber de qué lote salen las unidades; se descuentan por orden de antigüedad.
     *
     * @param  array  $features  Mapa atributo => valor de la variante
     * @throws Exception Si no hay stock suficiente en esa variante
     */
    public function registrarSalidaPorVarianteFIFO(Store $store, int $userId, int $productId, array $features, int $quantity, ?string $description = null): void
    {
        if ($quantity < 1) {
            return;
        }

        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $product->isBatch()) {
            throw new Exception("El producto «{$product->name}» no es por lote; no se puede usar salida por variante.");
        }

        $key = self::detectorDeVariantesEnLotes($features);
        $batchItems = BatchItem::where('quantity', '>', 0)
            ->whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $productId))
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->with('batch')
            ->get()
            ->filter(fn (BatchItem $bi) => self::detectorDeVariantesEnLotes($bi->features) === $key)
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
     * Retorna el stock disponible según el tipo de producto (consulta anatómica, sin modificar inventario).
     * Una sola función con todos los casos; sin redundancia.
     *
     * Casos (en orden de resolución):
     * 1. Serializado: product_id + serial_number → product_items, disponible/status.
     * 2. Lote por ítem concreto: product_id + batch_item_id → quantity de ese BatchItem.
     * 3. Lote por variante (features): product_id + variant_features → suma quantity de todos los BatchItems con esas features.
     * 4. Simple: solo product_id → campo stock de la tabla products.
     *
     * @param  int|null  $batchItemId  Lote: id de un BatchItem concreto (stock de ese ítem).
     * @param  string|null  $serialNumber  Serializado: número de serie.
     * @param  array|null  $variantFeatures  Lote: mapa atributo=>valor; retorna stock total de esa variante en todos los lotes.
     * @return array{disponible: bool, cantidad: int, status: string|null}
     */
    public function stockDisponible(Store $store, int $productId, ?int $batchItemId = null, ?string $serialNumber = null, ?array $variantFeatures = null): array
    {
        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->first();

        if (! $product) {
            return ['disponible' => false, 'cantidad' => 0, 'status' => null];
        }

        // Case 3: Serializado — se envía el serial, se busca en product_items y se retorna el status para saber si está disponible
        if ($serialNumber !== null && $serialNumber !== '') {
            if (! $product->isSerialized()) {
                return ['disponible' => false, 'cantidad' => 0, 'status' => null];
            }
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

        // Case 2: Lote por ítem concreto — batch_item_id → quantity de ese BatchItem
        if ($batchItemId !== null && $batchItemId > 0) {
            if (! $product->isBatch()) {
                return ['disponible' => false, 'cantidad' => 0, 'status' => null];
            }
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

        // Case 3: Lote por variante (features) — suma quantity de todos los BatchItems con esas features
        if ($variantFeatures !== null && is_array($variantFeatures) && ! empty($variantFeatures)) {
            if (! $product->isBatch()) {
                return ['disponible' => false, 'cantidad' => 0, 'status' => null];
            }
            $key = self::detectorDeVariantesEnLotes($variantFeatures);
            $batchItems = BatchItem::whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $product->id))
                ->where('quantity', '>', 0)
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->get();
            $cantidad = 0;
            foreach ($batchItems as $bi) {
                if (self::detectorDeVariantesEnLotes($bi->features) === $key) {
                    $cantidad += (int) $bi->quantity;
                }
            }
            return [
                'disponible' => $cantidad > 0,
                'cantidad'   => $cantidad,
                'status'     => null,
            ];
        }

        // Case 4: Simple — retorna el stock de la tabla producto
        $cantidad = (int) $product->stock;

        return [
            'disponible' => $cantidad > 0,
            'cantidad'   => $cantidad,
            'status'     => null,
        ];
    }

    /**
     * Retorna el precio unitario de venta para un ítem según su tipo (simple, batch, serialized).
     * Usado para enriquecer cotizaciones con el precio real actual del inventario.
     *
     * @param  array|null  $variantFeatures  Para batch: mapa atributo=>valor de la variante
     * @param  array|null  $serialNumbers  Para serialized: lista de números de serie (usa el primero para el precio)
     * @return float Precio unitario
     */
    public function precioParaItem(Store $store, int $productId, string $type, ?array $variantFeatures = null, ?array $serialNumbers = null): float
    {
        $product = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->first();

        if (! $product) {
            return 0.0;
        }

        $productPrice = (float) ($product->price ?? 0);

        // Serialized: precio del ProductItem (primer serial) o producto
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

        // Batch: precio del BatchItem con esas features. Construir mapa clave→BatchItem con la misma
        // función de clave para evitar desajustes (solo la primera variante encontraba precio).
        if ($type === 'batch' && is_array($variantFeatures) && ! empty($variantFeatures)) {
            $key = self::detectorDeVariantesEnLotes($variantFeatures);
            if ($key === '') {
                return $productPrice;
            }
            $batchItems = BatchItem::whereHas('batch', fn ($q) => $q->where('store_id', $store->id)->where('product_id', $productId))
                ->where('quantity', '>', 0)
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->with('batch.product')
                ->get();
            $keyToItem = [];
            foreach ($batchItems as $bi) {
                $biFeatures = $bi->features;
                if (! is_array($biFeatures)) {
                    $biFeatures = is_string($biFeatures) ? json_decode($biFeatures, true) : [];
                }
                $biKey = self::detectorDeVariantesEnLotes($biFeatures ?? []);
                if ($biKey !== '' && ! isset($keyToItem[$biKey])) {
                    $keyToItem[$biKey] = $bi;
                }
            }
            $batchItem = $keyToItem[$key] ?? null;
            if ($batchItem) {
                return $batchItem->getSellingPriceAttribute();
            }
            return $productPrice;
        }

        // Simple: precio del producto
        return $productPrice;
    }

    /**
     * Valida que haya stock disponible para todos los productos indicados.
     * Solo valida productos de inventario (serialized/batch). No modifica el inventario.
     * Para productos serializados puede recibir serial_numbers[] en lugar de quantity; entonces valida que esos seriales existan y estén disponibles.
     * Delega en stockDisponible() para la lógica de consulta por tipo (simple, lote, serializado).
     *
     * @param  array  $items  Lista de ítems: product_id + quantity O product_id + serial_numbers[] O product_id + batch_item_id + quantity O product_id + variant_features + quantity
     * @throws Exception Si algún producto no tiene stock suficiente o un serial no está disponible
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
                $variantFeatures = $item['variant_features'] ?? null;
                if (is_array($variantFeatures) && ! empty($variantFeatures)) {
                    $r = $this->stockDisponible($store, $productId, null, null, $variantFeatures);
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
     * Productos de la tienda aptos para movimientos de inventario (simple, serialized o batch).
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
     * Busca productos de inventario por término (para buscador en compras).
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
