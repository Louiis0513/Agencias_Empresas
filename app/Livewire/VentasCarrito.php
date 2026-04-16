<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use App\Support\Quantity;
use App\Services\CotizacionService;
use App\Services\InventarioService;
use App\Services\VentaService;
use Exception;
use Livewire\Attributes\On;
use Livewire\Component;

class VentasCarrito extends Component
{
    public int $storeId;

    /** Carrito: líneas con product_id, name, quantity, stock?, price, type ('simple'|'batch'|'serialized'), y opcional batch_item_id, variant_display_name, serial_numbers[], serial_features[] */
    public array $carrito = [];
    public ?string $errorStock = null;

    /** Pendiente: producto simple a agregar (pedir cantidad) */
    public ?array $pendienteSimple = null;

    /** Pendiente: variante de lote seleccionada (pedir cantidad) */
    public ?array $pendienteBatch = null;

    /** Cantidad a agregar (para formularios pendiente simple / batch) */
    public string $cantidadSimple = '1';
    public string $cantidadBatch = '1';

    /** Modal unidades disponibles (producto serializado) */
    public ?int $productoSerializadoId = null;
    public string $productoSerializadoNombre = '';
    public array $unidadesDisponibles = [];
    public array $serialesSeleccionados = [];
    public int $unidadesDisponiblesPage = 1;
    public string $unidadesDisponiblesSearch = '';
    public int $unidadesDisponiblesTotal = 0;
    public int $unidadesDisponiblesPerPage = 15;

    /** Modal Guardar como cotización */
    public bool $mostrarModalCotizacion = false;
    public string $notaCotizacion = '';
    public ?string $venceAtCotizacion = null;
    public ?string $customerIdCotizacion = null;
    public ?string $errorCotizacion = null;

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    /**
     * Abre el modal de selección de producto (contexto venta).
     * Pasa los product_id ya en el carrito (simple y serializado) para invalidarlos en la vista.
     */
    public function abrirSelectorProducto(): void
    {
        $this->errorStock = null;
        $this->dispatch(
            'open-select-item-for-row',
            rowId: 'venta',
            itemType: 'INVENTARIO',
            productIdsInCartSimple: $this->getProductIdsInCartSimple(),
            productVariantIdsInDocument: $this->getProductVariantIdsBatchEnCarrito(),
        );
    }

    /**
     * Escucha selección de producto desde SelectItemModal. Si rowId === 'venta', deriva a simple / batch / serialized.
     */
    #[On('item-selected')]
    public function onItemSelected($rowId, $id, $name, $type, $productType = null, $productVariantId = null, $productItemId = null): void
    {
        if ($rowId !== 'venta' || $type !== 'INVENTARIO') {
            return;
        }
        $productType = $productType ?? 'simple';
        if ($productType === 'simple') {
            $store = $this->getStoreProperty();
            if (! $store) {
                return;
            }
            $producto = Product::where('id', $id)->where('store_id', $store->id)->where('is_active', true)->first();
            if (! $producto) {
                return;
            }
            $ventaService = app(VentaService::class);
            $disponibilidad = $ventaService->verificadorCarrito($store, (int) $producto->id);
            $this->pendienteSimple = [
                'product_id' => $producto->id,
                'name' => $producto->name,
                'price' => $ventaService->verPrecio($store, (int) $producto->id, 'simple'),
                'stock' => (float) $disponibilidad['cantidad'],
                'quantity_mode' => $producto->quantity_mode ?? Product::QUANTITY_MODE_UNIT,
            ];
            $this->pendienteBatch = null;
            $this->cantidadSimple = $this->defaultQuantityForMode((string) ($producto->quantity_mode ?? Product::QUANTITY_MODE_UNIT));
        } elseif ($productType === 'batch') {
            $this->pendienteSimple = null;

            if ($productVariantId) {
                $store = $this->getStoreProperty();
                if (! $store) {
                    return;
                }
                $producto = Product::where('id', $id)->where('store_id', $store->id)->first();
                if (! $producto) {
                    return;
                }
                if ($this->batchVarianteYaEnCarrito((int) $id, (int) $productVariantId, [])) {
                    $this->errorStock = 'Esta variante ya está en el carrito. Elimínela para volver a agregarla o elija otra variante.';

                    return;
                }
                $ventaService = app(VentaService::class);
                $r = $ventaService->verificadorCarritoVariante($store, (int) $id, (int) $productVariantId);
                $stock = (float) $r['cantidad'];

                $this->pendienteBatch = [
                    'product_id' => (int) $id,
                    'name' => $name,
                    'product_variant_id' => (int) $productVariantId,
                    'variant_features' => [],
                    'variant_display_name' => $name,
                    'price' => $ventaService->verPrecio($store, (int) $id, 'batch', (int) $productVariantId),
                    'stock' => $stock,
                    'quantity_mode' => $producto->quantity_mode ?? Product::QUANTITY_MODE_UNIT,
                ];
                $this->cantidadBatch = $this->defaultQuantityForMode((string) ($producto->quantity_mode ?? Product::QUANTITY_MODE_UNIT));
            } else {
                $this->dispatch('open-select-batch-variant', productId: $id, rowId: 'venta', productName: $name, variantKeysInCart: $this->getVariantKeysInCartForProduct((int) $id));
            }
        } else {
            $this->pendienteSimple = null;
            $this->pendienteBatch = null;
            if ($productItemId) {
                $this->agregarSerializadoDirectoPorItemId((int) $id, (int) $productItemId);
            } else {
                $this->abrirModalUnidades((int) $id);
            }
        }
    }

    /**
     * Escucha selección de variante (lote). Una fila por variante con stock total; no se muestran lotes.
     */
    #[On('batch-variant-selected')]
    public function onBatchVariantSelected($rowId, $productId, $productName, $variantFeatures, $displayName, $totalStock = 0, $price = null, $productVariantId = null): void
    {
        if ($rowId !== 'venta') {
            return;
        }
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }
        $producto = Product::where('id', $productId)->where('store_id', $store->id)->first();
        if (! $producto) {
            return;
        }
        $variantFeatures = is_array($variantFeatures) ? $variantFeatures : [];
        $pvId = $productVariantId ? (int) $productVariantId : null;
        if ($this->batchVarianteYaEnCarrito((int) $productId, $pvId, $variantFeatures)) {
            $this->errorStock = 'Esta variante ya está en el carrito. Elimínela para volver a agregarla o elija otra variante.';

            return;
        }
        $ventaService = app(VentaService::class);
        $stock = (float) $totalStock;
        if ($stock < 1 && $productVariantId) {
            $r = $ventaService->verificadorCarritoVariante($store, (int) $productId, (int) $productVariantId);
            $stock = (float) $r['cantidad'];
        }
        $this->pendienteBatch = [
            'product_id' => (int) $productId,
            'name' => $productName,
            'product_variant_id' => $productVariantId ? (int) $productVariantId : null,
            'variant_features' => $variantFeatures,
            'variant_display_name' => $displayName,
            'price' => $price !== null ? (float) $price : ($productVariantId ? $ventaService->verPrecio($store, (int) $productId, 'batch', (int) $productVariantId) : 0),
            'stock' => $stock,
            'quantity_mode' => $producto->quantity_mode ?? Product::QUANTITY_MODE_UNIT,
        ];
        $this->pendienteSimple = null;
        $this->cantidadBatch = $this->defaultQuantityForMode((string) ($producto->quantity_mode ?? Product::QUANTITY_MODE_UNIT));
    }

    public function cancelarPendienteSimple(): void
    {
        $this->pendienteSimple = null;
    }

    public function cancelarPendienteBatch(): void
    {
        $this->pendienteBatch = null;
    }

    public function confirmarAgregarSimple(VentaService $ventaService): void
    {
        $qty = $this->parseRequestedQuantity($this->pendienteSimple, $this->cantidadSimple);
        $this->agregarSimpleAlCarrito($qty, $ventaService);
        $this->cantidadSimple = $this->defaultQuantityForMode((string) ($this->pendienteSimple['quantity_mode'] ?? Product::QUANTITY_MODE_UNIT));
    }

    public function confirmarAgregarVariante(VentaService $ventaService): void
    {
        $qty = $this->parseRequestedQuantity($this->pendienteBatch, $this->cantidadBatch);
        $this->agregarVarianteAlCarrito($qty, $ventaService);
        $this->cantidadBatch = $this->defaultQuantityForMode((string) ($this->pendienteBatch['quantity_mode'] ?? Product::QUANTITY_MODE_UNIT));
    }

    protected function defaultQuantityForMode(string $mode): string
    {
        return $mode === Product::QUANTITY_MODE_DECIMAL ? '0.01' : '1';
    }

    protected function parseRequestedQuantity(?array $pendiente, mixed $raw): float
    {
        $mode = (string) ($pendiente['quantity_mode'] ?? Product::QUANTITY_MODE_UNIT);
        if ($mode === Product::QUANTITY_MODE_DECIMAL) {
            return max(0.01, Quantity::normalize($raw ?? 0));
        }

        return (float) max(1, (int) $raw);
    }

    /**
     * Abre el modal de unidades disponibles para un producto serializado.
     */
    public function abrirModalUnidades(int $productId): void
    {
        $this->errorStock = null;
        $this->serialesSeleccionados = [];
        $this->unidadesDisponiblesSearch = '';
        $this->unidadesDisponiblesPage = 1;
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $producto = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->first();

        if (! $producto || ! $producto->isSerialized()) {
            return;
        }

        $this->productoSerializadoId = $producto->id;
        $this->productoSerializadoNombre = $producto->name;
        $this->cargarPaginaUnidadesDisponibles();
    }

    /**
     * Seriales que ya están en el carrito para un producto (no mostrarlos de nuevo en el modal).
     */
    protected function getSerialesEnCarritoParaProducto(int $productId): array
    {
        $serials = [];
        foreach ($this->carrito as $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }
            foreach ($item['serial_numbers'] ?? [] as $sn) {
                $serials[] = $sn;
            }
        }
        return array_values(array_unique($serials));
    }

    /**
     * Product IDs que están en el carrito como tipo simple (para invalidarlos en el selector de producto).
     */
    protected function getProductIdsInCartSimple(): array
    {
        $ids = [];
        foreach ($this->carrito as $item) {
            if (($item['type'] ?? '') !== 'simple') {
                continue;
            }
            $id = (int) ($item['product_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /** IDs de variantes ya en el carrito como batch (para deshabilitar filas en el selector). */
    protected function getProductVariantIdsBatchEnCarrito(): array
    {
        $ids = [];
        foreach ($this->carrito as $item) {
            if (($item['type'] ?? '') !== 'batch') {
                continue;
            }
            $vid = (int) ($item['product_variant_id'] ?? 0);
            if ($vid > 0) {
                $ids[] = $vid;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Claves de variante normalizadas ya presentes en el carrito para un producto (líneas batch).
     * Se pasan al modal de variantes para invalidar/deshabilitar esas opciones en la vista.
     */
    protected function getVariantKeysInCartForProduct(int $productId): array
    {
        $keys = [];
        foreach ($this->carrito as $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }
            if (($item['type'] ?? '') !== 'batch') {
                continue;
            }
            $key = InventarioService::normalizedVariantKeyForBatchLine($item);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /** True si la misma variante (clave normalizada) ya está en el carrito para ese producto. */
    protected function batchVarianteYaEnCarrito(int $productId, ?int $productVariantId, array $variantFeatures = []): bool
    {
        $prospect = [
            'type' => 'batch',
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'variant_features' => $variantFeatures,
        ];
        $newKey = InventarioService::normalizedVariantKeyForBatchLine($prospect);
        if ($newKey === '') {
            return false;
        }
        foreach ($this->carrito as $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }
            if (($item['type'] ?? '') !== 'batch') {
                continue;
            }
            if (InventarioService::normalizedVariantKeyForBatchLine($item) === $newKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Carga la página actual de unidades disponibles (con búsqueda por serie y paginación).
     * Excluye las unidades que ya están en el carrito para este producto.
     */
    public function cargarPaginaUnidadesDisponibles(): void
    {
        $store = $this->getStoreProperty();
        if (! $store || $this->productoSerializadoId === null) {
            return;
        }

        $enCarrito = $this->getSerialesEnCarritoParaProducto($this->productoSerializadoId);

        $query = ProductItem::where('store_id', $store->id)
            ->where('product_id', $this->productoSerializadoId)
            ->where('status', ProductItem::STATUS_AVAILABLE);

        if (! empty($enCarrito)) {
            $query->whereNotIn('serial_number', $enCarrito);
        }

        $search = trim($this->unidadesDisponiblesSearch);
        if ($search !== '') {
            $query->where('serial_number', 'like', '%' . $search . '%');
        }

        $this->unidadesDisponiblesTotal = $query->count();
        $this->unidadesDisponibles = $query->orderBy('serial_number')
            ->offset(($this->unidadesDisponiblesPage - 1) * $this->unidadesDisponiblesPerPage)
            ->limit($this->unidadesDisponiblesPerPage)
            ->get()
            ->map(fn (ProductItem $item) => [
                'id' => $item->id,
                'serial_number' => $item->serial_number,
                'features' => $item->features,
            ])
            ->values()
            ->toArray();
    }

    public function irAPaginaUnidades(int $page): void
    {
        $maxPage = (int) max(1, ceil($this->unidadesDisponiblesTotal / $this->unidadesDisponiblesPerPage));
        $this->unidadesDisponiblesPage = max(1, min($page, $maxPage));
        $this->cargarPaginaUnidadesDisponibles();
    }

    public function updatedUnidadesDisponiblesSearch(): void
    {
        $this->unidadesDisponiblesPage = 1;
        $this->cargarPaginaUnidadesDisponibles();
    }

    public function cerrarModalUnidades(): void
    {
        $this->productoSerializadoId = null;
        $this->productoSerializadoNombre = '';
        $this->unidadesDisponibles = [];
        $this->serialesSeleccionados = [];
        $this->unidadesDisponiblesPage = 1;
        $this->unidadesDisponiblesSearch = '';
        $this->unidadesDisponiblesTotal = 0;
    }

    /**
     * Agrega al carrito las unidades serializadas seleccionadas en el modal.
     */
    public function agregarSerializadosAlCarrito(): void
    {
        $this->errorStock = null;
        if ($this->productoSerializadoId === null || empty($this->serialesSeleccionados)) {
            $this->cerrarModalUnidades();
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store) {
            $this->cerrarModalUnidades();
            return;
        }

        $productId = $this->productoSerializadoId;
        $producto = Product::where('id', $productId)->where('store_id', $store->id)->first();
        if (! $producto) {
            $this->cerrarModalUnidades();
            return;
        }

        $serialNumbers = array_values(array_filter(array_map('trim', $this->serialesSeleccionados)));
        if (empty($serialNumbers)) {
            $this->cerrarModalUnidades();
            return;
        }

        $ventaService = app(VentaService::class);
        $items = [['product_id' => $productId, 'serial_numbers' => $serialNumbers]];
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }

        $productItems = ProductItem::where('store_id', $store->id)
            ->where('product_id', $productId)
            ->whereIn('serial_number', $serialNumbers)
            ->get()
            ->keyBy('serial_number');

        $carritoSimulado = $this->carrito;
        foreach ($serialNumbers as $serial) {
            $precio = $ventaService->verPrecio($store, $productId, 'serialized', null, [$serial]);
            $carritoSimulado[] = [
                'product_id' => $productId,
                'name' => $producto->name,
                'quantity' => 1,
                'stock' => Quantity::normalizeStockForProduct($producto, $producto->stock),
                'price' => $precio,
                'type' => 'serialized',
                'serial_numbers' => [$serial],
                'prices' => [$precio],
            ];
        }

        $itemsParaValidar = $this->carritoToItemsParaValidar($carritoSimulado);
        try {
            $ventaService->validarGuardadoItemCarrito($store, $itemsParaValidar);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }

        foreach ($serialNumbers as $serial) {
            $precio = $ventaService->verPrecio($store, $productId, 'serialized', null, [$serial]);
            $pi = $productItems->get($serial);
            $features = ($pi && is_array($pi->features)) ? $pi->features : [];
            $this->carrito[] = [
                'product_id' => $productId,
                'name' => $producto->name,
                'quantity' => 1,
                'stock' => Quantity::normalizeStockForProduct($producto, $producto->stock),
                'price' => $precio,
                'type' => 'serialized',
                'serial_numbers' => [$serial],
                'prices' => [$precio],
                'serial_features' => [$features],
            ];
        }

        $this->cerrarModalUnidades();
    }

    /**
     * Agrega directamente al carrito una unidad serializada cuando el selector
     * ya devuelve productItemId (evita abrir un segundo modal).
     */
    protected function agregarSerializadoDirectoPorItemId(int $productId, int $productItemId): void
    {
        $this->errorStock = null;
        $store = $this->getStoreProperty();
        if (! $store || $productId < 1 || $productItemId < 1) {
            return;
        }

        $producto = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->first();
        if (! $producto || ! $producto->isSerialized()) {
            return;
        }

        $item = ProductItem::where('id', $productItemId)
            ->where('store_id', $store->id)
            ->where('product_id', $productId)
            ->where('status', ProductItem::STATUS_AVAILABLE)
            ->first();
        if (! $item) {
            $this->errorStock = 'La unidad serializada seleccionada no está disponible.';
            return;
        }

        $serial = trim((string) $item->serial_number);
        if ($serial === '') {
            $this->errorStock = 'La unidad seleccionada no tiene serial válido.';
            return;
        }

        // Evitar duplicados del mismo serial en carrito.
        foreach ($this->carrito as $row) {
            if ((int) ($row['product_id'] ?? 0) !== $productId) {
                continue;
            }
            if (in_array($serial, $row['serial_numbers'] ?? [], true)) {
                $this->errorStock = 'Ese serial ya está en el carrito.';
                return;
            }
        }

        $ventaService = app(VentaService::class);
        $items = [['product_id' => $productId, 'serial_numbers' => [$serial]]];
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }

        $precio = $ventaService->verPrecio($store, $productId, 'serialized', null, [$serial]);
        $this->carrito[] = [
            'product_id' => $productId,
            'name' => $producto->name,
            'quantity' => 1,
            'stock' => Quantity::normalizeStockForProduct($producto, $producto->stock),
            'price' => $precio,
            'type' => 'serialized',
            'serial_numbers' => [$serial],
            'prices' => [$precio],
            'serial_features' => [is_array($item->features) ? $item->features : []],
        ];
    }

    /**
     * Agrega al carrito un producto simple (solo cantidad).
     */
    public function agregarSimpleAlCarrito(float $quantity, VentaService $ventaService): void
    {
        $this->errorStock = null;
        if (! $this->pendienteSimple) {
            return;
        }
        $mode = (string) ($this->pendienteSimple['quantity_mode'] ?? Product::QUANTITY_MODE_UNIT);
        $quantity = $mode === Product::QUANTITY_MODE_DECIMAL
            ? max(0.01, Quantity::normalize($quantity))
            : (float) max(1, (int) $quantity);
        $store = $this->getStoreProperty();
        if (! $store) {
            $this->pendienteSimple = null;
            return;
        }
        $productId = (int) $this->pendienteSimple['product_id'];
        $stock = (float) $this->pendienteSimple['stock'];
        if ($quantity > $stock) {
            $this->errorStock = "Stock insuficiente. Disponible: {$stock}, solicitado: {$quantity}.";
            return;
        }
        $carritoSimulado = $this->carrito;
        $carritoSimulado[] = [
            'product_id' => $productId,
            'name' => $this->pendienteSimple['name'],
            'quantity' => $quantity,
            'stock' => $stock,
            'price' => (float) $this->pendienteSimple['price'],
            'type' => 'simple',
            'quantity_mode' => $mode,
        ];
        $items = $this->carritoToItemsParaValidar($carritoSimulado);
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }
        $this->carrito[] = [
            'product_id' => $productId,
            'name' => $this->pendienteSimple['name'],
            'quantity' => $quantity,
            'stock' => $stock,
            'price' => (float) $this->pendienteSimple['price'],
            'type' => 'simple',
            'quantity_mode' => $mode,
        ];
        $this->pendienteSimple = null;
    }

    /**
     * Agrega al carrito una variante de producto lote (por variante, stock total en todos los lotes).
     */
    public function agregarVarianteAlCarrito(float $quantity, VentaService $ventaService): void
    {
        $this->errorStock = null;
        if (! $this->pendienteBatch) {
            return;
        }
        $mode = (string) ($this->pendienteBatch['quantity_mode'] ?? Product::QUANTITY_MODE_UNIT);
        $quantity = $mode === Product::QUANTITY_MODE_DECIMAL
            ? max(0.01, Quantity::normalize($quantity))
            : (float) max(1, (int) $quantity);
        $store = $this->getStoreProperty();
        if (! $store) {
            $this->pendienteBatch = null;
            return;
        }
        $productId = (int) $this->pendienteBatch['product_id'];
        $variantFeatures = $this->pendienteBatch['variant_features'] ?? [];
        $pvIdRaw = $this->pendienteBatch['product_variant_id'] ?? null;
        $pvId = $pvIdRaw !== null && (int) $pvIdRaw > 0 ? (int) $pvIdRaw : null;
        if ($this->batchVarianteYaEnCarrito($productId, $pvId, is_array($variantFeatures) ? $variantFeatures : [])) {
            $this->errorStock = 'Esta variante ya está en el carrito. Elimínela para volver a agregarla o elija otra variante.';

            return;
        }
        $stockVariante = (float) $this->pendienteBatch['stock'];
        if ($quantity > $stockVariante) {
            $this->errorStock = "Stock insuficiente en esta variante. Disponible: {$stockVariante}, solicitado: {$quantity}.";
            return;
        }
        $items = [['product_id' => $productId, 'product_variant_id' => $pvId, 'quantity' => $quantity]];
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }
        $this->carrito[] = [
            'product_id' => $productId,
            'name' => $this->pendienteBatch['name'],
            'product_variant_id' => $pvId,
            'variant_features' => $variantFeatures,
            'variant_display_name' => $this->pendienteBatch['variant_display_name'],
            'quantity' => $quantity,
            'stock' => $stockVariante,
            'price' => (float) $this->pendienteBatch['price'],
            'type' => 'batch',
            'quantity_mode' => $mode,
        ];
        $this->pendienteBatch = null;
    }

    /**
     * Convierte el carrito a formato para validar stock: product_id + quantity | variant_features + quantity | batch_item_id + quantity | serial_numbers.
     */
    protected function carritoToItemsParaValidar(array $carrito): array
    {
        $items = [];
        $byProduct = [];
        foreach ($carrito as $row) {
            if (! empty($row['serial_numbers'])) {
                $items[] = ['product_id' => $row['product_id'], 'serial_numbers' => $row['serial_numbers']];
            } elseif (! empty($row['product_variant_id'])) {
                $items[] = [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'product_variant_id' => (int) $row['product_variant_id'],
                    'quantity' => (float) ($row['quantity'] ?? 0),
                ];
            } else {
                $pid = (int) ($row['product_id'] ?? 0);
                if ($pid > 0) {
                    $byProduct[$pid] = ($byProduct[$pid] ?? 0) + (float) ($row['quantity'] ?? 0);
                }
            }
        }
        foreach ($byProduct as $productId => $totalQty) {
            if ($totalQty > 0) {
                $items[] = ['product_id' => $productId, 'quantity' => $totalQty];
            }
        }
        return $items;
    }

    /**
     * Valida el carrito actual con VentaService::validarGuardadoItemCarrito (InventarioService::validarStockDisponible).
     */
    protected function validarCarritoConStock(VentaService $ventaService): bool
    {
        $this->errorStock = null;
        $store = $this->getStoreProperty();
        if (! $store || empty($this->carrito)) {
            return true;
        }

        $items = $this->carritoToItemsParaValidar($this->carrito);
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
            return true;
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return false;
        }
    }

    /**
     * Actualiza la cantidad de una línea del carrito por su índice. Valida stock (producto o variante).
     */
    public function actualizarCantidadPorIndice(int $index, float $quantity): void
    {
        $this->errorStock = null;
        if (! isset($this->carrito[$index])) {
            return;
        }
        $mode = (string) (($this->carrito[$index]['quantity_mode'] ?? Product::QUANTITY_MODE_UNIT));
        $quantity = $mode === Product::QUANTITY_MODE_DECIMAL
            ? max(0, Quantity::normalize($quantity))
            : (float) max(0, (int) $quantity);
        $item = &$this->carrito[$index];
        if (! empty($item['serial_numbers'] ?? [])) {
            return;
        }
        if ($quantity === 0) {
            array_splice($this->carrito, $index, 1);
            $this->carrito = array_values($this->carrito);
            return;
        }
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }
        $stockMax = (float) ($item['stock'] ?? 0);
        if ($quantity > $stockMax) {
            $this->errorStock = "Cantidad máxima disponible: {$stockMax}.";
            return;
        }
        $cantidadAnterior = (float) $item['quantity'];
        $item['quantity'] = $quantity;
        $ventaService = app(VentaService::class);
        $productId = (int) $item['product_id'];
        if (($item['type'] ?? '') === 'batch' && ! empty($item['product_variant_id'])) {
            $r = $ventaService->verificadorCarritoVariante($store, $productId, (int) $item['product_variant_id']);
            if ($r['cantidad'] < $quantity) {
                $item['quantity'] = $cantidadAnterior;
                $this->errorStock = "Stock insuficiente en esta variante. Disponible: {$r['cantidad']}.";
                return;
            }
            $item['stock'] = $r['cantidad'];
        } else {
            $r = $ventaService->verificadorCarrito($store, $productId, null, null);
            if ($r['cantidad'] < $quantity) {
                $item['quantity'] = $cantidadAnterior;
                $this->errorStock = "Stock insuficiente. Disponible: {$r['cantidad']}.";
                return;
            }
            $item['stock'] = $r['cantidad'];
        }
    }

    /**
     * Quita una línea del carrito por su índice (cada serializado en su propia fila, cada lote una fila).
     */
    public function quitarLineaCarrito(int $index): void
    {
        if (isset($this->carrito[$index])) {
            array_splice($this->carrito, $index, 1);
            $this->carrito = array_values($this->carrito);
            $this->errorStock = null;
        }
    }

    /**
     * Suma de todos los subtotales del carrito (para el contenedor Total).
     */
    public function getCarritoTotalProperty(): float
    {
        $total = 0.0;
        foreach ($this->carrito as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $isSerialized = ! empty($item['serial_numbers'] ?? []);
            $prices = $item['prices'] ?? [];
            $precioUnit = $isSerialized && ! empty($prices) ? (float) $prices[0] : (float) ($item['price'] ?? 0);
            $minQty = (($item['quantity_mode'] ?? Product::QUANTITY_MODE_UNIT) === Product::QUANTITY_MODE_DECIMAL) ? 0.01 : 1;
            $total += $precioUnit * max($minQty, $qty);
        }
        $store = $this->getStoreProperty();
        $currency = $store?->currency ?? 'COP';

        return app(\App\Services\CurrencyFormatService::class)->roundForCurrency($total, $currency);
    }

    /**
     * Abre el modal para guardar el carrito como cotización.
     */
    public function abrirModalCotizacion(): void
    {
        $this->errorCotizacion = null;
        $this->notaCotizacion = '';
        $this->venceAtCotizacion = null;
        $this->customerIdCotizacion = null;
        $this->mostrarModalCotizacion = true;
    }

    /**
     * Cierra el modal de cotización sin guardar.
     */
    public function cerrarModalCotizacion(): void
    {
        $this->mostrarModalCotizacion = false;
        $this->notaCotizacion = '';
        $this->venceAtCotizacion = null;
        $this->customerIdCotizacion = null;
        $this->errorCotizacion = null;
    }

    /**
     * Guarda el carrito actual como cotización.
     */
    public function guardarComoCotizacion(CotizacionService $cotizacionService): void
    {
        $this->errorCotizacion = null;

        $nota = trim($this->notaCotizacion);
        if ($nota === '') {
            $this->errorCotizacion = 'La nota es obligatoria.';
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store) {
            $this->errorCotizacion = 'No se encontró la tienda.';
            return;
        }

        if (empty($this->carrito)) {
            $this->errorCotizacion = 'El carrito está vacío. Agrega productos antes de guardar la cotización.';
            return;
        }

        $venceAt = $this->venceAtCotizacion
            ? \Carbon\Carbon::parse($this->venceAtCotizacion)->startOfDay()
            : null;

        try {
            $customerId = $this->customerIdCotizacion ? (int) $this->customerIdCotizacion : null;
            $cotizacionService->crearDesdeCarrito($store, auth()->id(), $customerId, $nota, $this->carrito, $venceAt);

            $this->cerrarModalCotizacion();
            session()->flash('success', 'Cotización guardada correctamente.');
            $this->redirect(route('stores.ventas.cotizaciones', $store), navigate: true);
        } catch (\InvalidArgumentException $e) {
            $this->errorCotizacion = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorCotizacion = 'Error al guardar la cotización: ' . $e->getMessage();
        }
    }
    public function enviarAFacturacion()
    {
        // 1. Validar
        if (empty($this->carrito)) {
            return;
        }

        // 2. Mapear items
        $itemsParaFactura = collect($this->carrito)->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'type' => $item['type'] ?? 'simple',
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'variant_features' => $item['variant_features'] ?? [],
                'variant_display_name' => $item['variant_display_name'] ?? '',
                'serial_numbers' => $item['serial_numbers'] ?? [],
            ];
        })->toArray();

        // 3. Emitir datos GLOBALMENTE (Sin ->to, así no falla buscando el componente)
        $this->dispatch('load-items-from-cart', items: $itemsParaFactura);
        
        // 4. Abrir el modal (Usamos 'create-invoice' que es el nombre en tu vista Blade)
        $this->dispatch('open-modal', 'create-invoice');
    }

    public function getCustomersProperty(): \Illuminate\Support\Collection
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }

        return Customer::where('store_id', $store->id)->orderBy('name')->get();
    }

    public function render()
    {
        $store = Store::find($this->storeId);

        return view('livewire.ventas-carrito', ['store' => $store]);
    }
}
