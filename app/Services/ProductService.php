<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductService
{
    /**
     * Crea un nuevo producto en la tienda.
     * - Categoría obligatoria.
     * - La categoría debe tener al menos un atributo asignado.
     * - Valida que los atributos requeridos estén llenos.
     * - Se guardan los valores de atributos (attribute_values) del producto.
     * - Para productos tipo batch: puede recibir 'variants' y 'attribute_option_ids'.
     * - Para productos tipo serialized: puede recibir 'serializedItems'.
     * - Para productos simples con stock inicial: puede recibir 'has_initial_stock' y llamará a InventarioService.
     * 
     * @param int|null $userId ID del usuario que crea el producto (necesario para movimientos de inventario)
     */
    public function createProduct(Store $store, array $data, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($store, $data, $userId) {
            $categoryId = $data['category_id'] ?? null;
            if (! $categoryId) {
                throw new Exception('Debes seleccionar una categoría para el producto.');
            }

            $category = Category::where('id', $categoryId)
                ->where('store_id', $store->id)
                ->with('attributes')
                ->firstOrFail();

            if ($category->attributes->isEmpty()) {
                throw new Exception("La categoría «{$category->name}» no tiene atributos. Asigna atributos a la categoría antes de crear productos.");
            }

            $data['store_id'] = $store->id;
            $attributeValues = $data['attribute_values'] ?? [];
            $proveedorIds = $data['proveedor_ids'] ?? null;
            $variants = $data['variants'] ?? null;
            // Serializado: normalizar a array (vacío si no hay stock inicial)
            $serializedItems = isset($data['serializedItems']) && is_array($data['serializedItems']) ? $data['serializedItems'] : [];
            $attributeOptionIds = $data['attribute_option_ids'] ?? null;
            $hasInitialStock = $data['has_initial_stock'] ?? false;
            $simpleInitialStockQty = 0;
            
            unset($data['attribute_values'], $data['proveedor_ids'], $data['variants'], $data['serializedItems'], $data['attribute_option_ids'], $data['has_initial_stock']);

            // Validar que los atributos requeridos estén llenos
            // Solo para productos simples: los atributos están a nivel de producto
            // Para batch y serialized: los atributos están en las variantes/unidades
            $productType = $data['type'] ?? null;
            $isSimpleProduct = ($productType === 'simple' || empty($productType));
            $isBatchProduct = ($productType === \App\Models\MovimientoInventario::PRODUCT_TYPE_BATCH);
            $isSerializedProduct = ($productType === \App\Models\MovimientoInventario::PRODUCT_TYPE_SERIALIZED);
            
            if ($isSimpleProduct) {
                $this->validateRequiredAttributes($category, $attributeValues);
            } elseif ($isBatchProduct && ! empty($variants)) {
                // Validar atributos requeridos en cada variante
                $this->validateRequiredAttributesInVariants($category, $variants);
            } elseif ($isSerializedProduct && ! empty($serializedItems)) {
                // Validar atributos requeridos en cada unidad serializada
                $this->validateRequiredAttributesInSerializedItems($category, $serializedItems);
            }

            // Evitar duplicar stock: el producto se crea con stock=0 y el movimiento lo incrementa.
            // Para productos simples con stock inicial
            $isSimpleProduct = (($data['type'] ?? null) === 'simple' || empty($data['type'] ?? null));
            if ($isSimpleProduct && $hasInitialStock) {
                $simpleInitialStockQty = (int) ($data['stock'] ?? 0);
                $data['stock'] = 0;
            }
            
            // Para productos batch y serialized: siempre se crean con stock=0
            // El stock se actualiza cuando se crean los Batch/BatchItem o ProductItems a través de InventarioService
            if (($data['type'] ?? null) === \App\Models\MovimientoInventario::PRODUCT_TYPE_BATCH || 
                ($data['type'] ?? null) === \App\Models\MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
                $data['stock'] = 0;
            }

            $product = Product::create($data);

            if ($proveedorIds !== null) {
                $this->syncProveedores($product, $store, $proveedorIds);
            }

            // Guardar valores de atributos del producto (para productos simples)
            foreach ($attributeValues as $attributeId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                ProductAttributeValue::create([
                    'product_id' => $product->id,
                    'attribute_id' => $attributeId,
                    'value' => (string) $value,
                ]);
            }

            // Para productos tipo batch: guardar opciones permitidas y crear lotes si hay variantes con stock
            if ($product->isBatch()) {
                if ($attributeOptionIds !== null && ! empty($attributeOptionIds)) {
                    $categoryAttributeIds = $category->attributes->pluck('id')->toArray();
                    $validIds = \App\Models\AttributeOption::whereIn('id', $attributeOptionIds)
                        ->whereIn('attribute_id', $categoryAttributeIds)
                        ->pluck('id')
                        ->toArray();
                    $product->allowedVariantOptions()->sync($validIds);
                }

                if ($variants !== null && ! empty($variants)) {
                    $this->validateNoDuplicateVariants($variants);
                    $this->createBatchFromVariants($product, $store, $variants, $userId);
                }
            }

            // Para productos tipo serialized: crear ProductItems solo si hay unidades (stock inicial)
            if ($product->isSerialized() && ! empty($serializedItems) && $userId) {
                $this->createSerializedItems($product, $store, $serializedItems, $userId);
            }

            // Para productos simples con stock inicial: registrar movimiento de entrada
            if (($product->type === 'simple' || empty($product->type)) && $hasInitialStock && $simpleInitialStockQty > 0 && $userId) {
                $inventarioService = app(\App\Services\InventarioService::class);
                $inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $product->id,
                    'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
                    'quantity' => $simpleInitialStockQty,
                    'description' => 'Stock inicial al crear producto',
                    'unit_cost' => $product->cost > 0 ? $product->cost : null,
                ]);
            }

            return $product->load('attributeValues.attribute');
        });
    }

    /**
     * Valida que todos los atributos requeridos de la categoría tengan valor.
     */
    protected function validateRequiredAttributes(Category $category, array $attributeValues): void
    {
        foreach ($category->attributes as $attribute) {
            $isRequired = $attribute->pivot->is_required ?? false;
            if ($isRequired) {
                $value = $attributeValues[$attribute->id] ?? null;
                if ($value === null || $value === '' || ($attribute->type === 'boolean' && $value === '0')) {
                    throw new Exception("El atributo «{$attribute->name}» es obligatorio y debe tener un valor.");
                }
            }
        }
    }

    /**
     * Valida que todos los atributos requeridos estén llenos en cada variante de productos batch.
     */
    protected function validateRequiredAttributesInVariants(Category $category, array $variants): void
    {
        foreach ($variants as $index => $variant) {
            $attributeValues = $variant['attribute_values'] ?? [];
            foreach ($category->attributes as $attribute) {
                $isRequired = $attribute->pivot->is_required ?? false;
                if ($isRequired) {
                    $value = $attributeValues[$attribute->id] ?? null;
                    if ($value === null || $value === '' || ($attribute->type === 'boolean' && $value === '0')) {
                        throw new Exception("En la variante " . ($index + 1) . ", el atributo «{$attribute->name}» es obligatorio y debe tener un valor.");
                    }
                }
            }
        }
    }

    /**
     * Valida que todos los atributos requeridos estén llenos en cada unidad serializada.
     */
    protected function validateRequiredAttributesInSerializedItems(Category $category, array $serializedItems): void
    {
        foreach ($serializedItems as $index => $item) {
            $attributeValues = $item['attribute_values'] ?? [];
            foreach ($category->attributes as $attribute) {
                $isRequired = $attribute->pivot->is_required ?? false;
                if ($isRequired) {
                    $value = $attributeValues[$attribute->id] ?? null;
                    if ($value === null || $value === '' || ($attribute->type === 'boolean' && $value === '0')) {
                        throw new Exception("En la unidad " . ($index + 1) . ", el atributo «{$attribute->name}» es obligatorio y debe tener un valor.");
                    }
                }
            }
        }
    }

    /**
     * Actualiza un producto existente en la tienda.
     * - Valida que el producto pertenezca a la tienda.
     * - Actualiza los valores de atributos del producto.
     */
    public function updateProduct(Store $store, int $productId, array $data): Product
    {
        return DB::transaction(function () use ($store, $productId, $data) {
            $product = Product::where('id', $productId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $categoryId = $data['category_id'] ?? $product->category_id;
            
            if ($categoryId) {
                $category = Category::where('id', $categoryId)
                    ->where('store_id', $store->id)
                    ->with('attributes')
                    ->firstOrFail();

                if ($category->attributes->isEmpty()) {
                    throw new Exception("La categoría «{$category->name}» no tiene atributos. Asigna atributos a la categoría antes de actualizar el producto.");
                }
            }

            $attributeValues = $data['attribute_values'] ?? [];
            $proveedorIds = $data['proveedor_ids'] ?? null;
            unset($data['attribute_values'], $data['proveedor_ids']);

            // Actualizar los campos del producto
            $product->update($data);

            if ($proveedorIds !== null) {
                $this->syncProveedores($product, $store, $proveedorIds);
            }

            // Eliminar valores de atributos existentes
            $product->attributeValues()->delete();

            // Crear nuevos valores de atributos
            foreach ($attributeValues as $attributeId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                ProductAttributeValue::create([
                    'product_id' => $product->id,
                    'attribute_id' => $attributeId,
                    'value' => (string) $value,
                ]);
            }

            return $product->fresh()->load('attributeValues.attribute');
        });
    }

    /**
     * Elimina un producto de la tienda.
     * - Valida que el producto pertenezca a la tienda.
     * - Elimina también los valores de atributos asociados (cascade).
     */
    public function deleteProduct(Store $store, int $productId): bool
    {
        return DB::transaction(function () use ($store, $productId) {
            $product = Product::where('id', $productId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            // Los valores de atributos se eliminan automáticamente por cascade
            $product->delete();

            return true;
        });
    }

    /**
     * Añade variantes a un producto por lote ya existente.
     * Misma estructura de variantes que en creación: attribute_values, opcional price, cost, has_stock, stock_initial, batch_number, expiration_date.
     * Variantes con stock inicial generan movimiento de entrada; variantes sin stock se crean con cantidad 0 en un lote VAR-.
     *
     * @param int|null $userId ID del usuario (necesario si hay variantes con stock para el movimiento)
     */
    public function addVariantsToProduct(Store $store, Product $product, array $variants, ?int $userId = null): void
    {
        if (! $product->isBatch()) {
            throw new Exception('Solo se pueden añadir variantes a productos por lote.');
        }

        $product->load('category.attributes');
        $category = $product->category;
        if (! $category || $category->attributes->isEmpty()) {
            throw new Exception('El producto debe tener una categoría con atributos para añadir variantes.');
        }

        $this->validateRequiredAttributesInVariants($category, $variants);

        $variantsWithAttributes = array_filter($variants, function ($v) {
            $attributeValues = $v['attribute_values'] ?? [];
            foreach ($attributeValues as $value) {
                if ($value !== '' && $value !== null && $value !== '0') {
                    return true;
                }
            }
            return false;
        });

        if (empty($variantsWithAttributes)) {
            return;
        }

        $variantsWithStock = array_filter($variantsWithAttributes, fn ($v) => ! empty($v['has_stock']) && ! empty($v['stock_initial']) && (int) $v['stock_initial'] > 0);
        $variantsWithoutStock = array_filter($variantsWithAttributes, fn ($v) => empty($v['has_stock']) || empty($v['stock_initial']) || (int) $v['stock_initial'] <= 0);

        $inventarioService = app(InventarioService::class);

        if (! empty($variantsWithStock) && $userId) {
            $batchesByNumber = [];
            foreach ($variantsWithStock as $variant) {
                $batchNumber = $variant['batch_number'] ?? 'L-' . date('Ymd');
                if (! isset($batchesByNumber[$batchNumber])) {
                    $batchesByNumber[$batchNumber] = [
                        'batch_number' => $batchNumber,
                        'expiration_date' => null,
                        'items' => [],
                    ];
                }
                if (empty($batchesByNumber[$batchNumber]['expiration_date']) && ! empty($variant['expiration_date'])) {
                    $batchesByNumber[$batchNumber]['expiration_date'] = $variant['expiration_date'];
                }
                $batchesByNumber[$batchNumber]['items'][] = $variant;
            }

            foreach ($batchesByNumber as $batchData) {
                $batchItems = [];
                $totalQuantity = 0;
                foreach ($batchData['items'] as $variant) {
                    $features = $this->extractFeaturesFromVariant($variant);
                    $quantity = (int) ($variant['stock_initial'] ?? 0);
                    $totalQuantity += $quantity;
                    $itemData = [
                        'quantity' => $quantity,
                        'cost' => (float) ($variant['cost'] ?? 0),
                        'features' => ! empty($features) ? $features : null,
                    ];
                    if (! empty($variant['price'])) {
                        $itemData['price'] = (float) $variant['price'];
                    }
                    $batchItems[] = $itemData;
                }

                $inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $product->id,
                    'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
                    'quantity' => $totalQuantity,
                    'description' => 'Añadir variantes a producto por lote',
                    'batch_data' => [
                        'reference' => $batchData['batch_number'],
                        'expiration_date' => $batchData['expiration_date'] ?: null,
                        'items' => $batchItems,
                    ],
                ]);
            }
        }

        if (! empty($variantsWithoutStock)) {
            $batchReference = 'VAR-' . date('Ymd');
            $batch = Batch::firstOrCreate(
                [
                    'store_id'   => $store->id,
                    'product_id' => $product->id,
                    'reference'  => $batchReference,
                ],
                ['expiration_date' => null]
            );

            foreach ($variantsWithoutStock as $variant) {
                $features = $this->extractFeaturesFromVariant($variant);
                if (empty($features)) {
                    continue;
                }
                $normalizedKey = InventarioService::detectorDeVariantesEnLotes($features);
                $existingItem = $batch->batchItems()->get()->first(function ($bi) use ($normalizedKey) {
                    return InventarioService::detectorDeVariantesEnLotes($bi->features) === $normalizedKey;
                });
                if (! $existingItem) {
                    BatchItem::create([
                        'batch_id'  => $batch->id,
                        'quantity'  => 0,
                        'unit_cost' => (float) ($variant['cost'] ?? 0),
                        'price'     => ! empty($variant['price']) ? (float) $variant['price'] : null,
                        'features'  => $features,
                    ]);
                }
            }
        }

        // No actualizamos costo ponderado aquí: estas operaciones son de variantes y precios al público.
        // Si se añadió stock, InventarioService::registrarMovimiento ya actualizó el costo.
    }

    /**
     * Actualiza los atributos (features), precio al público y/o estado activo de una variante en todos los lotes donde aparece.
     * Todas las filas de inventario (BatchItem) con las features antiguas pasan a tener las features nuevas (y precio/is_active si se indica).
     * Si en un mismo lote ya existe un ítem con las nuevas features, se suman las cantidades y se elimina el ítem actualizado.
     *
     * @param array $oldFeatures Ej: ['1' => 'Rojo', '2' => 'M']
     * @param array $newFeatures Ej: ['1' => 'Azul', '2' => 'M'] o con nuevo atributo ['1' => 'Rojo', '2' => 'M', '3' => 'Liso']
     * @param float|null $price Precio al público para la variante (actualiza todos los ítems afectados)
     * @param bool|null $isActive Estado activo de la variante (null = no cambiar)
     */
    public function updateVariantFeatures(Store $store, Product $product, array $oldFeatures, array $newFeatures, ?float $price = null, ?bool $isActive = null): void
    {
        if (! $product->isBatch()) {
            throw new Exception('Solo se pueden editar variantes en productos por lote.');
        }

        $product->load('category.attributes');
        $category = $product->category;
        if (! $category || $category->attributes->isEmpty()) {
            throw new Exception('El producto debe tener una categoría con atributos.');
        }

        $oldKey = InventarioService::detectorDeVariantesEnLotes($oldFeatures);
        $newKey = InventarioService::detectorDeVariantesEnLotes($newFeatures);
        $featuresChanged = $oldKey !== $newKey;

        // Solo validar atributos requeridos cuando cambiamos las features
        if ($featuresChanged) {
            $this->validateRequiredAttributes($category, $newFeatures);
        }

        $batches = $product->batches()->where('store_id', $store->id)->get();

        foreach ($batches as $batch) {
            $itemsWithOldFeatures = $batch->batchItems()->get()->filter(function (BatchItem $bi) use ($oldKey) {
                return InventarioService::detectorDeVariantesEnLotes($bi->features) === $oldKey;
            });

            $existingWithNewFeatures = $featuresChanged ? $batch->batchItems()->get()->first(function (BatchItem $bi) use ($newKey) {
                return InventarioService::detectorDeVariantesEnLotes($bi->features) === $newKey;
            }) : null;

            foreach ($itemsWithOldFeatures as $item) {
                $updateData = [];
                if ($featuresChanged) {
                    $updateData['features'] = $newFeatures;
                }
                if ($price !== null) {
                    $updateData['price'] = $price;
                }
                if ($isActive !== null) {
                    $updateData['is_active'] = $isActive;
                }

                if ($featuresChanged && $existingWithNewFeatures && $existingWithNewFeatures->id !== $item->id) {
                    $existingUpdate = [];
                    if ($price !== null) {
                        $existingUpdate['price'] = $price;
                    }
                    if ($isActive !== null) {
                        $existingUpdate['is_active'] = $isActive;
                    }
                    if (! empty($existingUpdate)) {
                        $existingWithNewFeatures->update($existingUpdate);
                    }
                    $existingWithNewFeatures->increment('quantity', $item->quantity);
                    $item->delete();
                } else {
                    if (! empty($updateData)) {
                        $item->update($updateData);
                    }
                    if ($featuresChanged) {
                        $existingWithNewFeatures = $item;
                    }
                }
            }
        }

        // No actualizamos costo ponderado: solo cambiamos atributos/precio al público de la variante.
    }

    /**
     * Indica si ya existe una variante con las features dadas para el producto en la tienda.
     * Útil para Crear variantes (evitar duplicados) y Modificar variante (evitar que la nueva combinación ya exista).
     */
    public function variantExists(Store $store, Product $product, array $features): bool
    {
        if (empty($features)) {
            return false;
        }
        $key = InventarioService::detectorDeVariantesEnLotes($features);
        if ($key === '') {
            return false;
        }
        $exists = BatchItem::whereHas('batch', fn ($q) => $q->where('product_id', $product->id)->where('store_id', $store->id))
            ->get()
            ->contains(fn (BatchItem $bi) => InventarioService::detectorDeVariantesEnLotes($bi->features) === $key);

        return $exists;
    }

    /**
     * Valida que no haya variantes duplicadas (mismas features) en el array.
     * Útil en el formulario de crear producto cuando el usuario agrega Variante 1, Variante 2, etc.
     *
     * @throws Exception Si hay dos o más variantes con los mismos atributos
     */
    public function validateNoDuplicateVariants(array $variants): void
    {
        $keysSeen = [];
        foreach ($variants as $index => $variant) {
            $features = $this->extractFeaturesFromVariant($variant);
            if (empty($features)) {
                continue;
            }
            $key = InventarioService::detectorDeVariantesEnLotes($features);
            if ($key === '') {
                continue;
            }
            if (isset($keysSeen[$key])) {
                $firstIndex = $keysSeen[$key];
                throw new Exception("Tienes variantes duplicadas (con los mismos atributos). La variante #" . ($index + 1) . " es igual a la variante #" . ($firstIndex + 1) . ". Edita o elimina las redundantes antes de guardar.");
            }
            $keysSeen[$key] = $index;
        }
    }

    /**
     * Extrae features (atributo_id => valor) de un array de variante, filtrando vacíos.
     */
    protected function extractFeaturesFromVariant(array $variant): array
    {
        $features = [];
        foreach ($variant['attribute_values'] ?? [] as $attrId => $value) {
            if ($value !== '' && $value !== null && $value !== '0') {
                $features[$attrId] = $value;
            }
        }
        return $features;
    }

    /**
     * Busca productos de la tienda por término (nombre, SKU, código de barras).
     */
    public function buscarProductos(Store $store, string $termino, array $excluirIds = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Product::where('store_id', $store->id)
            ->where(function ($q) use ($termino) {
                $q->where('id', $termino)
                    ->orWhere('name', 'like', "%{$termino}%")
                    ->orWhere('sku', 'like', "%{$termino}%")
                    ->orWhere('barcode', 'like', "%{$termino}%");
            });

        if (! empty($excluirIds)) {
            $query->whereNotIn('id', $excluirIds);
        }

        return $query->orderBy('name')->limit(20)->get();
    }

    /**
     * Sincroniza los proveedores de un producto (solo proveedores de la misma tienda).
     */
    protected function syncProveedores(Product $product, Store $store, array $proveedorIds): void
    {
        $validIds = \App\Models\Proveedor::where('store_id', $store->id)
            ->whereIn('id', $proveedorIds)
            ->pluck('id')
            ->toArray();

        $product->proveedores()->sync($validIds);
    }

    /**
     * Crear ProductItems desde unidades serializadas (stock inicial).
     * Usa InventarioService para registrar los movimientos y controlar el stock.
     */
    protected function createSerializedItems(Product $product, Store $store, array $serializedItems, ?int $userId = null): void
    {
        if (empty($serializedItems) || ! $userId) {
            return;
        }

        // Preparar items en el formato que espera InventarioService
        $serialItems = [];
        $reference = 'INI-' . date('Y');

        foreach ($serializedItems as $item) {
            $serial = trim($item['serial_number'] ?? '');
            if (empty($serial)) {
                continue;
            }

            $features = [];
            foreach ($item['attribute_values'] ?? [] as $attrId => $value) {
                if ($value !== '' && $value !== null && $value !== '0') {
                    $features[$attrId] = $value;
                }
            }

            $price = isset($item['price']) && $item['price'] !== '' && $item['price'] !== null
                ? (float) $item['price']
                : null;
            $serialItems[] = [
                'serial_number' => $serial,
                'cost' => (float) ($item['cost'] ?? 0),
                'price' => $price,
                'features' => ! empty($features) ? $features : null,
                'expiration_date' => $item['expiration_date'] ?? null,
            ];
        }

        if (empty($serialItems)) {
            return;
        }

        // Registrar movimiento de entrada a través de InventarioService
        // Esto creará los ProductItems y actualizará el stock del producto
        $inventarioService = app(\App\Services\InventarioService::class);
        $inventarioService->registrarMovimiento($store, $userId, [
            'product_id' => $product->id,
            'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
            'quantity' => count($serialItems),
            'description' => 'Stock inicial al crear producto serializado',
            'reference' => $reference,
            'serial_items' => $serialItems,
        ]);

    }

    /**
     * Crear batch y batch_items desde variantes.
     * Crea batch_items para TODAS las variantes definidas, incluso si tienen cantidad 0,
     * para que estén disponibles al comprar productos.
     * Usa InventarioService para registrar los movimientos y controlar el stock.
     */
    protected function createBatchFromVariants(Product $product, Store $store, array $variants, ?int $userId = null): void
    {
        if (empty($variants) || ! $userId) {
            return;
        }

        // Filtrar variantes que tienen atributos definidos (no vacíos)
        $variantsWithAttributes = array_filter($variants, function ($v) {
            $attributeValues = $v['attribute_values'] ?? [];
            foreach ($attributeValues as $value) {
                if ($value !== '' && $value !== null && $value !== '0') {
                    return true; // Tiene al menos un atributo con valor
                }
            }
            return false;
        });

        if (empty($variantsWithAttributes)) {
            return;
        }

        // Separar variantes con stock y sin stock
        $variantsWithStock = array_filter($variantsWithAttributes, fn ($v) => ! empty($v['has_stock']) && ! empty($v['stock_initial']) && (int) $v['stock_initial'] > 0);
        $variantsWithoutStock = array_filter($variantsWithAttributes, fn ($v) => empty($v['has_stock']) || empty($v['stock_initial']) || (int) $v['stock_initial'] <= 0);

        $inventarioService = app(\App\Services\InventarioService::class);

        // 1. Crear movimiento de entrada para variantes CON stock inicial
        if (! empty($variantsWithStock)) {
            // Agrupar variantes por número de lote (si tienen el mismo batch_number, van al mismo batch)
            $batchesByNumber = [];
            foreach ($variantsWithStock as $variant) {
                $batchNumber = $variant['batch_number'] ?? 'L-' . date('Ymd');
                if (! isset($batchesByNumber[$batchNumber])) {
                    $batchesByNumber[$batchNumber] = [
                        'batch_number' => $batchNumber,
                        'expiration_date' => null,
                        'items' => [],
                    ];
                }
                // Tomar la fecha de vencimiento de la primera variante con fecha
                if (empty($batchesByNumber[$batchNumber]['expiration_date']) && ! empty($variant['expiration_date'])) {
                    $batchesByNumber[$batchNumber]['expiration_date'] = $variant['expiration_date'];
                }
                $batchesByNumber[$batchNumber]['items'][] = $variant;
            }

            foreach ($batchesByNumber as $batchData) {
                // Preparar items en el formato que espera InventarioService
                $batchItems = [];
                $totalQuantity = 0;

                foreach ($batchData['items'] as $variant) {
                    $features = $this->extractFeaturesFromVariant($variant);
                    $quantity = (int) ($variant['stock_initial'] ?? 0);
                    $totalQuantity += $quantity;

                    $itemData = [
                        'quantity' => $quantity,
                        'cost' => (float) ($variant['cost'] ?? 0),
                        'features' => ! empty($features) ? $features : null,
                    ];
                    
                    if (! empty($variant['price'])) {
                        $itemData['price'] = (float) $variant['price'];
                    }

                    $batchItems[] = $itemData;
                }

                // Registrar movimiento de entrada a través de InventarioService
                // Esto creará el Batch, BatchItems y actualizará el stock del producto
                $inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $product->id,
                    'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
                    'quantity' => $totalQuantity,
                    'description' => 'Stock inicial al crear producto por lote',
                    'batch_data' => [
                        'reference' => $batchData['batch_number'],
                        'expiration_date' => $batchData['expiration_date'] ?: null,
                        'items' => $batchItems,
                    ],
                ]);
            }
        }

        // 2. Crear batch_items con cantidad 0 para variantes SIN stock inicial
        // Esto permite que las variantes estén disponibles al comprar productos
        if (! empty($variantsWithoutStock)) {
            // Usar un lote especial para variantes sin stock inicial
            $batchReference = 'VAR-' . date('Ymd');
            
            // Buscar o crear el batch para variantes sin stock
            $batch = \App\Models\Batch::firstOrCreate(
                [
                    'store_id'   => $store->id,
                    'product_id' => $product->id,
                    'reference'  => $batchReference,
                ],
                [
                    'expiration_date' => null,
                ]
            );

            foreach ($variantsWithoutStock as $variant) {
                $features = $this->extractFeaturesFromVariant($variant);
                if (empty($features)) {
                    continue; // Saltar variantes sin atributos definidos
                }

                // Verificar si ya existe un batch_item con estas features
                $normalizedKey = \App\Services\InventarioService::detectorDeVariantesEnLotes($features);
                $existingItem = $batch->batchItems()->get()->first(function ($bi) use ($normalizedKey) {
                    return \App\Services\InventarioService::detectorDeVariantesEnLotes($bi->features) === $normalizedKey;
                });

                if (! $existingItem) {
                    // Crear batch_item con cantidad 0
                    \App\Models\BatchItem::create([
                        'batch_id'  => $batch->id,
                        'quantity'  => 0,
                        'unit_cost' => (float) ($variant['cost'] ?? 0),
                        'price'     => ! empty($variant['price']) ? (float) $variant['price'] : null,
                        'features'  => $features,
                    ]);
                }
            }
        }
    }
    
}
