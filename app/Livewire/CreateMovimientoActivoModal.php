<?php

namespace App\Livewire;

use App\Models\Activo;
use App\Models\Store;
use App\Services\ActivoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateMovimientoActivoModal extends Component
{
    public int $storeId;

    public int $activo_id = 0;
    public string $type = 'ENTRADA';
    public string $quantity = '';
    public ?string $description = null;
    public ?string $unit_cost = null;

    protected function rules(): array
    {
        return [
            'activo_id'  => ['required', 'exists:activos,id'],
            'type'       => ['required', 'in:ENTRADA,SALIDA'],
            'quantity'   => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
            'unit_cost'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'activo_id.required' => 'Debes seleccionar un activo.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min'       => 'La cantidad debe ser al menos 1.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getActivosProperty()
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }
        return app(ActivoService::class)->activosParaMovimientos($store);
    }

    public function getActivoSeleccionadoProperty(): ?Activo
    {
        if (! $this->activo_id) {
            return null;
        }
        return $this->activos->firstWhere('id', $this->activo_id);
    }

    public function resetForm(): void
    {
        $this->activo_id  = 0;
        $this->type       = 'ENTRADA';
        $this->quantity   = '';
        $this->description = null;
        $this->unit_cost  = null;
        $this->resetValidation();
    }

    public function save(ActivoService $activoService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para registrar movimientos en esta tienda.');
        }

        try {
            $activoService->registrarMovimiento($store, Auth::id(), [
                'activo_id'   => (int) $this->activo_id,
                'type'        => $this->type,
                'quantity'    => (int) $this->quantity,
                'description' => $this->description ?: null,
                'unit_cost'   => $this->unit_cost !== '' && $this->unit_cost !== null
                    ? (float) $this->unit_cost
                    : null,
            ]);

            $this->resetForm();

            return redirect()->route('stores.activos.movimientos', $store)
                ->with('success', 'Movimiento de activo registrado correctamente.');
        } catch (\Exception $e) {
            $this->addError('quantity', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.create-movimiento-activo-modal');
    }
}
