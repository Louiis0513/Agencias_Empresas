<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\ActivoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateActivoModal extends Component
{
    public int $storeId;

    public bool $fromPurchase = false;

    /** RowId de la fila en compra cuando se abre desde el modal de selección */
    public string $compraRowId = '';

    public string $name = '';
    public string $code = '';
    public string $description = '';
    public string $quantity = '0';
    public string $unit_cost = '0';
    public string $location = '';
    public bool $is_active = true;

    #[On('open-create-activo-from-compra')]
    public function setCompraRowId(string $rowId = ''): void
    {
        if ($this->fromPurchase) {
            $this->compraRowId = $rowId;
        }
    }

    public function mount(int $storeId, bool $fromPurchase = false): void
    {
        $this->storeId = $storeId;
        $this->fromPurchase = $fromPurchase;
        if ($fromPurchase) {
            $this->quantity = '0';
            $this->unit_cost = '0';
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function save(ActivoService $service)
    {
        $this->validate();

        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear activos en esta tienda.');
        }

        $activo = $service->crearActivo($store, [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'description' => $this->description ?: null,
            'quantity' => (int) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'location' => $this->location ?: null,
            'is_active' => $this->is_active,
        ]);

        $compraRowId = $this->compraRowId;

        $this->reset([
            'name', 'code', 'description', 'quantity', 'unit_cost', 'location', 'is_active', 'compraRowId',
        ]);
        $this->resetValidation();

        if ($this->fromPurchase) {
            $this->dispatch('item-selected', rowId: $compraRowId, id: $activo->id, name: $activo->name, type: 'ACTIVO_FIJO');
            $this->dispatch('close-modal', 'create-activo-from-compra');
            $this->dispatch('close-modal', 'select-item-compra');

            return;
        }

        return redirect()->route('stores.activos', $store)->with('success', 'Activo creado correctamente.');
    }

    public function render()
    {
        return view('livewire.create-activo-modal');
    }
}
