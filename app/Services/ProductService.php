<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\CurrencyFormatService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductService
{
    /**
     * Resuelve precio y margen con regla estricta:
     * - Debe venir exactamente uno entre price y margin.
     * - Si viene margin, calcula price usando cost.
     * - Si viene price, calcula margin usando price y cost.
     *
     * @return array{price: float, margin: float|null}
     */
    public function resolvePriceAndMargin(float $cost, mixed $price = null, mixed $margin = null, string $currency = 'COP'): array
    {
        $hasPrice = $price !== null && $price !== '';
        $hasMargin = $margin !== null && $margin !== '';

        if ($hasPrice === $hasMargin) {
            throw new Exception('Ingresa precio o margen, no ambos.');
        }

        $currencyService = app(CurrencyFormatService::class);

        if ($hasMargin) {
            $marginValue = round((float) $margin, 2);
            if ($marginValue >= 100) {
                throw new Exception('No se puede calcular precio con margen >= 100%.');
            }

            $priceValue = (float) ($cost / (1 - ($marginValue / 100)));
            $priceValue = $currencyService->roundForCurrency($priceValue, $currency);

            return [
                'price' => $priceValue,
                'margin' => $marginValue,
            ];
        }

        $priceValue = $currencyService->roundForCurrency((float) $price, $currency);
        $marginValue = $priceValue > 0
            ? round((($priceValue - $cost) / $priceValue) * 100, 2)
            : null;

        return [
            'price' => $priceValue,
            'margin' => $marginValue,
        ];
    }

    public function computeMarginFromCostAndPrice(float $cost, ?float $price): ?float
    {
        if ($price === null || $price <= 0) {
            return null;
        }

        return round((($price - $cost) / $price) * 100, 2);
    }

    /**
     * Crea un nuevo producto en la tienda.
     * - Categoría obligatoria.
     * - La categoría debe tener al menos un atributo asignado.
     * - Valida que los atributos requeridos estén llenos.
     * - Se guardan los valores de atributos (attribute_values) del producto.
     * - Para productos tipo batch: puede recibir 'variants'.
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
            $hasInitialStock = $data['has_initial_stock'] ?? false;
            $simpleInitialStockQty = 0.0;
            
            unset($data['attribute_values'], $data['proveedor_ids'], $data['variants'], $data['serializedItems'], $data['has_initial_stock']);

            // Validar que los atributos requeridos estén llenos
            // Solo para productos simples: los atributos están a nivel de producto
            // Para batch y serialized: los atributos están en las variantes/unidades
            $productType = $data['type'] ?? null;
            $isSimpleProduct = ($productType === 'simple' || empty($productType));
            $isBatchProduct = ($productType === \App\Models\MovimientoInventario::PRODUCT_TYPE_BATCH);
            $isSerializedProduct = ($productType === \App\Models\MovimientoInventario::PRODUCT_TYPE_SERIALIZED);

            if (! isset($data['quantity_mode'])) {
                $data['quantity_mode'] = Product::QUANTITY_MODE_UNIT;
            }
            if (! isset($data['quantity_step'])) {
                $data['quantity_step'] = $data['quantity_mode'] === Product::QUANTITY_MODE_DECIMAL ? 0.01 : 1.00;
            }
            if ($isSerializedProduct) {
                $data['quantity_mode'] = Product::QUANTITY_MODE_UNIT;
                $data['quantity_step'] = 1.00;
            }
            
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
                $simpleInitialStockQty = Quantity::normalize($data['stock'] ?? 0);
                $data['stock'] = 0;
            }
            
            // Para productos batch y serialized: siempre se crean con stock=0
            // El stock se actualiza cuando se crean los Batch/BatchItem o ProductItems a través de InventarioService
            if (($data['type'] ?? null) === \App\Models\MovimientoInventario::PRODUCT_TYPE_BATCH ||
                ($data['type'] ?? null) === \App\Models\MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
                $data['stock'] = 0;
            }

            $currency = $store->currency ?? 'COP';
            $currencyService = app(CurrencyFormatService::class);
            if (isset($data['cost'])) {
                $data['cost'] = $currencyService->roundForCurrency((float) $data['cost'], $currency);
            }

            if ($isSimpleProduct) {
                $hasPrice = array_key_exists('price', $data) && $data['price'] !== '' && $data['price'] !== null;
                $hasMargin = array_key_exists('margin', $data) && $data['margin'] !== '' && $data['margin'] !== null;
                $costForCalc = (float) ($data['cost'] ?? 0);

                if ($hasPrice || $hasMargin) {
                    $resolved = $this->resolvePriceAndMargin(
                        $costForCalc,
                        $hasPrice ? $data['price'] : null,
                        $hasMargin ? $data['margin'] : null,
                        $currency
                    );
                    $data['price'] = $resolved['price'];
                    $data['margin'] = $resolved['margin'];
                } elseif (isset($data['price'])) {
                    $data['price'] = $currencyService->roundForCurrency((float) $data['price'], $currency);
                    $data['margin'] = $this->computeMarginFromCostAndPrice($costForCalc, (float) $data['price']);
                }
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

            // Para productos tipo batch: crear lotes si hay variantes con stock
            if ($product->isBatch()) {
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
                if ($value === null || $value === '') {
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
                    if ($value === null || $value === '') {
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
                    if ($value === null || $value === '') {
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
     * - Si cambia quantity_mode entre decimal y unit, conserva products.stock real.
     *   No modifica históricos (facturas/compras/movimientos ya registrados).
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

            $currency = $store->currency ?? 'COP';
            $currencyService = app(CurrencyFormatService::class);
            if (array_key_exists('cost', $data)) {
                $data['cost'] = $currencyService->roundForCurrency((float) $data['cost'], $currency);
            }

            $isSimpleProduct = ($product->type === 'simple' || empty($product->type));
            if ($isSimpleProduct) {
                $currentQuantityMode = $product->isSerialized()
                    ? Product::QUANTITY_MODE_UNIT
                    : ($product->quantity_mode ?? Product::QUANTITY_MODE_UNIT);
                $nextQuantityMode = $currentQuantityMode;
                if (array_key_exists('quantity_mode', $data)) {
                    $candidateMode = (string) $data['quantity_mode'];
                    if (in_array($candidateMode, [Product::QUANTITY_MODE_UNIT, Product::QUANTITY_MODE_DECIMAL], true)) {
                        $nextQuantityMode = $candidateMode;
                    }
                }

                $data['quantity_mode'] = $nextQuantityMode;
                $data['quantity_step'] = $nextQuantityMode === Product::QUANTITY_MODE_DECIMAL
                    ? Quantity::normalize($data['quantity_step'] ?? $product->quantity_step ?? 0.01)
                    : 1.00;

                $hasPrice = array_key_exists('price', $data) && $data['price'] !== '' && $data['price'] !== null;
                $hasMargin = array_key_exists('margin', $data) && $data['margin'] !== '' && $data['margin'] !== null;
                $costForCalc = (float) ($data['cost'] ?? $product->cost ?? 0);

                if ($hasPrice || $hasMargin) {
                    $resolved = $this->resolvePriceAndMargin(
                        $costForCalc,
                        $hasPrice ? $data['price'] : null,
                        $hasMargin ? $data['margin'] : null,
                        $currency
                    );
                    $data['price'] = $resolved['price'];
                    $data['margin'] = $resolved['margin'];
                } elseif (array_key_exists('cost', $data)) {
                    $data['margin'] = $this->computeMarginFromCostAndPrice($costForCalc, (float) ($product->price ?? 0));
                }
            } elseif (array_key_exists('price', $data)) {
                $data['price'] = $currencyService->roundForCurrency((float) $data['price'], $currency);
            }

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
     * Crea registros en product_variants y opcionalmente batch_items con stock inicial.
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

        $inventarioService = app(InventarioService::class);

        foreach ($variantsWithAttributes as $variant) {
            $features = $this->extractFeaturesFromVariant($variant);
            if (empty($features)) {
                continue;
            }

            // Crear o encontrar el ProductVariant
            $productVariant = InventarioService::resolverVariante($product->id, null, $features);
            if (! $productVariant) {
                continue;
            }

            // Actualizar precio, costo, sku y barcode si vienen
            $updateData = [];
            if (! empty($variant['price'])) {
                $updateData['price'] = (float) $variant['price'];
            }
            if (! empty($variant['cost'])) {
                $updateData['cost_reference'] = (float) $variant['cost'];
            }
            if (array_key_exists('margin', $variant) && $variant['margin'] !== '' && $variant['margin'] !== null) {
                $updateData['margin'] = (float) $variant['margin'];
            }
            if (isset($variant['sku'])) {
                $skuValue = trim((string) $variant['sku']);
                if ($skuValue !== '') {
                    $updateData['sku'] = $skuValue;
                }
            }
            if (isset($variant['barcode'])) {
                $barcodeValue = trim((string) $variant['barcode']);
                if ($barcodeValue !== '') {
                    $updateData['barcode'] = $barcodeValue;
                }
            }
            if (! empty($updateData)) {
                $hasPrice = array_key_exists('price', $updateData) && $updateData['price'] !== null;
                $hasMargin = array_key_exists('margin', $updateData) && $updateData['margin'] !== null;
                if ($hasPrice || $hasMargin) {
                    $resolved = $this->resolvePriceAndMargin(
                        (float) ($updateData['cost_reference'] ?? $productVariant->cost_reference ?? 0),
                        $hasPrice ? $updateData['price'] : null,
                        $hasMargin ? $updateData['margin'] : null,
                        $store->currency ?? 'COP'
                    );
                    $updateData['price'] = $resolved['price'];
                    $updateData['margin'] = $resolved['margin'];
                } elseif (array_key_exists('cost_reference', $updateData)) {
                    $updateData['margin'] = $this->computeMarginFromCostAndPrice(
                        (float) $updateData['cost_reference'],
                        $productVariant->price !== null ? (float) $productVariant->price : null
                    );
                }
                $productVariant->update($updateData);
            }

            // Si tiene stock inicial, crear movimiento de entrada
            $hasStock = ! empty($variant['has_stock']) && ! empty($variant['stock_initial']) && Quantity::normalize($variant['stock_initial']) > 0;
            if ($hasStock && $userId) {
                $batchNumber = $variant['batch_number'] ?? 'L-' . date('Ymd');
                $quantity = Quantity::normalize($variant['stock_initial']);
                $expirationDate = isset($variant['expiration_date']) && trim((string) $variant['expiration_date']) !== ''
                    ? $variant['expiration_date']
                    : null;

                $inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $product->id,
                    'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
                    'quantity' => $quantity,
                    'description' => 'Añadir variante a producto por lote',
                    'batch_data' => [
                        'reference' => $batchNumber,
                        'expiration_date' => $expirationDate,
                        'items' => [
                            [
                                'quantity' => $quantity,
                                'cost' => (float) ($variant['cost'] ?? 0),
                                'product_variant_id' => $productVariant->id,
                            ],
                        ],
                    ],
                ]);
            }
        }
    }

    /**
     * Actualiza un ProductVariant: features, precio, costo de referencia, barcode, sku, is_active.
     * Si las features cambian, reasigna los batch_items existentes a la nueva variante (o fusiona si ya existe).
     *
     * @param  int  $productVariantId  ID de la variante a modificar
     * @param  array  $data  Datos a actualizar: features, price, cost_reference, barcode, sku, is_active
     */
    public function updateVariant(Store $store, Product $product, int $productVariantId, array $data): void
    {
        if (! $product->isBatch()) {
            throw new Exception('Solo se pueden editar variantes en productos por lote.');
        }

        $variant = ProductVariant::where('id', $productVariantId)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $updateData = [];

        if (array_key_exists('features', $data) && is_array($data['features'])) {
            $newKey = InventarioService::detectorDeVariantesEnLotes($data['features']);
            $oldKey = $variant->normalized_key;

            if ($newKey !== $oldKey && $newKey !== '') {
                // Verificar si ya existe otra variante con esas features
                $existingVariant = ProductVariant::where('product_id', $product->id)
                    ->where('id', '!=', $variant->id)
                    ->get()
                    ->first(fn (ProductVariant $pv) => $pv->normalized_key === $newKey);

                if ($existingVariant) {
                    // Fusionar: mover todos los batch_items de esta variante a la existente
                    BatchItem::where('product_variant_id', $variant->id)->update([
                        'product_variant_id' => $existingVariant->id,
                    ]);
                    // Actualizar la variante destino con los datos nuevos
                    $mergeData = [];
                    if (array_key_exists('price', $data)) {
                        $mergeData['price'] = $data['price'];
                    }
                    if (array_key_exists('cost_reference', $data)) {
                        $mergeData['cost_reference'] = $data['cost_reference'];
                    }
                    if (array_key_exists('is_active', $data)) {
                        $mergeData['is_active'] = $data['is_active'];
                    }
                    if (array_key_exists('in_showcase', $data)) {
                        $mergeData['in_showcase'] = $data['in_showcase'];
                    }
                    if (array_key_exists('image_path', $data)) {
                        $mergeData['image_path'] = $data['image_path'];
                    }
                    if (! empty($mergeData)) {
                        $existingVariant->update($mergeData);
                    }
                    // Eliminar la variante vieja
                    $variant->delete();
                    return;
                }

                $updateData['features'] = json_decode($newKey, true);
            }
        }

        $currency = $store->currency ?? 'COP';
        $currencyService = app(CurrencyFormatService::class);
        if (array_key_exists('price', $data)) {
            $updateData['price'] = $currencyService->roundForCurrency((float) $data['price'], $currency);
        }
        if (array_key_exists('cost_reference', $data)) {
            $updateData['cost_reference'] = $currencyService->roundForCurrency((float) $data['cost_reference'], $currency);
        }
        if (array_key_exists('barcode', $data)) {
            $updateData['barcode'] = $data['barcode'];
        }
        if (array_key_exists('sku', $data)) {
            $updateData['sku'] = $data['sku'];
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = $data['is_active'];
        }
        if (array_key_exists('in_showcase', $data)) {
            $updateData['in_showcase'] = $data['in_showcase'];
        }
        if (array_key_exists('image_path', $data)) {
            $updateData['image_path'] = $data['image_path'];
        }

        $hasPrice = array_key_exists('price', $data) && $data['price'] !== '' && $data['price'] !== null;
        $hasMargin = array_key_exists('margin', $data) && $data['margin'] !== '' && $data['margin'] !== null;
        $hasCostReference = array_key_exists('cost_reference', $data);

        if ($hasPrice || $hasMargin) {
            $costReference = (float) ($updateData['cost_reference'] ?? $variant->cost_reference ?? 0);
            $resolved = $this->resolvePriceAndMargin(
                $costReference,
                $hasPrice ? ($updateData['price'] ?? $data['price']) : null,
                $hasMargin ? $data['margin'] : null,
                $currency
            );
            $updateData['price'] = $resolved['price'];
            $updateData['margin'] = $resolved['margin'];
        } elseif ($hasCostReference) {
            $updateData['margin'] = $this->computeMarginFromCostAndPrice(
                (float) ($updateData['cost_reference'] ?? 0),
                $variant->price !== null ? (float) $variant->price : null
            );
        }

        if (! empty($updateData)) {
            $variant->update($updateData);
        }
    }

    /**
     * Indica si ya existe una variante con las features dadas para el producto.
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
        return ProductVariant::where('product_id', $product->id)
            ->get()
            ->contains(fn (ProductVariant $pv) => $pv->normalized_key === $key);
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

        $currencyService = app(CurrencyFormatService::class);
        $currency = $store->currency ?? 'COP';

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
                ? $currencyService->roundForCurrency((float) $item['price'], $currency)
                : null;
            $cost = $currencyService->roundForCurrency((float) ($item['cost'] ?? 0), $currency);
            $margin = array_key_exists('margin', $item) && $item['margin'] !== '' && $item['margin'] !== null
                ? round((float) $item['margin'], 2)
                : null;

            if ($margin !== null) {
                $resolved = $this->resolvePriceAndMargin($cost, null, $margin, $currency);
                $price = $resolved['price'];
                $margin = $resolved['margin'];
            } elseif ($price !== null) {
                $margin = $this->computeMarginFromCostAndPrice($cost, (float) $price);
            }

            $serialItems[] = [
                'serial_number' => $serial,
                'cost' => $cost,
                'price' => $price,
                'margin' => $margin,
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
     * Crear ProductVariant y opcionalmente batch_items con stock inicial desde variantes.
     * Crea ProductVariant para TODAS las variantes, y solo batch_items para las que tienen stock.
     */
    protected function createBatchFromVariants(Product $product, Store $store, array $variants, ?int $userId = null): void
    {
        if (empty($variants) || ! $userId) {
            return;
        }

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

        $inventarioService = app(\App\Services\InventarioService::class);

        foreach ($variantsWithAttributes as $variant) {
            $features = $this->extractFeaturesFromVariant($variant);
            if (empty($features)) {
                continue;
            }

            // Crear o encontrar el ProductVariant
            $productVariant = InventarioService::resolverVariante($product->id, null, $features);
            if (! $productVariant) {
                continue;
            }

            // Actualizar precio, costo, sku y barcode (redondear según moneda de la tienda)
            $currencyService = app(CurrencyFormatService::class);
            $currency = $store->currency ?? 'COP';
            $updateData = [];
            if (! empty($variant['price'])) {
                $updateData['price'] = $currencyService->roundForCurrency((float) $variant['price'], $currency);
            }
            if (! empty($variant['cost'])) {
                $updateData['cost_reference'] = $currencyService->roundForCurrency((float) $variant['cost'], $currency);
            }
            if (array_key_exists('margin', $variant) && $variant['margin'] !== '' && $variant['margin'] !== null) {
                $updateData['margin'] = round((float) $variant['margin'], 2);
            }
            if (isset($variant['sku'])) {
                $skuValue = trim((string) $variant['sku']);
                if ($skuValue !== '') {
                    $updateData['sku'] = $skuValue;
                }
            }
            if (isset($variant['barcode'])) {
                $barcodeValue = trim((string) $variant['barcode']);
                if ($barcodeValue !== '') {
                    $updateData['barcode'] = $barcodeValue;
                }
            }
            if (! empty($updateData)) {
                $hasPrice = array_key_exists('price', $updateData) && $updateData['price'] !== null;
                $hasMargin = array_key_exists('margin', $updateData) && $updateData['margin'] !== null;
                if ($hasPrice || $hasMargin) {
                    $resolved = $this->resolvePriceAndMargin(
                        (float) ($updateData['cost_reference'] ?? $productVariant->cost_reference ?? 0),
                        $hasPrice ? $updateData['price'] : null,
                        $hasMargin ? $updateData['margin'] : null,
                        $currency
                    );
                    $updateData['price'] = $resolved['price'];
                    $updateData['margin'] = $resolved['margin'];
                } elseif (array_key_exists('cost_reference', $updateData)) {
                    $updateData['margin'] = $this->computeMarginFromCostAndPrice(
                        (float) $updateData['cost_reference'],
                        $productVariant->price !== null ? (float) $productVariant->price : null
                    );
                }
                $productVariant->update($updateData);
            }

            // Si tiene stock inicial, crear movimiento de entrada
            $hasStock = ! empty($variant['has_stock']) && ! empty($variant['stock_initial']) && Quantity::normalize($variant['stock_initial']) > 0;
            if ($hasStock) {
                $batchNumber = $variant['batch_number'] ?? 'L-' . date('Ymd');
                $quantity = Quantity::normalize($variant['stock_initial']);

                $expirationDate = isset($variant['expiration_date']) && trim((string) $variant['expiration_date']) !== ''
                    ? $variant['expiration_date']
                    : null;

                $costForMovement = $currencyService->roundForCurrency((float) ($variant['cost'] ?? 0), $currency);
                $inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $product->id,
                    'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
                    'quantity' => $quantity,
                    'description' => 'Stock inicial al crear producto por lote',
                    'batch_data' => [
                        'reference' => $batchNumber,
                        'expiration_date' => $expirationDate,
                        'items' => [
                            [
                                'quantity' => $quantity,
                                'cost' => $costForMovement,
                                'product_variant_id' => $productVariant->id,
                            ],
                        ],
                    ],
                ]);
            }
        }
    }

    public function recalculateProductMargin(Product $product): void
    {
        if (! ($product->type === 'simple' || empty($product->type))) {
            return;
        }

        $product->update([
            'margin' => $this->computeMarginFromCostAndPrice((float) $product->cost, (float) $product->price),
        ]);
    }

    public function recalculateVariantMargin(ProductVariant $variant): void
    {
        $variant->update([
            'margin' => $this->computeMarginFromCostAndPrice(
                (float) $variant->cost_reference,
                $variant->price !== null ? (float) $variant->price : null
            ),
        ]);
    }

    public function recalculateProductItemMargin(ProductItem $item): void
    {
        $item->update([
            'margin' => $this->computeMarginFromCostAndPrice(
                (float) $item->cost,
                $item->price !== null ? (float) $item->price : null
            ),
        ]);
    }
}
