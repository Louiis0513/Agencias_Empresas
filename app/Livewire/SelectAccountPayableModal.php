<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\AccountPayableService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class SelectAccountPayableModal extends Component
{
    use WithPagination;

    public int $storeId;

    /** Índice de la fila destino en el formulario de comprobante */
    public string $destinoRowIndex = '';

    public ?int $proveedorId = null;

    public ?string $fechaVencimientoDesde = null;

    public ?string $fechaVencimientoHasta = null;

    public string $status = 'pendientes';

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    #[On('open-select-account-payable')]
    public function openForRow(?string $destinoRowIndex = null): void
    {
        // Livewire desde JS pasa { destinoRowIndex: '0' } como named args
        $this->destinoRowIndex = (string) ($destinoRowIndex ?? '');
        $this->proveedorId = null;
        $this->fechaVencimientoDesde = null;
        $this->fechaVencimientoHasta = null;
        $this->status = 'pendientes';
        $this->resetPage();
        $this->dispatch('open-modal', 'select-account-payable');
    }

    public function getCuentasPorPagarProperty()
    {
        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            return collect()->paginate(10);
        }

        $filtros = [
            'per_page' => 10,
            'page' => $this->getPage(),
            'status' => $this->status,
            'proveedor_id' => $this->proveedorId,
            'fecha_vencimiento_desde' => $this->fechaVencimientoDesde,
            'fecha_vencimiento_hasta' => $this->fechaVencimientoHasta,
        ];

        return app(AccountPayableService::class)->listarCuentasPorPagar($store, $filtros);
    }

    public function getProveedoresProperty()
    {
        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            return collect();
        }

        return \App\Models\Proveedor::deTienda($store->id)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function selectAccountPayable(int $id, int $purchaseId, string $proveedorNombre, float $total, float $balance, ?string $dueDate, string $status): void
    {
        $payload = [
            'destinoRowIndex' => $this->destinoRowIndex,
            'id' => $id,
            'purchaseId' => $purchaseId,
            'proveedorNombre' => $proveedorNombre,
            'total' => $total,
            'balance' => $balance,
            'dueDate' => $dueDate,
            'status' => $status,
        ];
        $this->dispatch('account-payable-selected', $payload);
        $this->dispatch('close-modal', 'select-account-payable');
    }

    public function render()
    {
        return view('livewire.select-account-payable-modal');
    }
}
