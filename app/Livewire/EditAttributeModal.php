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
    public string $code = '';
    public ?string $attribute_group_id = null;
    public string $type = 'text';
    public bool $is_required = false;
    public array $options = [''];

    public function loadAttribute($attributeId = null)
    {
        if ($attributeId === null) {
            return;
        }

        // Extraer el ID si viene como objeto
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
            ->with(['options', 'groups' => function($q) {
                $q->withPivot('is_required', 'position');
            }])
            ->first();

        if ($attribute) {
            $this->name = $attribute->name;
            $this->code = $attribute->code ?? '';
            $this->type = $attribute->type;

            // Obtener el grupo al que pertenece y el is_required del pivot
            $group = $attribute->groups->first();
            $this->attribute_group_id = $group ? (string)$group->id : null;
            
            // Obtener is_required del pivot (grupo) o del atributo como fallback
            $this->is_required = $group && isset($group->pivot->is_required) 
                ? (bool)$group->pivot->is_required 
                : $attribute->is_required;

            // Cargar opciones si es tipo select
            if ($attribute->type === 'select') {
                $this->options = $attribute->options->pluck('value')->toArray();
                if (empty($this->options)) {
                    $this->options = [''];
                }
            } else {
                $this->options = [''];
            }

            // Abrir el modal
            $this->dispatch('open-modal', 'edit-attribute');
        }
    }

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
        $this->normalizeOptions();
        $this->options[] = '';
    }

    public function removeOption($index)
    {
        $this->normalizeOptions();
        unset($this->options[$index]);
        $this->options = array_values($this->options);
        // Asegurar que siempre haya al menos una opción
        if (empty($this->options) && $this->type === 'select') {
            $this->options = [''];
        }
    }

    public function updatedType()
    {
        if ($this->type === 'select') {
            // Si no hay opciones o están vacías, inicializar con una opción vacía
            if (empty($this->options) || (count($this->options) === 1 && empty($this->options[0]))) {
                $this->options = [''];
            }
        } else {
            // Si cambia a otro tipo que no sea select, limpiar las opciones
            $this->options = [];
        }
    }

    protected function normalizeOptions()
    {
        if (!is_array($this->options)) {
            $this->options = [''];
            return;
        }

        $this->options = array_map(function ($option) {
            if (is_array($option)) {
                return '';
            }
            return (string)($option ?? '');
        }, $this->options);

        // Si el array está vacío después de normalizar, asegurar que tenga al menos un elemento vacío
        if (empty($this->options) && $this->type === 'select') {
            $this->options = [''];
        }
    }

    public function update(AttributeService $service)
    {
        // Normalizar las opciones para asegurar que sean strings
        if (is_array($this->options)) {
            $this->options = array_map(function ($option) {
                if (is_array($option)) {
                    return '';
                }
                return (string)($option ?? '');
            }, $this->options);
        }

        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar atributos en esta tienda.');
        }

        if (!$this->attributeId) {
            return;
        }

        $filteredOptions = array_filter($this->options, fn ($opt) => ! empty(trim((string) $opt)));

        try {
            // Actualizar el atributo (el servicio maneja también el cambio de grupo)
            $service->updateAttribute($store, $this->attributeId, [
                'name' => $this->name,
                'code' => $this->code ?: null,
                'type' => $this->type,
                'is_required' => $this->is_required,
                'attribute_group_id' => $this->attribute_group_id,
                'options' => array_values($filteredOptions),
            ]);

            $this->reset(['name', 'code', 'attribute_group_id', 'type', 'is_required', 'options', 'attributeId']);
            $this->resetValidation();
            $this->options = [''];

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
        // Asegurar que si el tipo es select, haya al menos una opción vacía
        if ($this->type === 'select' && empty($this->options)) {
            $this->options = [''];
        }

        // Normalizar opciones antes de renderizar
        $this->normalizeOptions();
        return view('livewire.edit-attribute-modal');
    }
}
