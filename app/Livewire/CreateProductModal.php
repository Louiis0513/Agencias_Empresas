<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Store;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateProductModal extends Component
{
    public int $storeId;

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

    public function save(ProductService $service)
    {
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
                $normalized[$attrId] = ($val === true || $val === '1' || $val === 1) ? '1' : '0';
            } elseif ($val !== null && $val !== '') {
                $normalized[$attrId] = (string) $val;
            }
        }

        try {
            $service->createProduct($store, [
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
            'location', 'type', 'is_active', 'attribute_values',
        ]);
        $this->resetValidation();

        return redirect()->route('stores.products', $store);
    }

    public function render()
    {
        return view('livewire.create-product-modal');
    }
}
