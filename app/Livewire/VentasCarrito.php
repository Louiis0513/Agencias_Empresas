<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use App\Services\InvoiceService;
use App\Services\InventarioService;
use Exception;
use Livewire\Component;

class VentasCarrito extends Component
{
    public int $storeId;

    public string $busquedaProducto = '';
    public array $productosEncontrados = [];
    /** Carrito: líneas con product_id, name, quantity, stock?, price, type ('batch'|'serialized'), y opcional serial_numbers[] */
    public array $carrito = [];
    public ?string $errorStock = null;

    /** Modal unidades disponibles (producto serializado) */
    public ?int $productoSerializadoId = null;
    public string $productoSerializadoNombre = '';
    public array $unidadesDisponibles = [];
    public array $serialesSeleccionados = [];
    public int $unidadesDisponiblesPage = 1;
    public string $unidadesDisponiblesSearch = '';
    public int $unidadesDisponiblesTotal = 0;
    public int $unidadesDisponiblesPerPage = 15;

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function buscarProductos(InvoiceService $invoiceService): void
    {
        $this->errorStock = null;
        if (trim($this->busquedaProducto) === '') {
            $this->productosEncontrados = [];
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $this->productosEncontrados = $invoiceService->buscarProductos($store, $this->busquedaProducto)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'stock' => (int) $p->stock,
                'barcode' => $p->barcode,
                'type' => $p->type ?? 'batch',
            ])
            ->values()
            ->toArray();
    }

    /**
     * Abre el modal de unidades disponibles para un producto serializado.
     */
    public function abrirModalUnidades(int $productId): void
    {
        $this->errorStock = null;
        $this->serialesSeleccionados = [];
        $this->unidadesDisponiblesSearch = '';
        $this->unidadesDisponiblesPage = 1;
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $producto = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->first();

        if (! $producto || ! $producto->isSerialized()) {
            return;
        }

        $this->productoSerializadoId = $producto->id;
        $this->productoSerializadoNombre = $producto->name;
        $this->cargarPaginaUnidadesDisponibles();
    }

    /**
     * Carga la página actual de unidades disponibles (con búsqueda por serie y paginación).
     */
    public function cargarPaginaUnidadesDisponibles(): void
    {
        $store = $this->getStoreProperty();
        if (! $store || $this->productoSerializadoId === null) {
            return;
        }

        $query = ProductItem::where('store_id', $store->id)
            ->where('product_id', $this->productoSerializadoId)
            ->where('status', ProductItem::STATUS_AVAILABLE);

        $search = trim($this->unidadesDisponiblesSearch);
        if ($search !== '') {
            $query->where('serial_number', 'like', '%' . $search . '%');
        }

        $this->unidadesDisponiblesTotal = $query->count();
        $this->unidadesDisponibles = $query->orderBy('serial_number')
            ->offset(($this->unidadesDisponiblesPage - 1) * $this->unidadesDisponiblesPerPage)
            ->limit($this->unidadesDisponiblesPerPage)
            ->get()
            ->map(fn (ProductItem $item) => [
                'id' => $item->id,
                'serial_number' => $item->serial_number,
                'features' => $item->features,
            ])
            ->values()
            ->toArray();
    }

    public function irAPaginaUnidades(int $page): void
    {
        $maxPage = (int) max(1, ceil($this->unidadesDisponiblesTotal / $this->unidadesDisponiblesPerPage));
        $this->unidadesDisponiblesPage = max(1, min($page, $maxPage));
        $this->cargarPaginaUnidadesDisponibles();
    }

    public function updatedUnidadesDisponiblesSearch(): void
    {
        $this->unidadesDisponiblesPage = 1;
        $this->cargarPaginaUnidadesDisponibles();
    }

    public function cerrarModalUnidades(): void
    {
        $this->productoSerializadoId = null;
        $this->productoSerializadoNombre = '';
        $this->unidadesDisponibles = [];
        $this->serialesSeleccionados = [];
        $this->unidadesDisponiblesPage = 1;
        $this->unidadesDisponiblesSearch = '';
        $this->unidadesDisponiblesTotal = 0;
    }

    /**
     * Agrega al carrito las unidades serializadas seleccionadas en el modal.
     */
    public function agregarSerializadosAlCarrito(InventarioService $inventarioService): void
    {
        $this->errorStock = null;
        if ($this->productoSerializadoId === null || empty($this->serialesSeleccionados)) {
            $this->cerrarModalUnidades();
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store) {
            $this->cerrarModalUnidades();
            return;
        }

        $productId = $this->productoSerializadoId;
        $producto = Product::where('id', $productId)->where('store_id', $store->id)->first();
        if (! $producto) {
            $this->cerrarModalUnidades();
            return;
        }

        $serialNumbers = array_values(array_filter(array_map('trim', $this->serialesSeleccionados)));
        if (empty($serialNumbers)) {
            $this->cerrarModalUnidades();
            return;
        }

        $items = [['product_id' => $productId, 'serial_numbers' => $serialNumbers]];
        try {
            $inventarioService->validarStockDisponible($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }

        $carritoSimulado = $this->carrito;
        $existe = false;
        foreach ($carritoSimulado as $i => $item) {
            if ((int) $item['product_id'] === $productId && isset($item['serial_numbers'])) {
                $carritoSimulado[$i]['serial_numbers'] = array_values(array_unique(array_merge($item['serial_numbers'], $serialNumbers)));
                $carritoSimulado[$i]['quantity'] = count($carritoSimulado[$i]['serial_numbers']);
                $existe = true;
                break;
            }
        }
        if (! $existe) {
            $carritoSimulado[] = [
                'product_id' => $productId,
                'name' => $producto->name,
                'quantity' => count($serialNumbers),
                'stock' => (int) $producto->stock,
                'price' => (float) $producto->price,
                'type' => 'serialized',
                'serial_numbers' => $serialNumbers,
            ];
        }

        $itemsParaValidar = $this->carritoToItemsParaValidar($carritoSimulado);
        try {
            $inventarioService->validarStockDisponible($store, $itemsParaValidar);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }

        $existe = false;
        foreach ($this->carrito as $i => $item) {
            if ((int) $item['product_id'] === $productId && isset($item['serial_numbers'])) {
                $this->carrito[$i]['serial_numbers'] = array_values(array_unique(array_merge($this->carrito[$i]['serial_numbers'], $serialNumbers)));
                $this->carrito[$i]['quantity'] = count($this->carrito[$i]['serial_numbers']);
                $this->carrito[$i]['stock'] = (int) $producto->stock;
                $existe = true;
                break;
            }
        }
        if (! $existe) {
            $this->carrito[] = [
                'product_id' => $productId,
                'name' => $producto->name,
                'quantity' => count($serialNumbers),
                'stock' => (int) $producto->stock,
                'price' => (float) $producto->price,
                'type' => 'serialized',
                'serial_numbers' => $serialNumbers,
            ];
        }

        $this->cerrarModalUnidades();
    }

    /**
     * Convierte el carrito a formato [ ['product_id', 'quantity'] o ['product_id', 'serial_numbers'] ] para validar.
     */
    protected function carritoToItemsParaValidar(array $carrito): array
    {
        $items = [];
        foreach ($carrito as $row) {
            if (! empty($row['serial_numbers'])) {
                $items[] = ['product_id' => $row['product_id'], 'serial_numbers' => $row['serial_numbers']];
            } else {
                $items[] = ['product_id' => $row['product_id'], 'quantity' => (int) ($row['quantity'] ?? 0)];
            }
        }
        return $items;
    }

    /**
     * Valida el carrito actual con InventarioService::validarStockDisponible.
     */
    protected function validarCarritoConStock(InventarioService $inventarioService): bool
    {
        $this->errorStock = null;
        $store = $this->getStoreProperty();
        if (! $store || empty($this->carrito)) {
            return true;
        }

        $items = $this->carritoToItemsParaValidar($this->carrito);
        try {
            $inventarioService->validarStockDisponible($store, $items);
            return true;
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return false;
        }
    }

    public function agregarAlCarrito(int $productId, int $quantity, InventarioService $inventarioService, InvoiceService $invoiceService): void
    {
        $this->errorStock = null;
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $quantity = max(1, $quantity);
        $producto = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->first();

        if (! $producto) {
            $this->addError('busquedaProducto', 'Producto no encontrado.');
            return;
        }

        if ($producto->isSerialized()) {
            $this->abrirModalUnidades($productId);
            return;
        }

        $carritoSimulado = $this->carrito;
        $existe = false;
        foreach ($carritoSimulado as $i => $item) {
            if ((int) $item['product_id'] === $productId && empty($item['serial_numbers'] ?? [])) {
                $carritoSimulado[$i]['quantity'] = (int) $item['quantity'] + $quantity;
                $existe = true;
                break;
            }
        }
        if (! $existe) {
            $carritoSimulado[] = [
                'product_id' => $producto->id,
                'name' => $producto->name,
                'quantity' => $quantity,
                'stock' => (int) $producto->stock,
                'price' => (float) $producto->price,
                'type' => 'batch',
            ];
        }

        $items = $this->carritoToItemsParaValidar($carritoSimulado);
        try {
            $inventarioService->validarStockDisponible($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }

        $existe = false;
        foreach ($this->carrito as $i => $item) {
            if ((int) $item['product_id'] === $productId && empty($item['serial_numbers'] ?? [])) {
                $this->carrito[$i]['quantity'] = (int) $item['quantity'] + $quantity;
                $this->carrito[$i]['stock'] = (int) $producto->stock;
                $existe = true;
                break;
            }
        }
        if (! $existe) {
            $this->carrito[] = [
                'product_id' => $producto->id,
                'name' => $producto->name,
                'quantity' => $quantity,
                'stock' => (int) $producto->stock,
                'price' => (float) $producto->price,
                'type' => 'batch',
            ];
        }
    }

    public function actualizarCantidad(int $productId, int $quantity): void
    {
        $quantity = max(0, $quantity);
        foreach ($this->carrito as $i => $item) {
            if ((int) $item['product_id'] === $productId && empty($item['serial_numbers'] ?? [])) {
                if ($quantity === 0) {
                    array_splice($this->carrito, $i, 1);
                    $this->errorStock = null;
                    return;
                }
                $cantidadAnterior = (int) $this->carrito[$i]['quantity'];
                $this->carrito[$i]['quantity'] = $quantity;
                if (! $this->validarCarritoConStock(app(InventarioService::class))) {
                    $this->carrito[$i]['quantity'] = $cantidadAnterior;
                }
                return;
            }
        }
    }

    public function quitarDelCarrito(int $productId): void
    {
        $this->carrito = array_values(array_filter($this->carrito, fn (array $item) => (int) $item['product_id'] !== $productId));
        $this->errorStock = null;
    }

    public function render()
    {
        return view('livewire.ventas-carrito');
    }
}
