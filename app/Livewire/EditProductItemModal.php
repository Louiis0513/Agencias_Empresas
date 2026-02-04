<?php

namespace App\Livewire;

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
    /** Atributos como texto "clave: valor" uno por línea */
    public string $featuresText = '';

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
            'featuresText' => ['nullable', 'string'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
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
        $this->featuresText = '';
        if (! empty($item->features) && is_array($item->features)) {
            $lines = [];
            foreach ($item->features as $k => $v) {
                $lines[] = $k . ': ' . (is_string($v) ? $v : json_encode($v));
            }
            $this->featuresText = implode("\n", $lines);
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

        $features = [];
        $lines = array_filter(array_map('trim', explode("\n", $this->featuresText)));
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if ($key !== '') {
                    $features[$key] = $value;
                }
            }
        }

        $item->update([
            'serial_number' => trim($this->serial_number),
            'price' => $this->price !== '' ? (float) $this->price : null,
            'status' => $this->status,
            'features' => $features ?: null,
        ]);

        $this->dispatch('close-modal', 'edit-product-item');
        $this->redirect(request()->header('Referer') ?: route('stores.products.show', [$store, $this->productId]), navigate: true);
    }

    public function render()
    {
        return view('livewire.edit-product-item-modal');
    }
}
