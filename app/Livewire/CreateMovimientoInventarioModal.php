<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Store;
use App\Services\InventarioService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateMovimientoInventarioModal extends Component
{
    public int $storeId;

    public int $product_id = 0;
    public string $type = 'ENTRADA';
    public string $quantity = '';
    public ?string $description = null;
    public ?string $unit_cost = null;

    protected function rules(): array
    {
        return [
            'product_id'  => ['required', 'exists:products,id'],
            'type'        => ['required', 'in:ENTRADA,SALIDA'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
            'unit_cost'   => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'product_id.required' => 'Debes seleccionar un producto.',
            'quantity.required'   => 'La cantidad es obligatoria.',
            'quantity.min'        => 'La cantidad debe ser al menos 1.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getProductosProperty()
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }
        return app(InventarioService::class)->productosConInventario($store);
    }

    public function getProductoSeleccionadoProperty(): ?Product
    {
        if (! $this->product_id) {
            return null;
        }
        return $this->productos->firstWhere('id', $this->product_id);
    }

    public function resetForm(): void
    {
        $this->product_id  = 0;
        $this->type        = 'ENTRADA';
        $this->quantity    = '';
        $this->description = null;
        $this->unit_cost   = null;
        $this->resetValidation();
    }

    public function save(InventarioService $inventarioService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para registrar movimientos en esta tienda.');
        }

        try {
            $inventarioService->registrarMovimiento($store, Auth::id(), [
                'product_id'  => (int) $this->product_id,
                'type'        => $this->type,
                'quantity'    => (int) $this->quantity,
                'description' => $this->description ?: null,
                'unit_cost'   => $this->unit_cost !== '' && $this->unit_cost !== null
                    ? (float) $this->unit_cost
                    : null,
            ]);

            $this->resetForm();

            return redirect()->route('stores.inventario', $store)
                ->with('success', 'Movimiento de inventario registrado correctamente.');
        } catch (\Exception $e) {
            $this->addError('quantity', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.create-movimiento-inventario-modal');
    }
}
