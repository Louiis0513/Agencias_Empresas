<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditProductModal extends Component
{
    public int $storeId;
    public ?int $productId = null;

    public string $name = '';
    public string $price = '0';
    public string $location = '';
    public bool $is_active = true;

    /** Solo para reenviar al guardar y no perder categoría/atributos. No se muestran en el formulario. */
    public ?string $category_id = null;
    /** @var array<int, string> Valores de atributos actuales (solo para reenviar al guardar). */
    public array $attribute_values = [];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function loadProduct($productId = null)
    {
        // Si se llama desde Alpine.js, el parámetro puede venir como objeto { id: X }
        if ($productId === null) {
            return;
        }
        
        // Extraer el ID si viene como objeto
        if (is_array($productId) && isset($productId['id'])) {
            $productId = $productId['id'];
        } elseif (is_object($productId) && isset($productId->id)) {
            $productId = $productId->id;
        }
        
        $this->productId = (int)$productId;
        
        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $product = Product::where('id', $this->productId)
            ->where('store_id', $store->id)
            ->with(['category', 'attributeValues.attribute'])
            ->first();

        if ($product) {
            $this->name = $product->name;
            $this->price = (string) $product->price;
            $this->location = $product->location ?? '';
            $this->is_active = (bool) $product->is_active;
            $this->category_id = $product->category_id ? (string) $product->category_id : null;

            // Mantener attribute_values para reenviarlos al guardar (no se editan en este modal)
            $this->attribute_values = [];
            $category = Category::where('id', $this->category_id)
                ->where('store_id', $store->id)
                ->with(['attributes.options'])
                ->first();
            if ($category) {
                foreach ($category->attributes as $attr) {
                    $existingValue = $product->attributeValues->firstWhere('attribute_id', $attr->id);
                    if ($attr->type === 'boolean') {
                        $this->attribute_values[$attr->id] = $existingValue && $existingValue->value === '1' ? '1' : '0';
                    } else {
                        $this->attribute_values[$attr->id] = $existingValue ? $existingValue->value : '';
                    }
                }
            }

            $this->dispatch('open-modal', 'edit-product');
        }
    }

    public function update(ProductService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar productos en esta tienda.');
        }

        if (! $this->productId) {
            return;
        }

        $normalized = [];
        foreach ($this->attribute_values as $attrId => $val) {
            // Para booleanos, incluir tanto '1' como '0'
            if (is_string($val) && ($val === '0' || $val === '1')) {
                $normalized[$attrId] = $val;
                continue;
            }
            // Para otros tipos, omitir valores vacíos
            if ($val === null || $val === '') {
                continue;
            }
            $normalized[$attrId] = is_bool($val) || $val === true || $val === '1' || $val === 1 || $val === 'true' ? '1' : (string) $val;
        }

        try {
            $service->updateProduct($store, $this->productId, [
                'name' => $this->name,
                'price' => (float) $this->price,
                'location' => $this->location ?: null,
                'is_active' => $this->is_active,
                'category_id' => $this->category_id,
                'attribute_values' => $normalized,
            ]);
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
            return;
        }

        $this->reset(['name', 'price', 'location', 'is_active', 'category_id', 'attribute_values', 'productId']);
        $this->resetValidation();

        return redirect()->route('stores.products', $store)
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function getCategoryProperty()
    {
        if (!$this->category_id) {
            return null;
        }
        
        $store = $this->getStoreProperty();
        if (!$store) {
            return null;
        }
        
        return Category::where('id', $this->category_id)
            ->where('store_id', $store->id)
            ->with(['attributes.options'])
            ->first();
    }

    public function render()
    {
        $category = $this->getCategoryProperty();
        return view('livewire.edit-product-modal', [
            'category' => $category,
        ]);
    }
}
