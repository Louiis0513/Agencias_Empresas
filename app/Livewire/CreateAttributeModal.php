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
    public string $code = '';
    public ?string $attribute_group_id = null;
    public string $type = 'text';
    public bool $is_required = false;
    public array $options = [''];

    protected function rules(): array
    {
        $groupIds = $this->getGroupsProperty()->pluck('id')->toArray();

        $rules = [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'attribute_group_id' => ['required', 'exists:attribute_groups,id', Rule::in($groupIds)],
            'type' => ['required', 'in:text,number,select,boolean'],
            'is_required' => ['boolean'],
        ];

        if ($this->type === 'select') {
            $rules['options'] = ['required', 'array', 'min:1'];
            $rules['options.*'] = ['required', 'string', 'min:1'];
        }

        return $rules;
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

    public function addOption()
    {
        $this->options[] = '';
    }

    public function removeOption($index)
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function updatedType()
    {
        if ($this->type === 'select' && empty($this->options)) {
            $this->options = [''];
        }
        if ($this->type !== 'select') {
            $this->options = [];
        }
    }

    public function save(AttributeService $service)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear atributos en esta tienda.');
        }

        $filteredOptions = array_filter($this->options, fn ($opt) => ! empty(trim((string) $opt)));

        $service->createAttribute($store, [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'attribute_group_id' => $this->attribute_group_id,
            'type' => $this->type,
            'is_required' => $this->is_required,
            'options' => array_values($filteredOptions),
        ]);

        $this->reset(['name', 'code', 'attribute_group_id', 'type', 'is_required', 'options']);
        $this->resetValidation();
        $this->options = [''];

        if ($this->fromGroupsPage) {
            return redirect()->route('stores.attribute-groups', $store);
        }

        return redirect()->back();
    }

    public function render()
    {
        return view('livewire.create-attribute-modal');
    }
}
