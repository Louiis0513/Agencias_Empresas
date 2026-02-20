<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\ActivoService;
use App\Services\InventarioService;
use App\Services\VentaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SelectItemModal extends Component
{
    public int $storeId;

    public string $itemType = 'INVENTARIO';

    /** Índice de la fila en el formulario de compra para identificar qué fila actualizar */
    public string $rowId = '';

    public string $search = '';

    /** Solo contexto venta: product_ids en el carrito como simple, para invalidar en la vista (serializado no: se puede añadir otra unidad con otro serial) */
    public array $productIdsInCartSimple = [];

    public function mount(int $storeId, string $itemType = 'INVENTARIO', string $rowId = ''): void
    {
        $this->storeId = $storeId;
        $this->itemType = $itemType;
        $this->rowId = $rowId;
    }

    #[On('open-select-item-for-row')]
    public function openForRow(string $rowId = '', string $itemType = 'INVENTARIO', array $productIdsInCartSimple = []): void
    {
        $this->rowId = $rowId;
        $this->itemType = $itemType;
        $this->productIdsInCartSimple = $productIdsInCartSimple ?? [];
        $this->search = '';
        $this->dispatch('open-modal', 'select-item-compra');
    }

    public function getResultsProperty()
    {
        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            return collect();
        }

        if ($this->itemType === 'INVENTARIO') {
            // Vista de ventas (carrito) o facturas: búsqueda con tipo (simple/batch/serialized) para selector coherente
            $productos = in_array($this->rowId, ['venta', 'factura'], true)
                ? app(VentaService::class)->buscarProductos($store, $this->search, 25)
                : app(InventarioService::class)->buscarProductosInventario($store, $this->search, 25);
            return $productos->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->sku ?? null,
                'type' => 'INVENTARIO',
                'product_type' => $p->type ?? 'simple',
            ]);
        }

        return app(ActivoService::class)->buscarActivosParaCompra(
            $store,
            $this->search,
            25
        )->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'code' => $a->code ?? null,
            'type' => 'ACTIVO_FIJO',
            'control_type' => null,
        ]);
    }

    public function selectItem(int $id, string $name, string $type, ?string $controlType = null, ?string $productType = null): void
    {
        $productType = $productType ?? 'simple';
        if ($type === 'INVENTARIO' && $this->rowId === 'venta' && $productType === 'simple' && in_array($id, $this->productIdsInCartSimple, true)) {
            $this->addError('item', 'Este producto ya está en el carrito.');

            return;
        }

        $payload = [
            'rowId' => $this->rowId,
            'id' => $id,
            'name' => $name,
            'type' => $type,
        ];
        if ($type === 'ACTIVO_FIJO' && $controlType !== null) {
            $payload['controlType'] = $controlType;
        }
        if ($type === 'INVENTARIO' && $productType !== null) {
            $payload['productType'] = $productType;
        }
        $this->dispatch('item-selected', ...$payload);
        $this->dispatch('close-modal', 'select-item-compra');
    }

    public function openCreateProduct(): void
    {
        $this->dispatch('open-create-product-from-compra', rowId: $this->rowId);
        $this->dispatch('open-modal', 'create-product-from-compra');
    }

    public function openCreateActivo(): void
    {
        $this->dispatch('open-create-activo-from-compra', rowId: $this->rowId);
        $this->dispatch('open-modal', 'create-activo-from-compra');
    }

    public function render()
    {
        return view('livewire.select-item-modal', [
            'results' => $this->results,
        ]);
    }
}
