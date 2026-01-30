<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Store;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateProductModal extends Component
{
    public int $storeId;

    public bool $fromPurchase = false;

    /** RowId de la fila en compra cuando se abre desde el modal de selección */
    public string $compraRowId = '';

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

    #[On('open-create-product-from-compra')]
    public function setCompraRowId(string $rowId = ''): void
    {
        if ($this->fromPurchase) {
            $this->compraRowId = $rowId;
        }
    }

    public function mount(int $storeId, bool $fromPurchase = false): void
    {
        $this->storeId = $storeId;
        $this->fromPurchase = $fromPurchase;
        if ($fromPurchase) {
            $this->stock = '0';
        }
    }

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
            'type' => ['nullable', 'string', 'max:255'],
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
        $store = Store::find($this->storeId);

        return $store ? $store->load('categories') : null;
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

    public function updatedCategoryId(): void
    {
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
        // Extraer el ID del atributo de la clave (formato: "attribute_values.7")
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
        
        // Convertir cualquier valor booleano a string '0' o '1'
        if ($val === true || $val === '1' || $val === 1 || $val === 'true') {
            $this->attribute_values[$attrId] = '1';
        } else {
            $this->attribute_values[$attrId] = '0';
        }
    }

    public function save(ProductService $service)
    {
        // Normalizar valores booleanos antes de validar
        $this->normalizeBooleanAttributes();
        
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear productos en esta tienda.');
        }

        $category = $this->getSelectedCategoryProperty();
        $normalized = [];
        foreach ($this->attribute_values as $attrId => $val) {
            $attr = $category->attributes->firstWhere('id', (int) $attrId);
            if (! $attr) {
                continue;
            }
            if ($attr->type === 'boolean') {
                // Asegurar que siempre sea string '0' o '1'
                if ($val === true || $val === '1' || $val === 1 || $val === 'true') {
                    $normalized[$attrId] = '1';
                } else {
                    $normalized[$attrId] = '0';
                }
            } elseif ($val !== null && $val !== '') {
                $normalized[$attrId] = (string) $val;
            }
        }

        $stock = $this->fromPurchase ? 0 : (int) $this->stock;

        try {
            $product = $service->createProduct($store, [
                'name' => $this->name,
                'barcode' => $this->barcode ?: null,
                'sku' => $this->sku ?: null,
                'category_id' => $this->category_id,
                'price' => (float) $this->price,
                'cost' => (float) $this->cost,
                'stock' => $stock,
                'location' => $this->location ?: null,
                'type' => $this->type ?: null,
                'is_active' => $this->is_active,
                'attribute_values' => $normalized,
            ]);
        } catch (\Exception $e) {
            $this->addError('category_id', $e->getMessage());

            return;
        }

        $compraRowId = $this->compraRowId;

        $this->reset([
            'name', 'barcode', 'sku', 'category_id', 'price', 'cost', 'stock',
            'location', 'type', 'is_active', 'attribute_values', 'compraRowId',
        ]);
        $this->resetValidation();

        if ($this->fromPurchase) {
            $this->dispatch('item-selected', rowId: $compraRowId, id: $product->id, name: $product->name, type: 'INVENTARIO');
            $this->dispatch('close-modal', 'create-product-from-compra');
            $this->dispatch('close-modal', 'select-item-compra');

            return;
        }

        return redirect()->route('stores.products', $store);
    }

    public function render()
    {
        // Asegurar que la propiedad se evalúe al renderizar
        $this->categoriesWithAttributes;
        return view('livewire.create-product-modal');
    }
}
