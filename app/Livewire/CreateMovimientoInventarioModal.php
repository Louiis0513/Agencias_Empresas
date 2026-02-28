<?php

namespace App\Livewire;

use App\Models\BatchItem;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\InventarioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateMovimientoInventarioModal extends Component
{
    public int $storeId;

    public int $product_id = 0;
    public string $type = 'ENTRADA';
    public string $quantity = '';
    public ?string $description = null;
    public ?string $unit_cost = null;

    /** Serializado ENTRADA → referencia de compra (obligatoria; INI-YYYY si carga inicial) */
    public string $serial_reference = '';
    /** Serializado ENTRADA → una fila por unidad: serial_number, cost, features */
    public array $serial_items = [];
    /** Serializado → seriales seleccionados para SALIDA */
    public array $serials_selected = [];
    public array $serials_available = [];

    /** Batch → datos de entrada */
    public string $batch_reference = '';
    public ?string $batch_expiration = null;
    public array $batch_items = [
        [
            'quantity' => '',
            'unit_cost' => '',
            'features' => [],
        ],
    ];
    /** Batch → salir (selección de variante) o entrada (variante seleccionada en modal) */
    public ?int $product_variant_id = null;
    /** Nombre legible de la variante seleccionada (para mostrar en formulario ENTRADA batch) */
    public ?string $selectedVariantDisplayName = null;
    public array $batch_items_available = [];

    public array $categoryAttributes = [];

    /** Modal seriales (producto serializado SALIDA): unidades disponibles con búsqueda/paginación */
    public ?int $productoSerializadoIdMov = null;
    public string $productoSerializadoNombreMov = '';
    public string $unidadesDisponiblesSearchMov = '';
    public int $unidadesDisponiblesPageMov = 1;
    public int $unidadesDisponiblesPerPageMov = 15;
    public int $unidadesDisponiblesTotalMov = 0;
    public array $unidadesDisponiblesMov = [];
    public array $serialesSeleccionadosMov = [];

    protected function rules(): array
    {
        return [
            'product_id'  => ['required', 'exists:products,id'],
            'type'        => ['required', 'in:ENTRADA,SALIDA'],
            'quantity'    => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
            'unit_cost'   => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'product_id.required' => 'Debes seleccionar un producto.',
            'quantity.required'   => 'La cantidad es obligatoria.',
            'quantity.min'        => 'La cantidad debe ser al menos 1.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getProductoSeleccionadoProperty(): ?Product
    {
        if (! $this->product_id) {
            return null;
        }
        $store = $this->getStoreProperty();
        if (! $store) {
            return null;
        }

        return Product::where('store_id', $store->id)
            ->where('id', $this->product_id)
            ->with(['category.attributes' => fn ($q) => $q->orderBy('name')])
            ->first();
    }

    public function abrirSelectorProducto(): void
    {
        $this->dispatch('open-select-item-for-row', rowId: 'movimiento-inventario', itemType: 'INVENTARIO');
    }

    public function abrirSelectorVarianteBatch(): void
    {
        $product = $this->productoSeleccionado;
        if (! $product || ! $product->isBatch()) {
            return;
        }
        $this->dispatch('open-select-batch-variant', productId: $product->id, rowId: 'movimiento-inventario', productName: $product->name, variantKeysInCart: []);
    }

    public function clearProduct(): void
    {
        $this->product_id = 0;
        $this->resetTypeSpecificFields();
    }

    #[On('item-selected')]
    public function onItemSelected($rowId, $id, $name, $type, $productType = null): void
    {
        if ($rowId !== 'movimiento-inventario' || $type !== 'INVENTARIO') {
            return;
        }
        $productType = $productType ?? 'simple';
        $this->product_id = (int) $id;

        $product = Product::where('id', $this->product_id)
            ->where('store_id', $this->getStoreProperty()?->id)
            ->first();

        if ($product) {
            if ($productType === 'batch') {
                $this->updatedProductId($this->product_id);
                $this->dispatch('open-select-batch-variant', productId: $id, rowId: 'movimiento-inventario', productName: $name, variantKeysInCart: []);
            } elseif ($productType === 'serialized' && $this->type === 'SALIDA') {
                $this->abrirModalSerialesMovimiento($id);
            } else {
                $this->updatedProductId($this->product_id);
            }
        } else {
            $this->updatedProductId($this->product_id);
        }
    }

    #[On('batch-variant-selected')]
    public function onBatchVariantSelected($rowId, $productId, $productName, $variantFeatures, $displayName, $totalStock = 0, $price = null, $productVariantId = null): void
    {
        if ($rowId !== 'movimiento-inventario') {
            return;
        }
        $this->product_id = (int) $productId;
        $this->updatedProductId($this->product_id);
        $this->product_variant_id = (int) $productVariantId;
        $this->selectedVariantDisplayName = $displayName ?: null;
        if ($this->type === MovimientoInventario::TYPE_SALIDA) {
            $this->quantity = (string) min(1, (int) $totalStock);
        } else {
            $this->quantity = '1';
        }
        $this->unit_cost = $price !== null && (float) $price >= 0 ? (string) $price : null;
    }

    public function resetForm(): void
    {
        $ref = 'INI-' . date('Y');
        $this->product_id  = 0;
        $this->type        = 'ENTRADA';
        $this->quantity    = '';
        $this->description = null;
        $this->unit_cost   = null;
        $this->serial_reference = $ref;
        $this->serial_items = [['serial_number' => '', 'cost' => '', 'features' => []]];
        $this->serials_selected = [];
        $this->serials_available = [];
        $this->batch_reference = $ref;
        $this->batch_expiration = null;
        $this->batch_items = [
            [
                'quantity' => '',
                'unit_cost' => '',
                'features' => [],
            ],
        ];
        $this->product_variant_id = null;
        $this->selectedVariantDisplayName = null;
        $this->batch_items_available = [];
        $this->productoSerializadoIdMov = null;
        $this->productoSerializadoNombreMov = '';
        $this->unidadesDisponiblesSearchMov = '';
        $this->unidadesDisponiblesPageMov = 1;
        $this->unidadesDisponiblesTotalMov = 0;
        $this->unidadesDisponiblesMov = [];
        $this->serialesSeleccionadosMov = [];
        $this->categoryAttributes = [];
        $this->resetValidation();
    }

    public function updatedProductId($value): void
    {
        $this->resetTypeSpecificFields();

        if (! $value) {
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $product = Product::where('id', $value)
            ->where('store_id', $store->id)
            ->with(['category.attributes' => fn ($q) => $q->orderBy('name')])
            ->first();

        if (! $product) {
            return;
        }

        $this->categoryAttributes = $product->category?->attributes->map(function ($attr) {
            return [
                'id' => $attr->id,
                'name' => $attr->name,
            ];
        })->values()->toArray();

        if ($product->isSerialized()) {
            $this->loadAvailableSerials($product);
        } elseif ($product->isBatch()) {
            $this->loadAvailableBatchItems($product);
        }
    }

    public function updatedType(): void
    {
        $this->resetTypeSpecificFields(false);

        if ($this->productoSeleccionado) {
            if ($this->productoSeleccionado->isSerialized()) {
                $this->loadAvailableSerials($this->productoSeleccionado);
            } elseif ($this->productoSeleccionado->isBatch()) {
                $this->loadAvailableBatchItems($this->productoSeleccionado);
            }
        }
    }

    protected function resetTypeSpecificFields(bool $resetBase = true): void
    {
        $ref = 'INI-' . date('Y');
        if ($resetBase) {
            $this->quantity = '';
            $this->unit_cost = null;
            $this->description = null;
        }

        $this->serial_reference = $ref;
        $this->serial_items = [['serial_number' => '', 'cost' => '', 'features' => []]];
        $this->serials_selected = [];
        $this->serials_available = [];

        $this->batch_reference = $ref;
        $this->batch_expiration = null;
        $this->batch_items = [
            [
                'quantity' => '',
                'unit_cost' => '',
                'features' => [],
            ],
        ];
        $this->product_variant_id = null;
        $this->selectedVariantDisplayName = null;
        $this->batch_items_available = [];
        $this->productoSerializadoIdMov = null;
        $this->productoSerializadoNombreMov = '';
        $this->unidadesDisponiblesSearchMov = '';
        $this->unidadesDisponiblesPageMov = 1;
        $this->unidadesDisponiblesTotalMov = 0;
        $this->unidadesDisponiblesMov = [];
        $this->serialesSeleccionadosMov = [];
    }

    public function abrirModalSerialesMovimiento(int $productId): void
    {
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
        $this->product_id = $productId;
        $this->productoSerializadoIdMov = $producto->id;
        $this->productoSerializadoNombreMov = $producto->name;
        $this->serialesSeleccionadosMov = [];
        $this->unidadesDisponiblesSearchMov = '';
        $this->unidadesDisponiblesPageMov = 1;
        $this->categoryAttributes = $producto->category?->attributes->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->toArray() ?? [];
        $this->loadAvailableSerials($producto);
        $this->cargarPaginaUnidadesMovimiento();
    }

    public function cargarPaginaUnidadesMovimiento(): void
    {
        $store = $this->getStoreProperty();
        if (! $store || $this->productoSerializadoIdMov === null) {
            return;
        }
        $query = ProductItem::where('store_id', $store->id)
            ->where('product_id', $this->productoSerializadoIdMov)
            ->where('status', ProductItem::STATUS_AVAILABLE);

        $search = trim($this->unidadesDisponiblesSearchMov);
        if ($search !== '') {
            $query->where('serial_number', 'like', '%' . $search . '%');
        }
        $this->unidadesDisponiblesTotalMov = $query->count();
        $this->unidadesDisponiblesMov = $query->orderBy('serial_number')
            ->offset(($this->unidadesDisponiblesPageMov - 1) * $this->unidadesDisponiblesPerPageMov)
            ->limit($this->unidadesDisponiblesPerPageMov)
            ->get()
            ->map(fn (ProductItem $item) => [
                'id' => $item->id,
                'serial_number' => $item->serial_number,
                'features' => $item->features,
            ])
            ->values()
            ->toArray();
    }

    public function irAPaginaUnidadesMovimiento(int $page): void
    {
        $maxPage = (int) max(1, ceil($this->unidadesDisponiblesTotalMov / $this->unidadesDisponiblesPerPageMov));
        $this->unidadesDisponiblesPageMov = max(1, min($page, $maxPage));
        $this->cargarPaginaUnidadesMovimiento();
    }

    public function updatedUnidadesDisponiblesSearchMov(): void
    {
        $this->unidadesDisponiblesPageMov = 1;
        $this->cargarPaginaUnidadesMovimiento();
    }

    public function cerrarModalSerialesMovimiento(): void
    {
        $product = $this->productoSeleccionado;
        $this->productoSerializadoIdMov = null;
        $this->productoSerializadoNombreMov = '';
        $this->unidadesDisponiblesMov = [];
        $this->unidadesDisponiblesTotalMov = 0;
        $this->serialesSeleccionadosMov = [];
        if ($product && $product->isSerialized()) {
            $this->loadAvailableSerials($product);
        }
    }

    public function confirmarSerialesMovimiento(): void
    {
        if (empty($this->serialesSeleccionadosMov)) {
            return;
        }
        $this->serials_selected = array_values($this->serialesSeleccionadosMov);
        $this->cerrarModalSerialesMovimiento();
    }

    protected function loadAvailableSerials(Product $product): void
    {
        if (! $product->isSerialized() || $this->type !== 'SALIDA') {
            $this->serials_available = [];
            return;
        }

        $this->serials_available = ProductItem::where('store_id', $this->storeId)
            ->where('product_id', $product->id)
            ->where('status', ProductItem::STATUS_AVAILABLE)
            ->orderBy('serial_number')
            ->pluck('serial_number')
            ->toArray();
    }

    protected function loadAvailableBatchItems(Product $product): void
    {
        if (! $product->isBatch()) {
            $this->batch_items_available = [];
            return;
        }

        // Cargar variantes del producto con su stock agregado
        $variants = ProductVariant::where('product_id', $product->id)
            ->where('is_active', true)
            ->with('batchItems')
            ->get();

        $this->batch_items_available = $variants->map(function (ProductVariant $variant) {
            $totalStock = $variant->batchItems->sum('quantity');
            $features = $variant->features ?? [];

            $parts = [];
            foreach ($features as $attrId => $value) {
                $attrName = collect($this->categoryAttributes)->firstWhere('id', (int) $attrId)['name'] ?? "Attr {$attrId}";
                $parts[] = "{$attrName}: {$value}";
            }

            return [
                'id' => $variant->id,
                'product_variant_id' => $variant->id,
                'display_name' => implode(', ', $parts) ?: '—',
                'quantity' => (int) $totalStock,
                'features' => $features,
            ];
        })->filter(fn ($v) => $v['quantity'] > 0)->values()->toArray();
    }

    public function addSerialItem(): void
    {
        $this->serial_items[] = ['serial_number' => '', 'cost' => '', 'features' => []];
    }

    public function removeSerialItem(int $index): void
    {
        if (isset($this->serial_items[$index]) && count($this->serial_items) > 1) {
            unset($this->serial_items[$index]);
            $this->serial_items = array_values($this->serial_items);
        }
    }

    public function addBatchItem(): void
    {
        $this->batch_items[] = [
            'quantity' => '',
            'unit_cost' => '',
            'features' => [],
        ];
    }

    public function removeBatchItem(int $index): void
    {
        if (isset($this->batch_items[$index]) && count($this->batch_items) > 1) {
            unset($this->batch_items[$index]);
            $this->batch_items = array_values($this->batch_items);
        }
    }

    public function save(InventarioService $inventarioService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para registrar movimientos en esta tienda.');
        }

        $product = Product::where('id', $this->product_id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $serialNumbers = [];
        $payload = [
            'product_id' => $product->id,
            'type' => $this->type,
            'description' => $this->description ?: null,
        ];

        if ($this->unit_cost !== null && $this->unit_cost !== '') {
            $payload['unit_cost'] = (float) $this->unit_cost;
        }

        if ($product->isSerialized()) {
            if ($this->type === \App\Models\MovimientoInventario::TYPE_ENTRADA) {
                [$serialItems, $reference] = $this->prepareSerialItemsEntrada();
                $payload['serial_items'] = $serialItems;
                $payload['reference'] = $reference;
                $payload['quantity'] = count($serialItems);
            } else {
                $serialNumbers = $this->prepareSerialsSalida();
                $payload['quantity'] = count($serialNumbers);
            }
        } else {
            if ($this->type === \App\Models\MovimientoInventario::TYPE_ENTRADA) {
                $batchData = $this->prepareBatchEntradaPayload();
                $payload['batch_data'] = $batchData;
                $payload['quantity'] = array_sum(array_column($batchData['items'], 'quantity'));
            } else {
                $this->prepareBatchSalidaValidation();
                // Usamos salida por variante FIFO en vez de batch_item_id directo
                $payload['_use_variant_fifo'] = true;
                $payload['product_variant_id'] = $this->product_variant_id;
                $payload['quantity'] = (int) $this->quantity;
            }
        }

        if (($payload['quantity'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a 0.',
            ]);
        }

        try {
            $useVariantFifo = $payload['_use_variant_fifo'] ?? false;
            unset($payload['_use_variant_fifo']);

            if ($useVariantFifo && ! empty($payload['product_variant_id'])) {
                $inventarioService->registrarSalidaPorVarianteFIFO(
                    $store,
                    Auth::id(),
                    $product->id,
                    (int) $payload['product_variant_id'],
                    (int) $payload['quantity'],
                    $payload['description'] ?? null,
                );
            } else {
                $inventarioService->registrarMovimiento(
                    $store,
                    Auth::id(),
                    $payload,
                    $serialNumbers
                );
            }

            $this->resetForm();

            return redirect()->route('stores.inventario', $store)
                ->with('success', 'Movimiento de inventario registrado correctamente.');
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'quantity' => $e->getMessage(),
            ]);
        }
    }

    protected function prepareSerialItemsEntrada(): array
    {
        $reference = trim($this->serial_reference);
        if ($reference === '') {
            $reference = 'INI-' . date('Y');
        }

        $items = [];
        foreach ($this->serial_items as $index => $row) {
            $serial = trim($row['serial_number'] ?? '');
            if ($serial === '') {
                continue;
            }
            $cost = (float) ($row['cost'] ?? 0);
            $features = [];
            foreach ($this->categoryAttributes as $attr) {
                $attrId = (string) $attr['id'];
                $value = $row['features'][$attrId] ?? null;
                if ($value !== null && $value !== '') {
                    $features[$attr['name']] = (string) $value;
                }
            }
            $items[] = [
                'serial_number' => $serial,
                'cost' => $cost,
                'features' => $features,
            ];
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'serial_items' => 'Debes agregar al menos una unidad con número de serie, atributos y costo.',
            ]);
        }

        return [$items, $reference];
    }

    protected function prepareSerialsSalida(): array
    {
        $serials = collect($this->serials_selected)->map(fn ($serial) => (string) $serial)->filter()->unique()->values()->toArray();

        if (empty($serials)) {
            throw ValidationException::withMessages([
                'serials_selected' => 'Debes seleccionar al menos un número de serie disponible.',
            ]);
        }

        $unknown = array_diff($serials, $this->serials_available);
        if (! empty($unknown)) {
            throw ValidationException::withMessages([
                'serials_selected' => 'Seleccionaste números de serie no disponibles en inventario.',
            ]);
        }

        return $serials;
    }

    protected function prepareBatchEntradaPayload(): array
    {
        if (trim($this->batch_reference) === '') {
            throw ValidationException::withMessages([
                'batch_reference' => 'Debes indicar la referencia del lote.',
            ]);
        }

        if (! $this->product_variant_id) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Debes seleccionar una variante del producto.',
            ]);
        }

        $qty = (int) $this->quantity;
        if ($qty < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a 0.',
            ]);
        }

        $unitCost = $this->unit_cost !== null && $this->unit_cost !== '' ? (float) $this->unit_cost : 0;

        return [
            'reference'       => $this->batch_reference,
            'expiration_date' => $this->batch_expiration ?: null,
            'items'           => [
                [
                    'product_variant_id' => $this->product_variant_id,
                    'quantity'           => $qty,
                    'unit_cost'          => $unitCost,
                ],
            ],
        ];
    }

    protected function prepareBatchSalidaValidation(): void
    {
        if (! $this->product_variant_id) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Debes seleccionar la variante a descontar.',
            ]);
        }

        $selected = collect($this->batch_items_available)->firstWhere('product_variant_id', $this->product_variant_id);
        if (! $selected) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'La variante seleccionada no está disponible.',
            ]);
        }

        $qty = (int) $this->quantity;
        if ($qty < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a 0.',
            ]);
        }

        if ($qty > (int) $selected['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => "Solo hay {$selected['quantity']} unidades disponibles en esa variante.",
            ]);
        }
    }

    public function render()
    {
        return view('livewire.create-movimiento-inventario-modal');
    }
}
