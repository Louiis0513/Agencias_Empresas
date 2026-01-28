<?php

namespace App\Livewire;

use App\Models\Bolsillo;
use App\Models\Store;
use App\Services\CajaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateMovimientoModal extends Component
{
    public int $storeId;
    /** Bolsillo preseleccionado (desde detalle de bolsillo). Si es 0, se elige en el modal. */
    public int $bolsilloId = 0;

    public int $bolsillo_id = 0;
    public string $type = 'INCOME';
    public string $amount = '';
    public ?string $description = null;

    public function mount(int $bolsilloId = 0): void
    {
        $this->bolsilloId = $bolsilloId;
        $this->bolsillo_id = $bolsilloId ?: 0;
    }

    protected function rules(): array
    {
        return [
            'bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'type' => ['required', 'in:INCOME,EXPENSE'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'bolsillo_id.required' => 'Debes seleccionar un bolsillo.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor que 0.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getBolsillosActivosProperty()
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }
        return Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
    }

    public function resetForm(): void
    {
        $this->amount = '';
        $this->description = null;
        $this->type = 'INCOME';
        $this->bolsillo_id = $this->bolsilloId ?: ($this->bolsillosActivos->first()?->id ?? 0);
        $this->resetValidation();
    }

    public function save(CajaService $cajaService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para registrar movimientos en esta tienda.');
        }

        $bolsillo = Bolsillo::deTienda($store->id)->where('id', $this->bolsillo_id)->firstOrFail();

        try {
            $cajaService->registrarMovimiento($store, Auth::id(), [
                'bolsillo_id' => $bolsillo->id,
                'type' => $this->type,
                'amount' => (float) $this->amount,
                'description' => $this->description ?: null,
            ]);

            $this->resetForm();

            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])
                ->with('success', 'Movimiento registrado correctamente.');
        } catch (\Exception $e) {
            $this->addError('amount', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.create-movimiento-modal');
    }
}
