<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Store;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateCategoryModal extends Component
{
    public int $storeId;

    public string $name = '';
    public ?string $parent_id = null;

    public function setParentId($parentId): void
    {
        $this->parent_id = $parentId ? (string) $parentId : null;
    }

    public function clearParentId(): void
    {
        $this->parent_id = null;
    }

    public function getParentCategoryName(): ?string
    {
        if (! $this->parent_id) {
            return null;
        }
        $category = Category::find($this->parent_id);

        return $category?->name;
    }

    protected function rules(): array
    {
        $store = $this->getStoreProperty();
        $categoryIds = $store ? $store->categories()->pluck('id')->toArray() : [];

        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'parent_id' => [
                'nullable',
                ...(count($categoryIds) > 0 ? [Rule::in($categoryIds)] : []),
            ],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        $store = Store::find($this->storeId);

        return $store ? $store->load('categories') : null;
    }

    public function save(CategoryService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear categorías en esta tienda.');
        }

        $service->createCategory($store, [
            'name' => $this->name,
            'parent_id' => $this->parent_id ?: null,
        ]);

        $this->reset(['name', 'parent_id']);
        $this->resetValidation();

        return redirect()->route('stores.categories', $store);
    }

    public function render()
    {
        return view('livewire.create-category-modal');
    }
}
