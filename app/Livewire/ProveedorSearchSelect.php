<?php

namespace App\Livewire;

use App\Models\Proveedor;
use Livewire\Component;

class ProveedorSearchSelect extends Component
{
    public int $storeId;

    public ?int $selectedProveedorId = null;

    public string $emitEventName = 'proveedor-selected';

    public string $emitClearEventName = 'proveedor-cleared';

    public bool $mostrarModal = false;

    public string $filtroBusqueda = '';

    /** @var array{id: int, nombre: string, nit: ?string}|null */
    public ?array $proveedorSeleccionado = null;

    public function mount(
        int $storeId,
        ?int $selectedProveedorId = null,
        string $emitEventName = 'proveedor-selected',
        string $emitClearEventName = 'proveedor-cleared',
    ): void {
        $this->storeId = $storeId;
        $this->emitEventName = $emitEventName;
        $this->emitClearEventName = $emitClearEventName;
        $this->selectedProveedorId = $selectedProveedorId;

        if ($selectedProveedorId) {
            $this->loadSelectedProveedor($selectedProveedorId);
        }
    }

    public function updatedSelectedProveedorId($value): void
    {
        if ($value) {
            $this->loadSelectedProveedor((int) $value);
        } else {
            $this->proveedorSeleccionado = null;
        }
    }

    protected function loadSelectedProveedor(int $proveedorId): void
    {
        $p = Proveedor::where('id', $proveedorId)
            ->where('store_id', $this->storeId)
            ->first();

        if ($p) {
            $this->proveedorSeleccionado = [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'nit' => $p->nit,
            ];
        } else {
            $this->proveedorSeleccionado = null;
        }
    }

    public function abrirModal(): void
    {
        $this->mostrarModal = true;
        $this->filtroBusqueda = '';
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->filtroBusqueda = '';
    }

    /**
     * Resultados acotados en servidor (evita listas enormes en el cliente).
     */
    public function getResultadosProperty()
    {
        $termino = trim($this->filtroBusqueda);
        if (strlen($termino) < 1) {
            return collect();
        }

        return Proveedor::deTienda($this->storeId)
            ->activos()
            ->buscar($termino)
            ->orderBy('nombre')
            ->limit(50)
            ->get(['id', 'nombre', 'nit']);
    }

    public function seleccionarProveedor(int $proveedorId): void
    {
        $p = Proveedor::where('id', $proveedorId)
            ->where('store_id', $this->storeId)
            ->first();

        if ($p) {
            $this->proveedorSeleccionado = [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'nit' => $p->nit,
            ];
            $this->selectedProveedorId = $p->id;
            $this->cerrarModal();

            $this->dispatch($this->emitEventName, proveedor_id: $p->id, nombre: $p->nombre);
        }
    }

    public function limpiarProveedor(): void
    {
        $this->proveedorSeleccionado = null;
        $this->selectedProveedorId = null;
        $this->cerrarModal();
        $this->dispatch($this->emitClearEventName);
    }

    public function render()
    {
        return view('livewire.proveedor-search-select');
    }
}
