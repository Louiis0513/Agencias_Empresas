<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\AccountPayableService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class SelectAccountPayableModal extends Component
{
    use WithPagination;

    public int $storeId;

    /** Índice de la fila destino en el formulario de comprobante (flujo antiguo) */
    public string $destinoRowIndex = '';

    /** true = flujo comprobante: al seleccionar factura se auto-asigna proveedor */
    public bool $forComprobante = false;

    public ?int $proveedorId = null;

    /** IDs de cuentas ya seleccionadas (para excluir del modal) */
    public array $excludeIds = [];

    public string $search = '';

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
        $this->destinoRowIndex = (string) ($destinoRowIndex ?? '');
        $this->forComprobante = false;
        $this->proveedorId = null;
        $this->search = '';
        $this->fechaVencimientoDesde = null;
        $this->fechaVencimientoHasta = null;
        $this->status = 'pendientes';
        $this->resetPage();
        $this->dispatch('open-modal', 'select-account-payable');
    }

    #[On('open-select-account-payable-for-comprobante')]
    public function openForComprobante($proveedor_id = null, $selected_ids = []): void
    {
        if (is_array($proveedor_id) || (is_object($proveedor_id) && ! is_numeric($proveedor_id))) {
            $payload = (array) $proveedor_id;
            $proveedor_id = $payload['proveedor_id'] ?? $payload['proveedorId'] ?? null;
            $selected_ids = $payload['selected_ids'] ?? $payload['selectedIds'] ?? [];
        }
        $this->destinoRowIndex = '';
        $this->forComprobante = true;
        $this->proveedorId = $proveedor_id ? (int) $proveedor_id : null;
        $raw = is_array($selected_ids) ? $selected_ids : (array) $selected_ids;
        $this->excludeIds = array_values(array_filter(array_map('intval', $raw)));
        $this->search = '';
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
            return new LengthAwarePaginator([], 0, 10, 1);
        }

        $filtros = [
            'per_page' => 10,
            'page' => $this->getPage(),
            'status' => $this->status,
            'exclude_ids' => $this->excludeIds,
            'search' => $this->search,
            'fecha_vencimiento_desde' => $this->fechaVencimientoDesde,
            'fecha_vencimiento_hasta' => $this->fechaVencimientoHasta,
        ];
        if (count($this->excludeIds) > 0) {
            $filtros['proveedor_id'] = $this->proveedorId;
        }

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

    public function selectAccountPayable(int $id, int $purchaseId, ?int $proveedorId, string $proveedorNombre, float $total, float $balance, ?string $dueDate, string $status): void
    {
        $payload = [
            'destinoRowIndex' => $this->destinoRowIndex,
            'id' => $id,
            'purchaseId' => $purchaseId,
            'proveedorId' => $proveedorId,
            'proveedorNombre' => $proveedorNombre,
            'total' => $total,
            'balance' => $balance,
            'dueDate' => $dueDate,
            'status' => $status,
        ];

        if ($this->forComprobante) {
            $this->dispatch('account-payable-selected-for-comprobante', $payload);
        } else {
            $this->dispatch('account-payable-selected', $payload);
        }
        $this->dispatch('close-modal', 'select-account-payable');
    }

    public function render()
    {
        return view('livewire.select-account-payable-modal');
    }
}
