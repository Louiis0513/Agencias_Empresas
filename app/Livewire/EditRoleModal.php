<?php

namespace App\Livewire;

use App\Models\Role;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditRoleModal extends Component
{
    public int $storeId;

    public ?int $roleId = null;

    public string $name = '';

    public function loadRole($roleId = null)
    {
        if ($roleId === null) {
            return;
        }

        if (is_array($roleId) && isset($roleId['id'])) {
            $roleId = $roleId['id'];
        } elseif (is_object($roleId) && isset($roleId->id)) {
            $roleId = $roleId->id;
        }

        $this->roleId = (int) $roleId;

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $role = Role::where('id', $this->roleId)
            ->where('store_id', $store->id)
            ->first();

        if ($role) {
            $this->name = $role->name;
            $this->dispatch('open-modal', 'edit-role');
        }
    }

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

    public function update()
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar roles en esta tienda.');
        }

        if (! $this->roleId) {
            return;
        }

        $role = Role::where('id', $this->roleId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $role->update(['name' => $this->name]);

        $this->reset(['name', 'roleId']);
        $this->resetValidation();

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.edit-role-modal');
    }
}
