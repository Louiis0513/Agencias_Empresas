<?php

namespace App\Livewire;

use App\Models\BatchItem;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use App\Services\InventarioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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
    /** Batch → salir */
    public ?int $batch_item_id = null;
    public array $batch_items_available = [];

    public array $categoryAttributes = [];

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

    public function getProductosProperty()
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }
        return app(InventarioService::class)->productosConInventario($store);
    }

    public function getProductoSeleccionadoProperty(): ?Product
    {
        if (! $this->product_id) {
            return null;
        }
        return $this->productos->firstWhere('id', $this->product_id);
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
        $this->batch_item_id = null;
        $this->batch_items_available = [];
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
        $this->batch_item_id = null;
        $this->batch_items_available = [];
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

        $this->batch_items_available = BatchItem::with('batch')
            ->where('quantity', '>', 0)
            ->whereHas('batch', function ($q) use ($product) {
                $q->where('store_id', $this->storeId)->where('product_id', $product->id);
            })
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function (BatchItem $item) {
                return [
                    'id' => $item->id,
                    'reference' => $item->batch->reference,
                    'expiration_date' => optional($item->batch->expiration_date)->format('Y-m-d'),
                    'quantity' => $item->quantity,
                    'features' => $item->features ?? [],
                ];
            })
            ->toArray();
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
                $payload['batch_item_id'] = $this->batch_item_id;
                $payload['quantity'] = (int) $this->quantity;
            }
        }

        if (($payload['quantity'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a 0.',
            ]);
        }

        try {
            $inventarioService->registrarMovimiento(
                $store,
                Auth::id(),
                $payload,
                $serialNumbers
            );

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

        $items = [];
        foreach ($this->batch_items as $index => $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);

            if ($qty < 1) {
                continue;
            }

            $features = [];
            foreach ($this->categoryAttributes as $attr) {
                $attrId = (string) $attr['id'];
                $value = $item['features'][$attrId] ?? null;
                if ($value !== null && $value !== '') {
                    $features[$attr['name']] = (string) $value;
                }
            }

            $items[] = [
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'features' => $features,
            ];
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'batch_items' => 'Debes agregar al menos una variante del lote con cantidad válida.',
            ]);
        }

        return [
            'reference' => $this->batch_reference,
            'expiration_date' => $this->batch_expiration ?: null,
            'items' => $items,
        ];
    }

    protected function prepareBatchSalidaValidation(): void
    {
        if (! $this->batch_item_id) {
            throw ValidationException::withMessages([
                'batch_item_id' => 'Debes seleccionar el lote/variante a descontar.',
            ]);
        }

        $selected = collect($this->batch_items_available)->firstWhere('id', $this->batch_item_id);
        if (! $selected) {
            throw ValidationException::withMessages([
                'batch_item_id' => 'El lote seleccionado no está disponible.',
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
                'quantity' => "Solo hay {$selected['quantity']} unidades disponibles en ese lote/variante.",
            ]);
        }
    }

    public function render()
    {
        return view('livewire.create-movimiento-inventario-modal');
    }
}
