<?php

namespace App\Livewire;

use App\Models\Store;
use App\Models\AttributeGroup;
use App\Services\AttributeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditAttributeGroupModal extends Component
{
    public int $storeId;
    public ?int $groupId = null;

    public string $name = '';

    public function loadGroup($groupId = null)
    {
        if ($groupId === null) {
            return;
        }

        // Extraer el ID si viene como objeto
        if (is_array($groupId) && isset($groupId['id'])) {
            $groupId = $groupId['id'];
        } elseif (is_object($groupId) && isset($groupId->id)) {
            $groupId = $groupId->id;
        }

        $this->groupId = (int)$groupId;

        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $group = AttributeGroup::where('id', $this->groupId)
            ->where('store_id', $store->id)
            ->first();

        if ($group) {
            $this->name = $group->name;
            
            // Abrir el modal
            $this->dispatch('open-modal', 'edit-attribute-group');
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

    public function update(AttributeService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (!$store || !Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar grupos en esta tienda.');
        }

        if (!$this->groupId) {
            return;
        }

        try {
            $service->updateAttributeGroup($store, $this->groupId, [
                'name' => $this->name,
            ]);

            $this->reset(['name', 'groupId']);
            $this->resetValidation();

            return redirect()->route('stores.attribute-groups', $store)
                ->with('success', 'Grupo de atributos actualizado correctamente.');
        } catch (\Exception $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.edit-attribute-group-modal');
    }
}
