<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\ActivoService;
use App\Services\InventarioService;
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

    public function mount(int $storeId, string $itemType = 'INVENTARIO', string $rowId = ''): void
    {
        $this->storeId = $storeId;
        $this->itemType = $itemType;
        $this->rowId = $rowId;
    }

    #[On('open-select-item-for-row')]
    public function openForRow(string $rowId = '', string $itemType = 'INVENTARIO'): void
    {
        $this->rowId = $rowId;
        $this->itemType = $itemType;
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
            return app(InventarioService::class)->buscarProductosInventario(
                $store,
                $this->search,
                25
            )->map(fn ($p) => [
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
            'control_type' => $a->control_type ?? 'LOTE',
        ]);
    }

    public function selectItem(int $id, string $name, string $type, ?string $controlType = null, ?string $productType = null): void
    {
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
