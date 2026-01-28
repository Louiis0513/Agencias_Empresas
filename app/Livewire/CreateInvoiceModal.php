<?php

namespace App\Livewire;

use App\Models\Store;
use App\Models\Product;
use App\Models\Customer;
use App\Services\CajaService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateInvoiceModal extends Component
{
    public int $storeId;

    // Cliente
    public ?int $customer_id = null;
    public string $busquedaCliente = '';
    public array $clientesEncontrados = [];
    public ?array $clienteSeleccionado = null; // ['id' => X, 'name' => '...', 'document_number' => '...', ...]

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
    /** Partes del pago cuando status = PAID. [ ['method' => 'CASH', 'amount' => '10000', 'bolsillo_id' => 1, 'recibido' => '15000'], ... ] */
    public array $paymentParts = [];

    /**
     * Devuelve el máximo permitido para el monto de un pago dado, considerando:
     * - No puede exceder el total de la factura
     * - Si hay varios pagos, no puede hacer que la suma supere el total (saldo restante)
     */
    public function maxMontoPago(int $index): float
    {
        $total = (float) ($this->total ?? 0);
        if ($total <= 0) {
            return 0.0;
        }

        $sumOtros = 0.0;
        foreach ($this->paymentParts as $i => $p) {
            if ($i === $index) {
                continue;
            }
            $sumOtros += (float) ($p['amount'] ?? 0);
        }

        $max = $total - $sumOtros;
        if ($max < 0) {
            $max = 0;
        }

        return round($max, 2);
    }

    protected function normalizarNumero($value): float
    {
        // Acepta strings con coma o punto, y valores vacíos.
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }
        return (float) $value;
    }

    protected function ajustarMontoPago(int $index): void
    {
        if (! isset($this->paymentParts[$index])) {
            return;
        }

        $raw = $this->paymentParts[$index]['amount'] ?? 0;
        $amount = $this->normalizarNumero($raw);
        if ($amount < 0) {
            $amount = 0.0;
        }

        $max = $this->maxMontoPago($index);
        if ($amount > $max) {
            $this->addError("paymentParts.{$index}.amount", "El monto máximo permitido para este pago es {$max}.");
        } else {
            // Si ya está dentro del rango, limpiar error del campo (si existe).
            $this->resetValidation("paymentParts.{$index}.amount");
        }
    }

    protected function ajustarRecibidoPago(int $index): void
    {
        if (! isset($this->paymentParts[$index])) {
            return;
        }

        $method = $this->paymentParts[$index]['method'] ?? 'CASH';
        if ($method !== 'CASH') {
            $this->resetValidation("paymentParts.{$index}.recibido");
            return;
        }

        $amount = $this->normalizarNumero($this->paymentParts[$index]['amount'] ?? 0);
        $recibido = $this->normalizarNumero($this->paymentParts[$index]['recibido'] ?? 0);

        if ($recibido < 0) {
            $recibido = 0.0;
        }

        // Regla: recibido debe ser >= monto (solo ayuda visual, pero no debe ser inconsistente).
        if ($amount > 0 && $recibido > 0 && $recibido + 0.00001 < $amount) {
            $this->addError("paymentParts.{$index}.recibido", "El recibido debe ser mayor o igual al monto ({$amount}).");
            return;
        }

        $this->resetValidation("paymentParts.{$index}.recibido");
    }

    protected function ajustarMontosPagos(): void
    {
        if ($this->status !== 'PAID') {
            return;
        }
        // Ajustar en orden para garantizar que la suma nunca exceda el total.
        foreach (array_keys($this->paymentParts) as $i) {
            $this->ajustarMontoPago((int) $i);
            $this->ajustarRecibidoPago((int) $i);
        }
    }

    public function mount()
    {
        $this->calcularTotales();
        $this->inicializarPagosSiPagada();
    }

    public function resetFormulario()
    {
        $this->customer_id = null;
        $this->busquedaCliente = '';
        $this->clientesEncontrados = [];
        $this->clienteSeleccionado = null;
        $this->productosSeleccionados = [];
        $this->busquedaProducto = '';
        $this->productosEncontrados = [];
        $this->discountType = 'amount';
        $this->discountValue = '0';
        $this->subtotal = 0;
        $this->discount = 0;
        $this->total = 0;
        $this->status = 'PAID';
        $this->paymentParts = [];
        $this->resetValidation();
        $this->calcularTotales();
        $this->inicializarPagosSiPagada();
    }

    public function updatedStatus(): void
    {
        if ($this->status === 'PENDING') {
            $this->paymentParts = [];
        } else {
            $this->inicializarPagosSiPagada();
            $this->ajustarMontosPagos();
        }
    }

    protected function inicializarPagosSiPagada(): void
    {
        if ($this->status !== 'PAID') {
            return;
        }
        if (empty($this->paymentParts)) {
            $this->paymentParts = [
                ['id' => 'p-' . uniqid(), 'method' => 'CASH', 'amount' => '', 'bolsillo_id' => 0, 'recibido' => ''],
            ];
        }
    }

    public function agregarPago(): void
    {
        $this->paymentParts[] = ['id' => 'p-' . uniqid(), 'method' => 'CASH', 'amount' => '', 'bolsillo_id' => 0, 'recibido' => ''];
    }

    public function quitarPago(int $index): void
    {
        if (isset($this->paymentParts[$index]) && count($this->paymentParts) > 1) {
            array_splice($this->paymentParts, $index, 1);
            $this->ajustarMontosPagos();
        }
    }

    /** Al cambiar método en una parte, limpiar bolsillo. */
    public function actualizarMetodoPago(int $index): void
    {
        if (isset($this->paymentParts[$index])) {
            $this->paymentParts[$index]['bolsillo_id'] = 0;
        }
    }

    public function updatedPaymentParts($value, $name): void
    {
        // $name viene como "0.amount", "1.method", etc.
        if (! is_string($name)) {
            return;
        }
        $parts = explode('.', $name);
        if (count($parts) < 2) {
            return;
        }

        $index = (int) $parts[0];
        $field = $parts[1];

        if ($field === 'amount') {
            $this->ajustarMontoPago($index);
            $this->ajustarRecibidoPago($index);
            return;
        }

        if ($field === 'recibido') {
            $this->ajustarRecibidoPago($index);
            return;
        }

        // Si cambian productos/descuentos y por ende total, también ajustamos.
        // (Esto se cubre desde calcularTotales(), pero dejamos esto como guardia adicional.)
        if ($field === 'method') {
            // No clamp aquí; el método no afecta el máximo, solo el bolsillo.
            return;
        }
    }

    public function bolsillosParaMetodo(string $method): \Illuminate\Support\Collection
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }
        return app(CajaService::class)->obtenerBolsillosParaPago($store, $method);
    }

    /** Suma de montos ingresados en las partes de pago. */
    public function getTotalPagadoProperty(): float
    {
        $sum = 0;
        foreach ($this->paymentParts as $p) {
            $sum += (float) ($p['amount'] ?? 0);
        }
        return round($sum, 2);
    }

    /** Diferencia total - total pagado (positivo = falta, negativo = sobra). */
    public function getDiferenciaPagoProperty(): float
    {
        return round($this->total - $this->totalPagado, 2);
    }

    public function buscarClientes()
    {
        if (empty($this->busquedaCliente)) {
            $this->clientesEncontrados = [];
            return;
        }

        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $this->clientesEncontrados = Customer::deTienda($store->id)
            ->buscar($this->busquedaCliente)
            ->limit(10)
            ->get()
            ->map(function($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'document_number' => $customer->document_number,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ];
            })
            ->toArray();
    }

    public function seleccionarCliente($clienteId)
    {
        $store = $this->getStoreProperty();
        if (!$store) {
            return;
        }

        $cliente = Customer::where('id', $clienteId)
            ->where('store_id', $store->id)
            ->first();

        if ($cliente) {
            $this->customer_id = $cliente->id;
            $this->clienteSeleccionado = [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'document_number' => $cliente->document_number,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
            ];
            $this->busquedaCliente = '';
            $this->clientesEncontrados = [];
        }
    }

    public function limpiarCliente()
    {
        $this->customer_id = null;
        $this->clienteSeleccionado = null;
        $this->busquedaCliente = '';
        $this->clientesEncontrados = [];
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

        // Si el total cambió (productos/descuentos), asegurar que los montos de pago no se pasen.
        $this->ajustarMontosPagos();
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

        if ($this->status === 'PAID') {
            $diferencia = $this->diferenciaPago;
            if (abs($diferencia) > 0.01) {
                $this->addError('paymentParts', $diferencia > 0
                    ? "La suma de pagos ({$this->totalPagado}) debe ser igual al total ({$this->total}). Falta: {$diferencia}."
                    : "La suma de pagos ({$this->totalPagado}) supera el total ({$this->total}). Sobra: " . abs($diferencia) . ".");
                return;
            }
            foreach ($this->paymentParts as $i => $p) {
                $amount = (float) ($p['amount'] ?? 0);
                $bid = (int) ($p['bolsillo_id'] ?? 0);
                $method = $p['method'] ?? 'CASH';
                if ($amount <= 0) {
                    $this->addError("paymentParts.{$i}.amount", "Monto debe ser mayor a 0.");
                    return;
                }
                // Validar que este pago no exceda el máximo permitido (saldo restante).
                $max = $this->maxMontoPago((int) $i);
                if ($amount > $max + 0.00001) {
                    $this->addError("paymentParts.{$i}.amount", "El monto máximo permitido para este pago es {$max}.");
                    return;
                }
                if ($method === 'CASH') {
                    $recibido = (float) ($p['recibido'] ?? 0);
                    // Si el usuario usa el campo "recibido", debe ser >= monto.
                    // Permitimos 0/vacío (porque es ayuda visual), pero si escribió algo, debe cuadrar.
                    if ($recibido > 0 && $recibido + 0.00001 < $amount) {
                        $this->addError("paymentParts.{$i}.recibido", "El recibido debe ser mayor o igual al monto.");
                        return;
                    }
                }
                $bolsillos = $this->bolsillosParaMetodo($method);
                if (! $bolsillos->contains('id', $bid)) {
                    $this->addError("paymentParts.{$i}.bolsillo_id", "Selecciona un bolsillo válido para {$method}.");
                    return;
                }
            }
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

            $payload = [
                'customer_id' => $this->customer_id,
                'subtotal' => $this->subtotal,
                'tax' => 0,
                'discount' => $this->discount,
                'total' => $this->total,
                'status' => $this->status,
                'details' => $details,
            ];
            if ($this->status === 'PAID' && ! empty($this->paymentParts)) {
                $payload['payments'] = array_map(function ($p) {
                    return [
                        'payment_method' => $p['method'] ?? 'CASH',
                        'amount' => (float) ($p['amount'] ?? 0),
                        'bolsillo_id' => (int) ($p['bolsillo_id'] ?? 0),
                    ];
                }, array_filter($this->paymentParts, fn ($p) => ((float) ($p['amount'] ?? 0)) > 0));
            }
            $invoiceService->crearFactura($store, Auth::id(), $payload);

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
        return view('livewire.create-invoice-modal');
    }
}
