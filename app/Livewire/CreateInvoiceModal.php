<?php

namespace App\Livewire;

use App\Models\ProductItem;
use App\Models\Store;
use App\Models\Product;
use App\Models\Customer;
use App\Services\CajaService;
use App\Services\InventarioService;
use App\Services\VentaService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateInvoiceModal extends Component
{
    public int $storeId;

    /** Evita doble envío del formulario (cuentas por cobrar duplicadas). */
    protected bool $saving = false;

    // Cliente
    public ?int $customer_id = null;
    public ?array $clienteSeleccionado = null; // ['id' => X, 'name' => '...', 'document_number' => '...', ...]

    /** Modal buscar cliente: filtros identificados por tipo */
    public bool $mostrarModalCliente = false;
    public string $filtroClienteNombre = '';
    public string $filtroClienteDocumento = '';
    public string $filtroClienteTelefono = '';
    public array $clientesEncontrados = [];

    // Productos (cada ítem: product_id, name, price, quantity, subtotal; opcional: type, variant_display_name, variant_features, serial_numbers)
    public array $productosSeleccionados = [];

    /** Pendiente: producto simple o variante a agregar (pedir cantidad, validar stock). */
    public ?array $pendienteSimple = null;
    public ?array $pendienteBatch = null;
    public int $cantidadSimple = 1;
    public int $cantidadBatch = 1;
    public ?string $errorStock = null;

    /** Modal unidades serializadas (solo status AVAILABLE) */
    public ?int $productoSerializadoId = null;
    public string $productoSerializadoNombre = '';
    public array $unidadesDisponibles = [];
    public array $serialesSeleccionados = [];
    public int $unidadesDisponiblesPage = 1;
    public string $unidadesDisponiblesSearch = '';
    public int $unidadesDisponiblesTotal = 0;
    public int $unidadesDisponiblesPerPage = 15;

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
        $this->saving = false;
        $this->customer_id = null;
        $this->clienteSeleccionado = null;
        $this->cerrarModalCliente();
        $this->productosSeleccionados = [];
        $this->pendienteSimple = null;
        $this->pendienteBatch = null;
        $this->cantidadSimple = 1;
        $this->cantidadBatch = 1;
        $this->errorStock = null;
        $this->cerrarModalUnidadesFactura();
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

    public function abrirModalCliente(): void
    {
        $this->mostrarModalCliente = true;
        $this->filtroClienteNombre = '';
        $this->filtroClienteDocumento = '';
        $this->filtroClienteTelefono = '';
        $this->clientesEncontrados = [];
    }

    public function cerrarModalCliente(): void
    {
        $this->mostrarModalCliente = false;
        $this->filtroClienteNombre = '';
        $this->filtroClienteDocumento = '';
        $this->filtroClienteTelefono = '';
        $this->clientesEncontrados = [];
    }

    /** Busca clientes por filtros identificados: nombre, documento o teléfono. */
    public function buscarClientes(): void
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            $this->clientesEncontrados = [];
            return;
        }

        $nombre = trim($this->filtroClienteNombre);
        $documento = trim($this->filtroClienteDocumento);
        $telefono = trim($this->filtroClienteTelefono);

        if ($nombre === '' && $documento === '' && $telefono === '') {
            $this->clientesEncontrados = [];
            return;
        }

        $query = Customer::deTienda($store->id);

        if ($nombre !== '') {
            $query->where('name', 'like', '%' . $nombre . '%');
        }
        if ($documento !== '') {
            $query->where('document_number', 'like', '%' . $documento . '%');
        }
        if ($telefono !== '') {
            $query->where('phone', 'like', '%' . $telefono . '%');
        }

        $this->clientesEncontrados = $query->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'document_number' => $customer->document_number,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ])
            ->toArray();
    }

    public function seleccionarCliente($clienteId): void
    {
        $store = $this->getStoreProperty();
        if (! $store) {
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
            $this->cerrarModalCliente();
        }
    }

    public function limpiarCliente(): void
    {
        $this->customer_id = null;
        $this->clienteSeleccionado = null;
        $this->cerrarModalCliente();
    }

    /** Abre el modal de selección de producto (contexto factura). */
    public function abrirSelectorProducto(): void
    {
        $this->errorStock = null;
        $this->dispatch('open-select-item-for-row', rowId: 'factura', itemType: 'INVENTARIO', productIdsInCartSimple: []);
    }

    /**
     * Escucha selección desde SelectItemModal. rowId === 'factura' → simple / batch / serialized.
     */
    #[On('item-selected')]
    public function onItemSelected($rowId, $id, $name, $type, $productType = null): void
    {
        if ($rowId !== 'factura' || $type !== 'INVENTARIO') {
            return;
        }
        $productType = $productType ?? 'simple';
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        if ($productType === 'simple') {
            $producto = Product::where('id', $id)->where('store_id', $store->id)->where('is_active', true)->first();
            if (! $producto) {
                return;
            }
            $ventaService = app(VentaService::class);
            $disponibilidad = $ventaService->verificadorCarrito($store, (int) $producto->id);
            $this->pendienteSimple = [
                'product_id' => $producto->id,
                'name' => $producto->name,
                'price' => $ventaService->verPrecio($store, (int) $producto->id, 'simple'),
                'stock' => (int) $disponibilidad['cantidad'],
            ];
            $this->pendienteBatch = null;
            $this->cantidadSimple = 1;
        } elseif ($productType === 'batch') {
            $this->pendienteSimple = null;
            $this->dispatch('open-select-batch-variant', productId: $id, rowId: 'factura', productName: $name, variantKeysInCart: $this->getVariantKeysInFacturaParaProducto((int) $id));
        } else {
            $this->pendienteSimple = null;
            $this->pendienteBatch = null;
            $this->abrirModalUnidadesFactura((int) $id);
        }
    }

    /**
     * Escucha selección de variante (lote). Setea pendienteBatch para pedir cantidad y validar.
     * Usa el precio enviado por el modal cuando viene (rowId factura); si no, obtiene vía InventarioService.
     */
    #[On('batch-variant-selected')]
    public function onBatchVariantSelected($rowId, $productId, $productName, $variantFeatures, $displayName, $totalStock = 0, $price = null): void
    {
        if ($rowId !== 'factura') {
            return;
        }
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }
        $producto = Product::where('id', $productId)->where('store_id', $store->id)->first();
        if (! $producto) {
            return;
        }
        $variantFeatures = is_array($variantFeatures) ? $variantFeatures : [];
        $ventaService = app(VentaService::class);
        $stock = (int) $totalStock;
        if ($stock < 1) {
            $r = $ventaService->verificadorCarritoVariante($store, (int) $productId, $variantFeatures);
            $stock = (int) $r['cantidad'];
        }
        $this->pendienteBatch = [
            'product_id' => (int) $productId,
            'name' => $productName,
            'variant_features' => $variantFeatures,
            'variant_display_name' => $displayName ?? '',
            'price' => $price !== null && (float) $price >= 0
                ? (float) $price
                : $ventaService->verPrecio($store, (int) $productId, 'batch', $variantFeatures),
            'stock' => $stock,
        ];
        $this->pendienteSimple = null;
        $this->cantidadBatch = 1;
    }

    /** Claves de variante ya en la factura para este producto (evitar duplicar en selector). */
    protected function getVariantKeysInFacturaParaProducto(int $productId): array
    {
        $keys = [];
        foreach ($this->productosSeleccionados as $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }
            if (empty($item['variant_features']) || ! is_array($item['variant_features'])) {
                continue;
            }
            $keys[] = InventarioService::detectorDeVariantesEnLotes($item['variant_features']);
        }
        return array_values(array_unique($keys));
    }

    public function cancelarPendienteSimple(): void
    {
        $this->pendienteSimple = null;
    }

    public function cancelarPendienteBatch(): void
    {
        $this->pendienteBatch = null;
    }

    public function confirmarAgregarSimpleFactura(VentaService $ventaService): void
    {
        $this->errorStock = null;
        if (! $this->pendienteSimple) {
            return;
        }
        $quantity = max(1, (int) $this->cantidadSimple);
        $store = $this->getStoreProperty();
        if (! $store) {
            $this->pendienteSimple = null;
            return;
        }
        $productId = (int) $this->pendienteSimple['product_id'];
        $stock = (int) $this->pendienteSimple['stock'];
        if ($quantity > $stock) {
            $this->errorStock = "Stock insuficiente. Disponible: {$stock}, solicitado: {$quantity}.";
            return;
        }
        $productosSimulado = $this->productosSeleccionados;
        $productosSimulado[] = [
            'product_id' => $productId,
            'name' => $this->pendienteSimple['name'],
            'quantity' => $quantity,
            'price' => (float) $this->pendienteSimple['price'],
            'type' => 'simple',
        ];
        $items = $this->productosFacturaToItemsParaValidar($productosSimulado);
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }
        $precio = (float) $this->pendienteSimple['price'];
        $this->productosSeleccionados[] = [
            'product_id' => $productId,
            'name' => $this->pendienteSimple['name'],
            'price' => $precio,
            'quantity' => $quantity,
            'subtotal' => $precio * $quantity,
            'type' => 'simple',
        ];
        $this->pendienteSimple = null;
        $this->cantidadSimple = 1;
        $this->calcularTotales();
    }

    public function confirmarAgregarVarianteFactura(VentaService $ventaService): void
    {
        $this->errorStock = null;
        if (! $this->pendienteBatch) {
            return;
        }
        $quantity = max(1, (int) $this->cantidadBatch);
        $store = $this->getStoreProperty();
        if (! $store) {
            $this->pendienteBatch = null;
            return;
        }
        $productId = (int) $this->pendienteBatch['product_id'];
        $variantFeatures = $this->pendienteBatch['variant_features'] ?? [];
        $stockVariante = (int) $this->pendienteBatch['stock'];
        if ($quantity > $stockVariante) {
            $this->errorStock = "Stock insuficiente en esta variante. Disponible: {$stockVariante}, solicitado: {$quantity}.";
            return;
        }
        $productosSimulado = $this->productosSeleccionados;
        $productosSimulado[] = [
            'product_id' => $productId,
            'name' => $this->pendienteBatch['name'],
            'variant_features' => $variantFeatures,
            'variant_display_name' => $this->pendienteBatch['variant_display_name'] ?? '',
            'quantity' => $quantity,
            'price' => (float) $this->pendienteBatch['price'],
            'type' => 'batch',
        ];
        $items = $this->productosFacturaToItemsParaValidar($productosSimulado);
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }
        $precio = (float) $this->pendienteBatch['price'];
        $this->productosSeleccionados[] = [
            'product_id' => $productId,
            'name' => $this->pendienteBatch['name'],
            'price' => $precio,
            'quantity' => $quantity,
            'subtotal' => $precio * $quantity,
            'type' => 'batch',
            'variant_features' => $variantFeatures,
            'variant_display_name' => $this->pendienteBatch['variant_display_name'] ?? '',
        ];
        $this->pendienteBatch = null;
        $this->cantidadBatch = 1;
        $this->calcularTotales();
    }

    /** Convierte productosSeleccionados a formato para validar stock. */
    protected function productosFacturaToItemsParaValidar(array $productos): array
    {
        $items = [];
        $byProduct = [];
        foreach ($productos as $row) {
            if (! empty($row['serial_numbers'])) {
                $items[] = ['product_id' => $row['product_id'], 'serial_numbers' => $row['serial_numbers']];
            } elseif (! empty($row['variant_features']) && is_array($row['variant_features'])) {
                $items[] = [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'variant_features' => $row['variant_features'],
                    'quantity' => (int) ($row['quantity'] ?? 0),
                ];
            } else {
                $pid = (int) ($row['product_id'] ?? 0);
                if ($pid > 0) {
                    $byProduct[$pid] = ($byProduct[$pid] ?? 0) + (int) ($row['quantity'] ?? 0);
                }
            }
        }
        foreach ($byProduct as $productId => $totalQty) {
            if ($totalQty > 0) {
                $items[] = ['product_id' => $productId, 'quantity' => $totalQty];
            }
        }
        return $items;
    }

    /** Abre el modal de unidades disponibles para un producto serializado (solo status AVAILABLE). */
    public function abrirModalUnidadesFactura(int $productId): void
    {
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
        $this->cargarPaginaUnidadesDisponiblesFactura();
    }

    /** Seriales ya en la factura para este producto (no mostrarlos de nuevo). */
    protected function getSerialesEnFacturaParaProducto(int $productId): array
    {
        $serials = [];
        foreach ($this->productosSeleccionados as $item) {
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }
            foreach ($item['serial_numbers'] ?? [] as $sn) {
                $serials[] = $sn;
            }
        }
        return array_values(array_unique($serials));
    }

    /** Carga unidades disponibles: solo status AVAILABLE. */
    public function cargarPaginaUnidadesDisponiblesFactura(): void
    {
        $store = $this->getStoreProperty();
        if (! $store || $this->productoSerializadoId === null) {
            return;
        }
        $enFactura = $this->getSerialesEnFacturaParaProducto($this->productoSerializadoId);
        $query = ProductItem::where('store_id', $store->id)
            ->where('product_id', $this->productoSerializadoId)
            ->where('status', ProductItem::STATUS_AVAILABLE);
        if (! empty($enFactura)) {
            $query->whereNotIn('serial_number', $enFactura);
        }
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

    public function irAPaginaUnidadesFactura(int $page): void
    {
        $maxPage = (int) max(1, ceil($this->unidadesDisponiblesTotal / $this->unidadesDisponiblesPerPage));
        $this->unidadesDisponiblesPage = max(1, min($page, $maxPage));
        $this->cargarPaginaUnidadesDisponiblesFactura();
    }

    public function updatedUnidadesDisponiblesSearch(): void
    {
        $this->unidadesDisponiblesPage = 1;
        $this->cargarPaginaUnidadesDisponiblesFactura();
    }

    public function cerrarModalUnidadesFactura(): void
    {
        $this->productoSerializadoId = null;
        $this->productoSerializadoNombre = '';
        $this->unidadesDisponibles = [];
        $this->serialesSeleccionados = [];
        $this->unidadesDisponiblesPage = 1;
        $this->unidadesDisponiblesSearch = '';
        $this->unidadesDisponiblesTotal = 0;
    }

    /** Agrega a la factura las unidades serializadas seleccionadas (solo vista; una línea por serie). */
    public function agregarSerializadosAFactura(): void
    {
        $this->errorStock = null;
        if ($this->productoSerializadoId === null || empty($this->serialesSeleccionados)) {
            $this->cerrarModalUnidadesFactura();
            return;
        }
        $store = $this->getStoreProperty();
        if (! $store) {
            $this->cerrarModalUnidadesFactura();
            return;
        }
        $producto = Product::where('id', $this->productoSerializadoId)->where('store_id', $store->id)->first();
        if (! $producto) {
            $this->cerrarModalUnidadesFactura();
            return;
        }
        $ventaService = app(VentaService::class);
        $serialNumbers = array_values(array_filter(array_map('trim', $this->serialesSeleccionados)));
        $items = [['product_id' => $this->productoSerializadoId, 'serial_numbers' => $serialNumbers]];
        try {
            $ventaService->validarGuardadoItemCarrito($store, $items);
        } catch (Exception $e) {
            $this->errorStock = $e->getMessage();
            return;
        }
        foreach ($serialNumbers as $serial) {
            $precio = $ventaService->verPrecio($store, $this->productoSerializadoId, 'serialized', null, [$serial]);
            $this->productosSeleccionados[] = [
                'product_id' => $this->productoSerializadoId,
                'name' => $producto->name,
                'price' => (float) $precio,
                'quantity' => 1,
                'subtotal' => (float) $precio,
                'type' => 'serialized',
                'serial_numbers' => [$serial],
            ];
        }
        $this->cerrarModalUnidadesFactura();
        $this->calcularTotales();
    }

    public function actualizarCantidad($index, $cantidad)
    {
        if (! isset($this->productosSeleccionados[$index])) {
            return;
        }
        if (($this->productosSeleccionados[$index]['type'] ?? 'simple') === 'serialized') {
            return; // Cantidad fija 1 por serie
        }
        $cantidad = max(1, (int) $cantidad);
        $this->productosSeleccionados[$index]['quantity'] = $cantidad;
        $this->productosSeleccionados[$index]['subtotal'] =
            $this->productosSeleccionados[$index]['price'] * $cantidad;
        $this->calcularTotales();
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

    public function save(VentaService $ventaService)
    {
        if ($this->saving) {
            return;
        }
        $this->saving = true;

        $store = $this->getStoreProperty();
        $lockKey = 'create-invoice-lock:' . ($store?->id ?? 0) . ':' . Auth::id();
        $lock = Cache::lock($lockKey, 15);
        if (! $lock->get()) {
            $this->saving = false;
            return;
        }

        try {
            $this->saveInvoice($ventaService, $store);
        } finally {
            $lock->release();
        }
    }

    protected function saveInvoice(VentaService $ventaService, ?Store $store): void
    {
        // Validaciones
        if (!$this->customer_id) {
            $this->saving = false;
            $this->addError('customer_id', 'Debes seleccionar un cliente.');
            return;
        }

        if (empty($this->productosSeleccionados)) {
            $this->addError('productosSeleccionados', 'Debes agregar al menos un producto.');
            $this->saving = false;
            return;
        }

        if ($this->total <= 0) {
            $this->addError('total', 'El total debe ser mayor a 0.');
            $this->saving = false;
            return;
        }

        if ($this->status === 'PAID') {
            $diferencia = $this->diferenciaPago;
            if (abs($diferencia) > 0.01) {
                $this->addError('paymentParts', $diferencia > 0
                    ? "La suma de pagos ({$this->totalPagado}) debe ser igual al total ({$this->total}). Falta: {$diferencia}."
                    : "La suma de pagos ({$this->totalPagado}) supera el total ({$this->total}). Sobra: " . abs($diferencia) . ".");
                $this->saving = false;
                return;
            }
            foreach ($this->paymentParts as $i => $p) {
                $amount = (float) ($p['amount'] ?? 0);
                $bid = (int) ($p['bolsillo_id'] ?? 0);
                $method = $p['method'] ?? 'CASH';
                if ($amount <= 0) {
                    $this->addError("paymentParts.{$i}.amount", "Monto debe ser mayor a 0.");
                    $this->saving = false;
                    return;
                }
                // Validar que este pago no exceda el máximo permitido (saldo restante).
                $max = $this->maxMontoPago((int) $i);
                if ($amount > $max + 0.00001) {
                    $this->addError("paymentParts.{$i}.amount", "El monto máximo permitido para este pago es {$max}.");
                    $this->saving = false;
                    return;
                }
                if ($method === 'CASH') {
                    $recibido = (float) ($p['recibido'] ?? 0);
                    // Si el usuario usa el campo "recibido", debe ser >= monto.
                    // Permitimos 0/vacío (porque es ayuda visual), pero si escribió algo, debe cuadrar.
                    if ($recibido > 0 && $recibido + 0.00001 < $amount) {
                        $this->addError("paymentParts.{$i}.recibido", "El recibido debe ser mayor o igual al monto.");
                        $this->saving = false;
                        return;
                    }
                }
                $bolsillos = $this->bolsillosParaMetodo($method);
                if (! $bolsillos->contains('id', $bid)) {
                    $this->addError("paymentParts.{$i}.bolsillo_id", "Selecciona un bolsillo válido para {$method}.");
                    $this->saving = false;
                    return;
                }
            }
        }

        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            $this->saving = false;
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
            $ventaService->registrarVenta($store, Auth::id(), $payload);

            // Resetear formulario
            $this->resetFormulario();

            redirect()->route('stores.invoices', $store)
                ->with('success', 'Factura creada correctamente.');
        } catch (\Exception $e) {
            $this->saving = false;
            $this->addError('customer_id', $e->getMessage());
        }
    }
    // =========================================================================
    // NUEVO: CARGA DESDE CARRITO
    // =========================================================================

    #[On('load-items-from-cart')]
    public function loadFromCart(array $items, ?int $customer_id = null): void
    {
        // 1. Limpiar el estado actual
        $this->resetFormulario();

        // 2. Cargar Cliente si existe
        if ($customer_id) {
            $this->seleccionarCliente($customer_id);
        }

        // 3. Mapear Items del Carrito a Items de Factura
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            
            // Estructura compatible con $this->productosSeleccionados
            $this->productosSeleccionados[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $price,
                'quantity' => $qty,
                'subtotal' => $price * $qty,
                'type' => $item['type'] ?? 'simple',
                
                // Campos opcionales para variantes
                'variant_features' => $item['variant_features'] ?? [],
                'variant_display_name' => $item['variant_display_name'] ?? '',
                
                // Campos opcionales para seriales
                'serial_numbers' => $item['serial_numbers'] ?? [],
            ];
        }

        // 4. Calcular Totales con los nuevos items
        $this->calcularTotales();

        // 5. Abrir el modal (Asegura que se muestre al recibir los datos)
        $this->dispatch('open-modal', 'create-invoice'); 
    }

    public function render()
    {
        return view('livewire.create-invoice-modal');
    }
}
