<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EditProductItemModal extends Component
{
    public int $storeId;
    public int $productId;

    public ?int $productItemId = null;
    public string $serial_number = '';
    public string $price = '';
    public string $status = 'AVAILABLE';
    /** Array asociativo: [attribute_id => value] */
    public array $attributeValues = [];

    protected function rules(): array
    {
        $store = $this->getStoreProperty();
        $productItem = $this->productItemId ? ProductItem::find($this->productItemId) : null;

        return [
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_items', 'serial_number')
                    ->where('store_id', $store?->id)
                    ->where('product_id', $this->productId)
                    ->ignore($this->productItemId),
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:AVAILABLE,SOLD,RESERVED,DEFECTIVE'],
            'attributeValues.*' => ['nullable', 'string'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getProductProperty(): ?Product
    {
        return Product::where('id', $this->productId)
            ->where('store_id', $this->storeId)
            ->with(['category.attributes'])
            ->first();
    }

    public function loadProductItem($id = null): void
    {
        if ($id === null) {
            return;
        }
        if (is_array($id) && isset($id['id'])) {
            $id = $id['id'];
        } elseif (is_object($id) && isset($id->id)) {
            $id = $id->id;
        }

        $this->productItemId = (int) $id;
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $item = ProductItem::where('id', $this->productItemId)
            ->where('store_id', $store->id)
            ->where('product_id', $this->productId)
            ->first();

        if (! $item) {
            return;
        }

        $this->serial_number = $item->serial_number;
        $this->price = $item->price !== null ? (string) $item->price : '';
        $this->status = $item->status ?? ProductItem::STATUS_AVAILABLE;
        
        // Cargar valores de atributos desde features del item
        $this->attributeValues = [];
        $product = $this->getProductProperty();
        if ($product && $product->category && $product->category->attributes) {
            foreach ($product->category->attributes as $attribute) {
                $attrId = (string) $attribute->id;
                // Buscar el valor en features del item (puede estar como string o int)
                $value = null;
                if (!empty($item->features) && is_array($item->features)) {
                    $value = $item->features[$attrId] ?? $item->features[$attribute->id] ?? null;
                }
                $this->attributeValues[$attrId] = $value !== null ? (string) $value : '';
            }
        }

        $this->resetValidation();
        $this->dispatch('open-modal', 'edit-product-item');
    }

    public function update(): void
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || $this->productItemId === null) {
            return;
        }

        $item = ProductItem::where('id', $this->productItemId)
            ->where('store_id', $store->id)
            ->where('product_id', $this->productId)
            ->firstOrFail();

        // Convertir attributeValues a formato features (usando ID numérico como clave)
        $features = [];
        foreach ($this->attributeValues as $attrId => $value) {
            $value = trim($value);
            if ($value !== '') {
                $features[(int) $attrId] = $value;
            }
        }

        $item->update([
            'serial_number' => trim($this->serial_number),
            'price' => $this->price !== '' ? (float) $this->price : null,
            'status' => $this->status,
            'features' => !empty($features) ? $features : null,
        ]);

        $this->dispatch('close-modal', 'edit-product-item');
        $this->redirect(request()->header('Referer') ?: route('stores.products.show', [$store, $this->productId]), navigate: true);
    }

    public function render()
    {
        $product = $this->getProductProperty();
        $attributes = $product && $product->category ? $product->category->attributes : collect();
        
        return view('livewire.edit-product-item-modal', [
            'attributes' => $attributes,
        ]);
    }
}
