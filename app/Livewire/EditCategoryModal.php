<?php

namespace App\Livewire;

use App\Models\Store;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EditCategoryModal extends Component
{
    public int $storeId;
    public ?int $categoryId = null;

    public string $name = '';
    public ?string $parent_id = null;

    protected function rules(): array
    {
        $store = $this->getStoreProperty();
        $availableCategoryIds = $this->getAvailableParentIds();

        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'parent_id' => [
                'nullable',
                ...(count($availableCategoryIds) > 0 ? [Rule::in($availableCategoryIds)] : []),
            ],
        ];
    }

    protected function getAvailableParentIds(): array
    {
        if (!$this->categoryId) {
            $store = $this->getStoreProperty();
            return $store ? $store->categories()->pluck('id')->toArray() : [];
        }

        $store = $this->getStoreProperty();
        if (!$store) {
            return [];
        }

        $category = Category::where('id', $this->categoryId)
            ->where('store_id', $store->id)
            ->first();

        if (!$category) {
            return $store->categories()->pluck('id')->toArray();
        }

        // Obtener todos los IDs de categorías que no pueden ser padres
        // (la categoría actual y todas sus descendientes)
        $excludedIds = [$category->id];
        $this->collectDescendantIds($category, $excludedIds);

        // Retornar todas las categorías excepto las excluidas
        return $store->categories()
            ->whereNotIn('id', $excludedIds)
            ->pluck('id')
            ->toArray();
    }

    protected function collectDescendantIds(Category $category, array &$ids): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<Category> $children */
        $children = Category::where('parent_id', $category->id)->get();
        foreach ($children as $child) {
            if ($child instanceof Category) {
                $ids[] = $child->id;
                $this->collectDescendantIds($child, $ids);
            }
        }
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function loadCategory($categoryId = null)
    {
        // Si se llama desde Alpine.js, el parámetro puede venir como null
        // y necesitamos obtenerlo del request
        if ($categoryId === null) {
            return;
        }
        
        $this->categoryId = (int)$categoryId;
        
        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $category = Category::where('id', $this->categoryId)
            ->where('store_id', $store->id)
            ->first();

        if ($category) {
            $this->name = $category->name;
            // Convertir null a string vacío para que el select lo reconozca
            $this->parent_id = $category->parent_id ? (string)$category->parent_id : '';
            
            // Abrir el modal
            $this->dispatch('open-modal', 'edit-category');
        }
    }

    public function update(CategoryService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (!$store || !Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar categorías en esta tienda.');
        }

        if (!$this->categoryId) {
            return;
        }

        try {
            $service->updateCategory($store, $this->categoryId, [
                'name' => $this->name,
                'parent_id' => $this->parent_id ?: null,
            ]);

            $this->reset(['name', 'parent_id', 'categoryId']);
            $this->resetValidation();

            return redirect()->route('stores.categories', $store)
                ->with('success', 'Categoría actualizada correctamente.');
        } catch (\Exception $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function getAvailableCategoriesProperty()
    {
        $store = $this->getStoreProperty();
        if (!$store) {
            return collect();
        }

        // Si no hay categoryId, retornar todas las categorías
        if (!$this->categoryId) {
            return Category::where('store_id', $store->id)
                ->orderBy('name')
                ->get();
        }

        $category = Category::where('id', $this->categoryId)
            ->where('store_id', $store->id)
            ->first();

        if (!$category) {
            return Category::where('store_id', $store->id)
                ->orderBy('name')
                ->get();
        }

        // Excluir la categoría actual y todas sus descendientes
        $excludedIds = [$category->id];
        $this->collectDescendantIds($category, $excludedIds);

        return Category::where('store_id', $store->id)
            ->whereNotIn('id', $excludedIds)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.edit-category-modal');
    }
}
