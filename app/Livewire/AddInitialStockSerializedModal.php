<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AddInitialStockSerializedModal extends Component
{
    public int $storeId;
    public int $productId;

    /** Unidades serializadas a agregar: [['serial_number' => '', 'cost' => '', 'price' => '', 'attribute_values' => [...]], ...] */
    public array $serializedItems = [];

    public function mount(int $storeId, int $productId): void
    {
        $this->storeId = $storeId;
        $this->productId = $productId;
    }

    public function getProductProperty(): ?Product
    {
        return Product::where('id', $this->productId)
            ->where('store_id', $this->storeId)
            ->with(['category.attributes' => fn ($q) => $q->with('options')->orderByPivot('position')])
            ->first();
    }

    /**
     * Agregar una nueva unidad serializada.
     */
    public function addSerializedItem(): void
    {
        $product = $this->getProductProperty();
        if (! $product || ! $product->category) {
            return;
        }

        $item = [
            'serial_number' => '',
            'cost' => '',
            'price' => '',
            'attribute_values' => [],
        ];

        foreach ($product->category->attributes as $attr) {
            $item['attribute_values'][$attr->id] = $attr->type === 'boolean' ? '0' : '';
        }

        $this->serializedItems[] = $item;
    }

    /**
     * Eliminar una unidad por índice.
     */
    public function removeSerializedItem(int $index): void
    {
        if (isset($this->serializedItems[$index])) {
            unset($this->serializedItems[$index]);
            $this->serializedItems = array_values($this->serializedItems);
        }
    }

    /**
     * Guardar las unidades serializadas.
     */
    public function save(): void
    {
        $product = $this->getProductProperty();
        if (! $product || ! $product->isSerialized()) {
            $this->addError('general', 'El producto no es serializado.');
            return;
        }

        if (empty($this->serializedItems)) {
            $this->addError('general', 'Debes agregar al menos una unidad.');
            return;
        }

        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para modificar productos en esta tienda.');
        }

        $validated = [];
        $serialNumbers = [];

        foreach ($this->serializedItems as $index => $item) {
            $serial = trim($item['serial_number'] ?? '');
            if (empty($serial)) {
                $this->addError("serializedItems.{$index}.serial_number", 'El número de serie es obligatorio.');
                continue;
            }

            if (in_array($serial, $serialNumbers, true)) {
                $this->addError("serializedItems.{$index}.serial_number", "El número de serie «{$serial}» está duplicado.");
                continue;
            }

            // Verificar que el serial no exista ya
            $exists = ProductItem::where('store_id', $this->storeId)
                ->where('product_id', $this->productId)
                ->where('serial_number', $serial)
                ->exists();

            if ($exists) {
                $this->addError("serializedItems.{$index}.serial_number", "El número de serie «{$serial}» ya existe en el inventario.");
                continue;
            }

            $serialNumbers[] = $serial;
            $priceValue = ! empty($item['price']) ? (float) $item['price'] : null;
            
            $validated[] = [
                'serial_number' => $serial,
                'cost' => (float) ($item['cost'] ?? 0),
                'price' => $priceValue,
                'attribute_values' => $item['attribute_values'] ?? [],
            ];
        }

        if ($this->getErrorBag()->hasAny()) {
            return;
        }

        try {
            DB::transaction(function () use ($product, $store, $validated) {
                $userId = Auth::id();
                $reference = 'INI-' . date('Y');

                foreach ($validated as $item) {
                    $features = [];
                    foreach ($item['attribute_values'] as $attrId => $value) {
                        if ($value !== '' && $value !== null && $value !== '0') {
                            $features[$attrId] = $value;
                        }
                    }

                    $productItemData = [
                        'store_id' => $this->storeId,
                        'product_id' => $this->productId,
                        'serial_number' => $item['serial_number'],
                        'cost' => (float) $item['cost'],
                        'status' => ProductItem::STATUS_AVAILABLE,
                        'batch' => $reference,
                        'features' => ! empty($features) ? $features : null,
                    ];
                    
                    if ($item['price'] !== null) {
                        $productItemData['price'] = (float) $item['price'];
                    }
                    
                    ProductItem::create($productItemData);
                }

                // Actualizar stock del producto
                $count = count($validated);
                $product->increment('stock', $count);

                // Actualizar costo ponderado
                $items = ProductItem::where('product_id', $product->id)
                    ->where('store_id', $product->store_id)
                    ->where('status', ProductItem::STATUS_AVAILABLE)
                    ->get();
                $totalCost = $items->sum('cost');
                $qty = $items->count();
                $product->cost = $qty > 0 ? (float) round($totalCost / $qty, 2) : 0.0;
                $product->save();
            });

            $this->serializedItems = [];
            $this->dispatch('close-modal', 'add-initial-stock-serialized');
            $this->dispatch('$refresh');
            session()->flash('success', 'Stock inicial agregado correctamente.');
        } catch (\Exception $e) {
            $this->addError('general', 'Error al guardar: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.add-initial-stock-serialized-modal');
    }
}
