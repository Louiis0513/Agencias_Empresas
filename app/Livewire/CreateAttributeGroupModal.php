<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\AttributeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateAttributeGroupModal extends Component
{
    public int $storeId;

    public string $name = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function save(AttributeService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear grupos en esta tienda.');
        }

        $service->createAttributeGroup($store, ['name' => $this->name]);

        $this->reset(['name']);
        $this->resetValidation();

        return redirect()->route('stores.attribute-groups', $store);
    }

    public function render()
    {
        return view('livewire.create-attribute-group-modal');
    }
}
