<?php

namespace App\Livewire;

use App\Models\Bolsillo;
use App\Models\Store;
use App\Services\CajaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditBolsilloModal extends Component
{
    public int $storeId;
    public ?int $bolsilloId = null;

    public string $name = '';
    public ?string $detalles = null;
    public bool $is_bank_account = false;
    public bool $is_active = true;

    public function mount(?int $bolsilloId = null): void
    {
        $this->bolsilloId = $bolsilloId;
        if ($bolsilloId) {
            $this->loadBolsillo($bolsilloId);
        }
    }

    public function loadBolsillo(int $id): void
    {
        $bolsillo = Bolsillo::where('id', $id)->where('store_id', $this->storeId)->firstOrFail();
        $this->bolsilloId = $bolsillo->id;
        $this->name = $bolsillo->name;
        $this->detalles = $bolsillo->detalles;
        $this->is_bank_account = $bolsillo->is_bank_account;
        $this->is_active = $bolsillo->is_active;
        $this->resetValidation();
        $this->dispatch('open-modal', 'edit-bolsillo');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'detalles' => ['nullable', 'string', 'max:1000'],
            'is_bank_account' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function update(CajaService $cajaService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar bolsillos.');
        }
        if (! $this->bolsilloId) {
            $this->addError('name', 'Bolsillo no especificado.');
            return;
        }

        $bolsillo = Bolsillo::where('id', $this->bolsilloId)->where('store_id', $store->id)->firstOrFail();

        try {
            $cajaService->actualizarBolsillo($bolsillo, [
                'name' => $this->name,
                'detalles' => $this->detalles,
                'is_bank_account' => $this->is_bank_account,
                'is_active' => $this->is_active,
            ]);

            $this->resetValidation();

            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])
                ->with('success', 'Bolsillo actualizado correctamente.');
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.edit-bolsillo-modal');
    }
}
