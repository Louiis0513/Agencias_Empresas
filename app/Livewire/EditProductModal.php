<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Livewire\CreateProductModal;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EditProductModal extends Component
{
    public int $storeId;
    public ?int $productId = null;

    public string $name = '';
    public string $barcode = '';
    public string $sku = '';
    public ?string $category_id = null;
    public string $price = '0';
    public string $cost = '0';
    public string $stock = '0';
    public string $location = '';
    public string $type = '';
    public bool $is_active = true;

    /** @var array<int, string> Valores de atributos: [attribute_id => value] */
    public array $attribute_values = [];

    protected function rules(): array
    {
        $store = $this->getStoreProperty();
        $categoryIds = $store ? $this->getCategoriesWithAttributesIds() : [];

        $rules = [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'category_id' => [
                'required',
                Rule::in($categoryIds),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_keys(CreateProductModal::typeOptions()))],
            'is_active' => ['boolean'],
        ];

        $category = $this->getSelectedCategoryProperty();
        if ($category) {
            foreach ($category->attributes as $attr) {
                $key = "attribute_values.{$attr->id}";
                $required = (bool) ($attr->pivot->is_required ?? $attr->is_required);
                if ($attr->type === 'boolean') {
                    $rules[$key] = [$required ? 'required' : 'nullable', 'string', 'in:0,1'];
                } elseif ($attr->type === 'select') {
                    $opts = $attr->options->pluck('value')->toArray();
                    $rules[$key] = $required
                        ? ['required', 'string', Rule::in($opts)]
                        : ['nullable', 'string', Rule::in(array_merge([''], $opts))];
                } else {
                    $rules[$key] = [$required ? 'required' : 'nullable', 'string', 'max:255'];
                }
            }
        }

        return $rules;
    }

    protected function messages(): array
    {
        $msgs = [
            'category_id.required' => 'Debes seleccionar una categoría. Crea categorías y asígnales atributos antes de crear productos.',
        ];
        $category = $this->getSelectedCategoryProperty();
        if ($category) {
            foreach ($category->attributes as $attr) {
                $req = (bool) ($attr->pivot->is_required ?? $attr->is_required);
                if ($req) {
                    $msgs["attribute_values.{$attr->id}.required"] = "El atributo «{$attr->name}» es obligatorio.";
                }
            }
        }
        return $msgs;
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    /** Categorías que tienen al menos un atributo asignado. */
    public function getCategoriesWithAttributesProperty()
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }

        return Category::where('store_id', $store->id)
            ->whereHas('attributes')
            ->with(['attributes' => fn ($q) => $q->with('options')])
            ->orderBy('name')
            ->get();
    }

    public function getCategoriesWithAttributesIds(): array
    {
        return $this->getCategoriesWithAttributesProperty()->pluck('id')->toArray();
    }

    public function getTypeOptionsProperty(): array
    {
        return CreateProductModal::typeOptions();
    }

    /** Categoría seleccionada con sus atributos (para campos dinámicos). */
    public function getSelectedCategoryProperty(): ?Category
    {
        if (! $this->category_id) {
            return null;
        }

        return Category::where('id', $this->category_id)
            ->where('store_id', $this->getStoreProperty()?->id)
            ->with(['attributes' => fn ($q) => $q->with(['options', 'groups'])->orderByPivot('position')])
            ->first();
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
            $this->barcode = $product->barcode ?? '';
            $this->sku = $product->sku ?? '';
            $this->category_id = $product->category_id ? (string)$product->category_id : null;
            $this->price = (string)$product->price;
            $this->cost = (string)$product->cost;
            $this->stock = (string)$product->stock;
            $this->location = $product->location ?? '';
            $allowedTypes = array_keys(CreateProductModal::typeOptions());
            $this->type = in_array($product->type ?? '', $allowedTypes) ? $product->type : MovimientoInventario::PRODUCT_TYPE_BATCH;
            $this->is_active = $product->is_active;

            // Cargar valores de atributos existentes
            $this->attribute_values = [];
            $category = $this->getSelectedCategoryProperty();
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
            
            // Abrir el modal
            $this->dispatch('open-modal', 'edit-product');
        }
    }

    public function updatedCategoryId(): void
    {
        // Si cambia la categoría, reinicializar los valores de atributos
        $this->attribute_values = [];
        $cat = $this->getSelectedCategoryProperty();
        if ($cat) {
            foreach ($cat->attributes as $attr) {
                $this->attribute_values[$attr->id] = $attr->type === 'boolean' ? '0' : '';
            }
        }
        $this->resetValidation();
    }

    /**
     * Manejar cambios en valores de atributos booleanos para asegurar que siempre sean strings.
     */
    public function updatedAttributeValues($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) !== 2 || $parts[0] !== 'attribute_values') {
            return;
        }

        $attrId = (int) $parts[1];
        $this->normalizeBooleanAttribute($attrId);
    }

    /**
     * Normalizar todos los atributos booleanos antes de validar.
     */
    protected function normalizeBooleanAttributes(): void
    {
        $category = $this->getSelectedCategoryProperty();
        if (!$category) {
            return;
        }

        foreach ($this->attribute_values as $attrId => $val) {
            $attr = $category->attributes->firstWhere('id', (int) $attrId);
            if ($attr && $attr->type === 'boolean') {
                $this->normalizeBooleanAttribute($attrId);
            }
        }
    }

    /**
     * Normalizar un atributo booleano específico.
     */
    protected function normalizeBooleanAttribute(int $attrId): void
    {
        $val = $this->attribute_values[$attrId] ?? null;
        
        if ($val === true || $val === '1' || $val === 1 || $val === 'true') {
            $this->attribute_values[$attrId] = '1';
        } else {
            $this->attribute_values[$attrId] = '0';
        }
    }

    public function update(ProductService $service)
    {
        // Normalizar valores booleanos antes de validar
        $this->normalizeBooleanAttributes();
        
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar productos en esta tienda.');
        }

        if (!$this->productId) {
            return;
        }

        $category = $this->getSelectedCategoryProperty();
        $normalized = [];
        foreach ($this->attribute_values as $attrId => $val) {
            $attr = $category->attributes->firstWhere('id', (int) $attrId);
            if (! $attr) {
                continue;
            }
            if ($attr->type === 'boolean') {
                if ($val === true || $val === '1' || $val === 1 || $val === 'true') {
                    $normalized[$attrId] = '1';
                } else {
                    $normalized[$attrId] = '0';
                }
            } elseif ($val !== null && $val !== '') {
                $normalized[$attrId] = (string) $val;
            }
        }

        try {
            $service->updateProduct($store, $this->productId, [
                'name' => $this->name,
                'barcode' => $this->barcode ?: null,
                'sku' => $this->sku ?: null,
                'category_id' => $this->category_id,
                'price' => (float) $this->price,
                'cost' => (float) $this->cost,
                'stock' => (int) $this->stock,
                'location' => $this->location ?: null,
                'type' => $this->type ?: null,
                'is_active' => $this->is_active,
                'attribute_values' => $normalized,
            ]);
        } catch (\Exception $e) {
            $this->addError('category_id', $e->getMessage());
            return;
        }

        $this->reset([
            'name', 'barcode', 'sku', 'category_id', 'price', 'cost', 'stock',
            'location', 'type', 'is_active', 'attribute_values', 'productId',
        ]);
        $this->resetValidation();

        return redirect()->route('stores.products', $store)
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function render()
    {
        $this->categoriesWithAttributes;
        return view('livewire.edit-product-modal');
    }
}
