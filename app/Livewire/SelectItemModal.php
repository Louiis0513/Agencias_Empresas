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

    /** Contexto venta/factura: product_variant_id ya en documento (líneas batch), para invalidar filas del buscador. */
    public array $productVariantIdsInDocument = [];

    public function mount(int $storeId, string $itemType = 'INVENTARIO', string $rowId = ''): void
    {
        $this->storeId = $storeId;
        $this->itemType = $itemType;
        $this->rowId = $rowId;
    }

    #[On('open-select-item-for-row')]
    public function openForRow(string $rowId = '', string $itemType = 'INVENTARIO', array $productIdsInCartSimple = [], array $productVariantIdsInDocument = []): void
    {
        $this->rowId = $rowId;
        $this->itemType = $itemType;
        $this->productIdsInCartSimple = $productIdsInCartSimple ?? [];
        $this->productVariantIdsInDocument = array_values(array_unique(array_map('intval', $productVariantIdsInDocument ?? [])));
        $this->search = '';
        $this->dispatch('open-modal', 'select-item-compra');
    }

    public function getResultsProperty()
    {
        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            return collect();
        }

        $term = trim($this->search);
        if (strlen($term) < 2) {
            return collect();
        }

        if ($this->itemType === 'INVENTARIO') {
            // Vista de ventas (carrito), facturas, inventario y compras: búsqueda con tipo (simple/batch/serialized)
            // La capa de servicio ya devuelve ítems lógicos (producto simple, variante de lote, ítem serializado).
            $items = in_array($this->rowId, ['venta', 'factura', 'inventario-filtro', 'movimiento-inventario'], true)
                ? app(VentaService::class)->buscarProductos($store, $term, 25)
                : app(InventarioService::class)->buscarProductosParaCompra($store, $term, 25);

            return $items->map(function ($row) {
                // $row es un array normal con la forma documentada en InventarioService::buscarProductosInventario.
                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'display_name' => $row['display_name'] ?? $row['name'],
                    'code' => $row['code'] ?? null,
                    'type' => 'INVENTARIO',
                    'product_type' => $row['product_type'] ?? 'simple',
                    'variant_id' => $row['variant_id'] ?? null,
                    'item_id' => $row['item_id'] ?? null,
                ];
            });
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

    public function selectItem(
        int $id,
        string $name,
        string $type,
        ?string $controlType = null,
        ?string $productType = null,
        ?int $productVariantId = null,
        ?int $productItemId = null
    ): void
    {
        $productType = $productType ?? 'simple';
        if ($type === 'INVENTARIO'
            && in_array($this->rowId, ['venta', 'factura'], true)
            && $productType === 'simple'
            && in_array($id, $this->productIdsInCartSimple, true)) {
            $this->addError('item', $this->rowId === 'factura'
                ? 'Este producto ya está en la factura.'
                : 'Este producto ya está en el carrito.');

            return;
        }

        if ($type === 'INVENTARIO'
            && in_array($this->rowId, ['venta', 'factura'], true)
            && $productType === 'batch'
            && $productVariantId !== null
            && in_array((int) $productVariantId, $this->productVariantIdsInDocument, true)) {
            $this->addError('item', $this->rowId === 'factura'
                ? 'Esta variante ya está en la factura.'
                : 'Esta variante ya está en el carrito.');

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
        if ($type === 'INVENTARIO' && $productVariantId !== null) {
            $payload['productVariantId'] = $productVariantId;
        }
        if ($type === 'INVENTARIO' && $productItemId !== null) {
            $payload['productItemId'] = $productItemId;
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
