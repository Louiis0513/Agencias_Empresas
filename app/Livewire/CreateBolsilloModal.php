<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\CajaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateBolsilloModal extends Component
{
    public int $storeId;

    public string $name = '';
    public ?string $detalles = null;
    public string $saldo = '0';
    public bool $is_bank_account = false;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'detalles' => ['nullable', 'string', 'max:1000'],
            'saldo' => ['required', 'numeric', 'min:0'],
            'is_bank_account' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre del bolsillo es obligatorio.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'detalles', 'saldo', 'is_bank_account', 'is_active']);
        $this->resetValidation();
    }

    public function save(CajaService $cajaService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear bolsillos en esta tienda.');
        }

        try {
            $cajaService->crearBolsillo($store, [
                'name' => $this->name,
                'detalles' => $this->detalles ?: null,
                'saldo' => (float) $this->saldo,
                'is_bank_account' => $this->is_bank_account,
                'is_active' => $this->is_active,
            ]);

            $this->reset(['name', 'detalles', 'saldo', 'is_bank_account', 'is_active']);
            $this->resetValidation();

            return redirect()->route('stores.cajas', $store)
                ->with('success', 'Bolsillo creado correctamente.');
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.create-bolsillo-modal');
    }
}
