<?php

namespace App\Livewire;

use App\Models\AttributeGroup;
use App\Models\Attribute;
use App\Models\Store;
use App\Services\AttributeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EditAttributeModal extends Component
{
    public int $storeId;

    /** Si se usa en la página de grupos (redirect allí tras crear). */
    public bool $fromGroupsPage = false;

    public ?int $attributeId = null;

    public string $name = '';
    public ?string $attribute_group_id = null;
    public bool $is_required = false;

    public function loadAttribute($attributeId = null)
    {
        if ($attributeId === null) {
            return;
        }

        if (is_array($attributeId) && isset($attributeId['id'])) {
            $attributeId = $attributeId['id'];
        } elseif (is_object($attributeId) && isset($attributeId->id)) {
            $attributeId = $attributeId->id;
        }

        $this->attributeId = (int)$attributeId;

        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $attribute = Attribute::where('id', $this->attributeId)
            ->where('store_id', $store->id)
            ->with(['groups' => function($q) {
                $q->withPivot('is_required', 'position');
            }])
            ->first();

        if ($attribute) {
            $this->name = $attribute->name;

            $group = $attribute->groups->first();
            $this->attribute_group_id = $group ? (string)$group->id : null;
            $this->is_required = $group && isset($group->pivot->is_required)
                ? (bool)$group->pivot->is_required
                : $attribute->is_required;

            $this->dispatch('open-modal', 'edit-attribute');
        }
    }

    protected function rules(): array
    {
        $groupIds = $this->getGroupsProperty()->pluck('id')->toArray();

        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'attribute_group_id' => ['required', 'exists:attribute_groups,id', Rule::in($groupIds)],
            'is_required' => ['boolean'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getGroupsProperty()
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }
        return AttributeGroup::where('store_id', $store->id)->orderBy('position')->orderBy('name')->get();
    }

    public function update(AttributeService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar atributos en esta tienda.');
        }

        if (!$this->attributeId) {
            return;
        }

        try {
            $service->updateAttribute($store, $this->attributeId, [
                'name' => $this->name,
                'is_required' => $this->is_required,
                'attribute_group_id' => $this->attribute_group_id,
            ]);

            $this->reset(['name', 'attribute_group_id', 'is_required', 'attributeId']);
            $this->resetValidation();

            if ($this->fromGroupsPage) {
                return redirect()->route('stores.attribute-groups', $store)
                    ->with('success', 'Atributo actualizado correctamente.');
            }

            return redirect()->back()
                ->with('success', 'Atributo actualizado correctamente.');
        } catch (\Exception $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.edit-attribute-modal');
    }
}
