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

    public string $control_type = 'LOTE';
    public string $name = '';
    public string $code = '';
    public string $serial_number = '';
    public string $model = '';
    public string $brand = '';
    public string $description = '';
    public string $quantity = '0';
    public string $unit_cost = '0';
    public string $location = '';
    public ?string $purchase_date = null;
    public ?string $warranty_expiry = null;
    public ?string $assigned_to_user_id = null;
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
        $this->control_type = 'LOTE';
    }

    protected function rules(): array
    {
        $rules = [
            'control_type' => ['required', 'in:LOTE,SERIALIZADO'],
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_expiry' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['boolean'],
        ];
        if ($this->control_type === 'SERIALIZADO') {
            $rules['quantity'] = ['required', 'integer', 'min:0', 'max:1'];
            if ($this->fromPurchase) {
                $rules['quantity'] = ['required', 'integer', 'in:0'];
                $rules['serial_number'] = ['required', 'string', 'max:100'];
            } elseif ((int) $this->quantity === 1) {
                $rules['serial_number'] = ['required', 'string', 'max:100'];
            }
        }
        return $rules;
    }

    public function save(ActivoService $service)
    {
        if ($this->assigned_to_user_id === '') {
            $this->assigned_to_user_id = null;
        }
        $this->validate();

        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear activos en esta tienda.');
        }

        $quantity = $this->fromPurchase && $this->control_type === 'SERIALIZADO' ? 0 : (int) $this->quantity;
        $unitCost = $this->fromPurchase && $this->control_type === 'SERIALIZADO' ? 0 : (float) $this->unit_cost;

        $activo = $service->crearActivo($store, [
            'control_type' => $this->control_type,
            'name' => $this->name,
            'code' => $this->code ?: null,
            'serial_number' => $this->control_type === 'SERIALIZADO' && ((int) $this->quantity === 1 || $this->fromPurchase) ? trim($this->serial_number) : null,
            'model' => $this->model ?: null,
            'brand' => $this->brand ?: null,
            'description' => $this->description ?: null,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'location' => $this->location ?: null,
            'purchase_date' => null,
            'warranty_expiry' => $this->warranty_expiry ?: null,
            'assigned_to_user_id' => $this->assigned_to_user_id ?: null,
            'is_active' => $this->is_active,
        ], Auth::id());

        $compraRowId = $this->compraRowId;

        $this->reset([
            'control_type', 'name', 'code', 'serial_number', 'model', 'brand', 'description', 'quantity', 'unit_cost', 'location', 'warranty_expiry', 'is_active', 'compraRowId',
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
        $store = \App\Models\Store::find($this->storeId);
        $workers = $store?->workers()->select('users.id', 'users.name')->orderBy('users.name')->get() ?? collect();

        return view('livewire.create-activo-modal', compact('workers'));
    }
}
