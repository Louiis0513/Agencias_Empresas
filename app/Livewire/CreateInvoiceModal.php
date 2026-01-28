<?php

namespace App\Livewire;

use App\Models\Store;
use App\Models\Product;
use App\Services\InvoiceService;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateInvoiceModal extends Component
{
    public int $storeId;

    // Cliente
    public ?int $customer_id = null;

    // Productos
    public array $productosSeleccionados = []; // [['product_id' => X, 'name' => '...', 'price' => Y, 'quantity' => Z, 'subtotal' => W], ...]
    public string $busquedaProducto = '';
    public array $productosEncontrados = [];

    // Descuentos
    public string $discountType = 'amount'; // 'amount' o 'percent'
    public string $discountValue = '0';

    // Totales
    public float $subtotal = 0;
    public float $discount = 0;
    public float $total = 0;

    // Factura
    public string $status = 'PAID';
    public string $payment_method = 'CASH';

    public function mount()
    {
        $this->calcularTotales();
    }

    public function resetFormulario()
    {
        $this->customer_id = null;
        $this->productosSeleccionados = [];
        $this->busquedaProducto = '';
        $this->productosEncontrados = [];
        $this->discountType = 'amount';
        $this->discountValue = '0';
        $this->subtotal = 0;
        $this->discount = 0;
        $this->total = 0;
        $this->status = 'PAID';
        $this->payment_method = 'CASH';
        $this->calcularTotales();
    }

    public function buscarProductos(InvoiceService $invoiceService)
    {
        if (empty($this->busquedaProducto)) {
            $this->productosEncontrados = [];
            return;
        }

        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $this->productosEncontrados = $invoiceService->buscarProductos($store, $this->busquedaProducto)
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float)$product->price,
                    'stock' => $product->stock,
                    'barcode' => $product->barcode,
                ];
            })
            ->toArray();
    }

    public function agregarProducto($productId)
    {
        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $producto = Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->first();

        if (!$producto) {
            $this->addError('busquedaProducto', 'Producto no encontrado.');
            return;
        }

        // Verificar si el producto ya está en la lista
        $existe = false;
        foreach ($this->productosSeleccionados as $key => $item) {
            if ($item['product_id'] == $productId) {
                // Incrementar cantidad
                $this->productosSeleccionados[$key]['quantity']++;
                $this->productosSeleccionados[$key]['subtotal'] = 
                    $this->productosSeleccionados[$key]['price'] * $this->productosSeleccionados[$key]['quantity'];
                $existe = true;
                break;
            }
        }

        if (!$existe) {
            // Agregar nuevo producto
            $this->productosSeleccionados[] = [
                'product_id' => $producto->id,
                'name' => $producto->name,
                'price' => (float)$producto->price,
                'quantity' => 1,
                'subtotal' => (float)$producto->price,
            ];
        }

        $this->busquedaProducto = '';
        $this->productosEncontrados = [];
        $this->calcularTotales();
    }

    public function actualizarCantidad($index, $cantidad)
    {
        if (isset($this->productosSeleccionados[$index])) {
            $cantidad = max(1, (int)$cantidad); // Mínimo 1
            $this->productosSeleccionados[$index]['quantity'] = $cantidad;
            $this->productosSeleccionados[$index]['subtotal'] = 
                $this->productosSeleccionados[$index]['price'] * $cantidad;
            $this->calcularTotales();
        }
    }

    public function eliminarProducto($index)
    {
        if (isset($this->productosSeleccionados[$index])) {
            unset($this->productosSeleccionados[$index]);
            $this->productosSeleccionados = array_values($this->productosSeleccionados); // Reindexar
            $this->calcularTotales();
        }
    }

    public function updatedDiscountValue()
    {
        $this->calcularTotales();
    }

    public function updatedDiscountType()
    {
        $this->calcularTotales();
    }

    public function calcularTotales()
    {
        // Preparar detalles para el cálculo
        $details = array_map(function($item) {
            return [
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
            ];
        }, $this->productosSeleccionados);

        // Calcular subtotal sumando todos los detalles
        $subtotal = 0;
        foreach ($details as $detail) {
            $subtotal += ($detail['unit_price'] * $detail['quantity']);
        }

        // Aplicar descuentos
        $discount = 0;
        
        if ($this->discountType === 'percent') {
            $discountPercent = (float)($this->discountValue ?: 0);
            if ($discountPercent > 0) {
                $discount = $subtotal * ($discountPercent / 100);
            }
        } else {
            $discount = (float)($this->discountValue ?: 0);
        }

        // El total es subtotal menos descuentos
        $total = $subtotal - $discount;

        // Asegurar que el total no sea negativo
        if ($total < 0) {
            $total = 0;
            $discount = $subtotal; // Ajustar el descuento para que no exceda el subtotal
        }

        $this->subtotal = round($subtotal, 2);
        $this->discount = round($discount, 2);
        $this->total = round($total, 2);
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function save(InvoiceService $invoiceService)
    {
        // Validaciones
        if (!$this->customer_id) {
            $this->addError('customer_id', 'Debes seleccionar un cliente.');
            return;
        }

        if (empty($this->productosSeleccionados)) {
            $this->addError('productosSeleccionados', 'Debes agregar al menos un producto.');
            return;
        }

        if ($this->total <= 0) {
            $this->addError('total', 'El total debe ser mayor a 0.');
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear facturas en esta tienda.');
        }

        try {
            // Preparar detalles para guardar
            $details = array_map(function($item) {
                return [
                    'product_id' => $item['product_id'],
                    'unit_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ];
            }, $this->productosSeleccionados);

            $invoiceService->crearFactura($store, Auth::id(), [
                'customer_id' => $this->customer_id,
                'subtotal' => $this->subtotal,
                'tax' => 0, // Por ahora sin impuestos
                'discount' => $this->discount,
                'total' => $this->total,
                'status' => $this->status,
                'payment_method' => $this->payment_method,
                'details' => $details,
            ]);

            // Resetear formulario
            $this->resetFormulario();

            return redirect()->route('stores.invoices', $store)
                ->with('success', 'Factura creada correctamente.');
        } catch (\Exception $e) {
            $this->addError('customer_id', $e->getMessage());
        }
    }

    public function render()
    {
        $store = $this->getStoreProperty();
        $customers = $store ? app(CustomerService::class)->getAllStoreCustomers($store) : collect();

        return view('livewire.create-invoice-modal', [
            'customers' => $customers,
        ]);
    }
}
