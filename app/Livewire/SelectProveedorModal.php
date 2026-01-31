<?php

namespace App\Livewire;

use App\Models\Proveedor;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SelectProveedorModal extends Component
{
    public int $storeId;

    public string $search = '';

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    #[On('open-select-proveedor')]
    public function open(): void
    {
        $this->search = '';
        $this->dispatch('open-modal', 'select-proveedor');
    }

    public function getResultsProperty()
    {
        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            return collect();
        }

        $query = Proveedor::deTienda($store->id)
            ->activos()
            ->orderBy('nombre');

        if (strlen(trim($this->search)) >= 1) {
            $query->buscar(trim($this->search));
        }

        return $query->limit(50)->get(['id', 'nombre']);
    }

    public function selectProveedor(int $id, string $nombre): void
    {
        $this->dispatch('proveedor-selected', ['id' => $id, 'nombre' => $nombre]);
        $this->dispatch('close-modal', 'select-proveedor');
    }

    public function render()
    {
        return view('livewire.select-proveedor-modal');
    }
}
