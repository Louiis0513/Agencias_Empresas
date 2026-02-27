<?php

namespace App\Livewire;

use App\Models\AttributeGroup;
use App\Models\Store;
use App\Services\AttributeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateAttributeModal extends Component
{
    public int $storeId;

    /** Si se usa en la página de grupos (redirect allí tras crear). */
    public bool $fromGroupsPage = false;

    public string $name = '';
    public ?string $attribute_group_id = null;
    public bool $is_required = false;

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

    public function setGroupId($groupId): void
    {
        $this->attribute_group_id = $groupId ? (string) $groupId : null;
    }

    public function getGroupName(): ?string
    {
        if (! $this->attribute_group_id) {
            return null;
        }
        $group = AttributeGroup::find($this->attribute_group_id);

        return $group?->name;
    }

    public function save(AttributeService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear atributos en esta tienda.');
        }

        try {
            $service->createAttribute($store, [
                'name' => $this->name,
                'attribute_group_id' => $this->attribute_group_id,
                'is_required' => $this->is_required,
            ]);

            $this->reset(['name', 'attribute_group_id', 'is_required']);
            $this->resetValidation();

            if ($this->fromGroupsPage) {
                return redirect()->route('stores.attribute-groups', $store);
            }

            return redirect()->back();
        } catch (\Exception $e) {
            $this->addError('general', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.create-attribute-modal');
    }
}
