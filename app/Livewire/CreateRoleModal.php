<?php

namespace App\Livewire;

use App\Models\Role;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateRoleModal extends Component
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

    public function save()
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear roles en esta tienda.');
        }

        Role::create([
            'store_id' => $store->id,
            'name' => $this->name,
        ]);

        $this->reset(['name']);
        $this->resetValidation();

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol creado correctamente.');
    }

    public function render()
    {
        return view('livewire.create-role-modal');
    }
}
