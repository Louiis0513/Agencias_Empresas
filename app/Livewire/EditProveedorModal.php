<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Proveedor;
use App\Models\Store;
use App\Services\ProductService;
use App\Services\ProveedorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditProveedorModal extends Component
{
    public int $storeId;
    public ?int $proveedorId = null;

    public string $nombre = '';
    public ?string $numero_celular = null;
    public ?string $telefono = null;
    public ?string $email = null;
    public ?string $nit = null;
    public ?string $direccion = null;
    public bool $estado = true;

    /** @var array<int> IDs de productos vinculados al proveedor */
    public array $producto_ids = [];

    public string $busquedaProducto = '';
    public array $productosEncontrados = [];

    public function loadProveedor($proveedorId = null)
    {
        if ($proveedorId === null) {
            return;
        }

        if (is_array($proveedorId) && isset($proveedorId['id'])) {
            $proveedorId = $proveedorId['id'];
        } elseif (is_object($proveedorId) && isset($proveedorId->id)) {
            $proveedorId = $proveedorId->id;
        }

        $this->proveedorId = (int) $proveedorId;

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $proveedor = Proveedor::where('id', $this->proveedorId)
            ->where('store_id', $store->id)
            ->first();

        if ($proveedor) {
            $proveedor->load('productos');
            $this->nombre = $proveedor->nombre;
            $this->numero_celular = $proveedor->numero_celular;
            $this->telefono = $proveedor->telefono;
            $this->email = $proveedor->email;
            $this->nit = $proveedor->nit;
            $this->direccion = $proveedor->direccion;
            $this->estado = $proveedor->estado;
            $this->producto_ids = $proveedor->productos->pluck('id')->toArray();

            $this->dispatch('open-modal', 'edit-proveedor');
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:1', 'max:255'],
            'numero_celular' => ['nullable', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'nit' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string'],
            'estado' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del proveedor es obligatorio.',
            'email.email' => 'Debe ser un correo electrónico válido.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    /** Productos seleccionados (para mostrar en la lista). */
    public function getProductosSeleccionadosProperty()
    {
        if (empty($this->producto_ids)) {
            return collect();
        }
        return Product::whereIn('id', $this->producto_ids)->orderBy('name')->get();
    }

    public function buscarProductos(ProductService $productService): void
    {
        if (empty($this->busquedaProducto)) {
            $this->productosEncontrados = [];
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $this->productosEncontrados = $productService->buscarProductos($store, $this->busquedaProducto, $this->producto_ids)
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])
            ->toArray();
    }

    public function agregarProducto(int $productId): void
    {
        if (in_array($productId, $this->producto_ids)) {
            return;
        }
        $this->producto_ids[] = $productId;
        $this->producto_ids = array_values(array_unique($this->producto_ids));
        $this->buscarProductos(app(ProductService::class));
    }

    public function quitarProducto(int $productId): void
    {
        $this->producto_ids = array_values(array_filter($this->producto_ids, fn ($id) => $id !== $productId));
        $this->buscarProductos(app(ProductService::class));
    }

    public function update(ProveedorService $proveedorService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar proveedores en esta tienda.');
        }

        if (! $this->proveedorId) {
            return;
        }

        try {
            $proveedorService->actualizarProveedor($store, $this->proveedorId, [
                'nombre' => $this->nombre,
                'numero_celular' => $this->numero_celular ?: null,
                'telefono' => $this->telefono ?: null,
                'email' => $this->email ?: null,
                'nit' => $this->nit ?: null,
                'direccion' => $this->direccion ?: null,
                'estado' => $this->estado,
                'producto_ids' => array_map('intval', $this->producto_ids),
            ]);

            $this->reset(['proveedorId', 'nombre', 'numero_celular', 'telefono', 'email', 'nit', 'direccion', 'estado', 'producto_ids', 'busquedaProducto', 'productosEncontrados']);
            $this->resetValidation();

            return redirect()->route('stores.proveedores', $store)
                ->with('success', 'Proveedor actualizado correctamente.');
        } catch (\Exception $e) {
            $this->addError('nombre', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.edit-proveedor-modal');
    }
}
