<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class InvoiceService
{
    /* -------------------------------------------------------------------------- */
    /* CONSULTAS (LECTURA)                                                         */
    /* -------------------------------------------------------------------------- */

    /**
     * Obtiene el rango de fechas para filtrar facturas (últimos 31 días por defecto).
     * 
     * @return array ['fecha_desde' => Carbon, 'fecha_hasta' => Carbon]
     */
    public function getRangoFechasPorDefecto(): array
    {
        $fechaHasta = now();
        $fechaDesde = now()->subDays(30);

        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ];
    }

    /**
     * Lista facturas con filtros opcionales y paginación.
     * Por defecto muestra solo las facturas de los últimos 31 días.
     * 
     * @param Store $store Tienda
     * @param array $filtros Filtros opcionales: status, customer_id, search, payment_method, fecha_desde, fecha_hasta, per_page
     * @return LengthAwarePaginator
     */
    public function listarFacturas(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = Invoice::deTienda($store->id)
            ->with(['user:id,name,email', 'customer:id,name,email', 'details']);

        // Filtro por rango de fechas. fecha_hasta = fin del día para incluir facturas de hoy.
        if (isset($filtros['fecha_desde']) && isset($filtros['fecha_hasta'])) {
            $desde = Carbon::parse($filtros['fecha_desde'])->startOfDay();
            $hasta = Carbon::parse($filtros['fecha_hasta'])->endOfDay();
            $query->whereBetween('created_at', [$desde, $hasta]);
        } else {
            $rango = $this->getRangoFechasPorDefecto();
            $query->whereBetween('created_at', [
                $rango['fecha_desde']->copy()->startOfDay(),
                $rango['fecha_hasta']->copy()->endOfDay(),
            ]);
        }

        // Filtro por estado
        if (isset($filtros['status']) && !empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        // Filtro por cliente
        if (isset($filtros['customer_id']) && !empty($filtros['customer_id'])) {
            $query->where('customer_id', $filtros['customer_id']);
        }

        // Filtro por método de pago
        if (isset($filtros['payment_method']) && !empty($filtros['payment_method'])) {
            $query->where('payment_method', $filtros['payment_method']);
        }

        // Búsqueda (usa el scope del modelo)
        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }

        // Paginación
        $perPage = $filtros['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Obtiene una factura específica por ID, validando que pertenezca a la tienda.
     * 
     * @param Store $store Tienda
     * @param int $invoiceId ID de la factura
     * @return Invoice
     * @throws Exception Si la factura no existe o no pertenece a la tienda
     */
    public function obtenerFactura(Store $store, int $invoiceId): Invoice
    {
        $factura = Invoice::deTienda($store->id)
            ->with(['details.product', 'customer', 'user'])
            ->find($invoiceId);

        if (!$factura) {
            throw new Exception("La factura #{$invoiceId} no existe o no pertenece a esta tienda.");
        }

        return $factura;
    }

    /* -------------------------------------------------------------------------- */
    /* OPERACIONES (ESCRITURA)                                                    */
    /* -------------------------------------------------------------------------- */

    /**
     * Crea una factura (solo cabecera y detalles). No modifica inventario, caja ni cuenta por cobrar.
     * El flujo completo (validar stock, descontar inventario, comprobante de ingreso o cuenta por cobrar)
     * lo orquesta VentaService::registrarVentaContado o VentaService::ventaACredito.
     *
     * @param Store $store Tienda
     * @param int $userId ID del usuario que crea la factura
     * @param array $datos customer_id, subtotal, tax, discount, total, status, details, payments (solo para derivar payment_method si PAID)
     * @return Invoice
     */
    public function crearFactura(Store $store, int $userId, array $datos): Invoice
    {
        $status = $datos['status'] ?? 'PAID';
        $payments = $datos['payments'] ?? [];
        if ($status === 'PENDING') {
            $payments = [];
        }
        $methods = array_unique(array_filter(array_column($payments, 'payment_method')));
        $datos['payment_method'] = count($methods) > 1 ? 'MIXED' : ($methods[0] ?? ($status === 'PAID' ? 'CASH' : 'CREDIT'));
        $datos['status'] = $status;

        return $this->crearFacturaSoloCabeceraYDetalles($store, $userId, $datos);
    }

    /**
     * Crea la factura solo con cabecera y detalles (sin inventario, caja ni cuenta por cobrar).
     * Pensado para ser orquestado por VentaService: el orquestador valida stock, crea la factura aquí,
     * descuenta inventario y, si es contado, crea el comprobante de ingreso.
     *
     * @param  array  $datos  customer_id, subtotal, tax, discount, total, status ('PAID'|'PENDING'),
     *                        payment_method (opcional; si PAID se puede derivar de destinos), details
     * @return Invoice
     */
    public function crearFacturaSoloCabeceraYDetalles(Store $store, int $userId, array $datos): Invoice
    {
        return DB::transaction(function () use ($store, $userId, $datos) {
            $status = $datos['status'] ?? 'PENDING';
            $paymentMethod = $datos['payment_method'] ?? ($status === 'PAID' ? 'CASH' : 'CREDIT');

            $factura = Invoice::create([
                'store_id'        => $store->id,
                'user_id'         => $userId,
                'customer_id'     => $datos['customer_id'] ?? null,
                'subtotal'        => $datos['subtotal'],
                'tax'             => $datos['tax'] ?? 0,
                'discount'        => $datos['discount'] ?? 0,
                'total'           => $datos['total'],
                'status'          => $status,
                'payment_method'  => $paymentMethod,
            ]);

            foreach ($datos['details'] ?? [] as $item) {
                $this->crearDetalleSinValidarStock($store, $factura, $item);
            }

            return $factura->load(['details.product', 'customer', 'user']);
        });
    }

    /**
     * Crea la factura en estado PENDING solo con cabecera y detalles (sin inventario, caja ni cuenta por cobrar).
     * Delega en crearFacturaSoloCabeceraYDetalles con status PENDING.
     *
     * @param  array  $datos  customer_id, subtotal, tax, discount, total, details
     * @return Invoice
     */
    public function crearFacturaPendienteSoloCabeceraYDetalles(Store $store, int $userId, array $datos): Invoice
    {
        $datos['status'] = 'PENDING';
        $datos['payment_method'] = 'CREDIT';
        return $this->crearFacturaSoloCabeceraYDetalles($store, $userId, $datos);
    }

    /**
     * Anula una factura marcándola como VOID.
     * Esta función solo cambia el estado de la factura, no modifica inventario.
     * 
     * @param Store $store Tienda
     * @param Invoice $factura Factura a anular
     * @return Invoice
     * @throws Exception Si la factura ya está anulada
     */
    public function anularFactura(Store $store, Invoice $factura): Invoice
    {
        if ($factura->store_id !== $store->id) {
            throw new Exception("La factura no pertenece a esta tienda.");
        }

        if ($factura->status === 'VOID') {
            throw new Exception("La factura ya está anulada.");
        }

        // Solo marcar como anulada
        $factura->status = 'VOID';
        $factura->save();

        return $factura;
    }

    /**
     * Marca una factura como pagada (cambia estado de PENDING a PAID).
     * 
     * @param Store $store Tienda
     * @param Invoice $factura Factura a marcar como pagada
     * @return Invoice
     * @throws Exception Si la factura no está en estado PENDING
     */
    public function marcarComoPagada(Store $store, Invoice $factura): Invoice
    {
        if ($factura->store_id !== $store->id) {
            throw new Exception("La factura no pertenece a esta tienda.");
        }

        if ($factura->status === 'VOID') {
            throw new Exception("No se puede marcar como pagada una factura anulada.");
        }

        if ($factura->status === 'PAID') {
            throw new Exception("La factura ya está marcada como pagada.");
        }

        if ($factura->status !== 'PENDING') {
            throw new Exception("Solo se pueden marcar como pagadas las facturas en estado PENDING. Estado actual: {$factura->status}");
        }

        $factura->status = 'PAID';
        $factura->save();

        return $factura;
    }

    /**
     * Asigna o cambia el cliente de una factura existente.
     * 
     * @param Store $store Tienda
     * @param Invoice $factura Factura a modificar
     * @param int|null $customerId ID del cliente a asignar (null para quitar cliente)
     * @return Invoice
     * @throws Exception Si el cliente no existe o no pertenece a la tienda
     */
    public function asignarCliente(Store $store, Invoice $factura, ?int $customerId): Invoice
    {
        if ($factura->store_id !== $store->id) {
            throw new Exception("La factura no pertenece a esta tienda.");
        }

        // Si se proporciona un customer_id, validar que exista y pertenezca a la tienda
        if ($customerId !== null) {
            $cliente = \App\Models\Customer::where('id', $customerId)
                ->where('store_id', $store->id)
                ->first();

            if (!$cliente) {
                throw new Exception("El cliente #{$customerId} no existe o no pertenece a esta tienda.");
            }
        }

        // Actualizar el cliente de la factura
        $factura->customer_id = $customerId;
        $factura->save();

        return $factura->fresh(['customer']);
    }

    /**
     * Calcula los totales de una factura basándose en los detalles.
     * 
     * @param array $details Array de detalles con: quantity, unit_price, discount (opcional)
     * @param float $discountAmount Descuento total (monto fijo)
     * @param float $discountPercent Descuento total (porcentaje)
     * @return array ['subtotal' => float, 'discount' => float, 'total' => float]
     */
    public function calcularTotales(array $details, float $discountAmount = 0, float $discountPercent = 0): array
    {
        // Calcular subtotal sumando todos los detalles
        $subtotal = 0;
        foreach ($details as $detail) {
            $itemSubtotal = ($detail['unit_price'] * $detail['quantity']);
            // Si el detalle tiene descuento individual, aplicarlo
            if (isset($detail['discount'])) {
                $itemSubtotal -= $detail['discount'];
            }
            $subtotal += $itemSubtotal;
        }

        // Aplicar descuentos globales
        $discount = 0;
        
        // Primero aplicar descuento por porcentaje
        if ($discountPercent > 0) {
            $discount = $subtotal * ($discountPercent / 100);
        }
        
        // Luego aplicar descuento por monto fijo (se suma al descuento por porcentaje)
        $discount += $discountAmount;

        // El total es subtotal menos descuentos
        $total = $subtotal - $discount;

        // Asegurar que el total no sea negativo
        if ($total < 0) {
            $total = 0;
            $discount = $subtotal; // Ajustar el descuento para que no exceda el subtotal
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Busca productos para agregar a una factura.
     * Busca por ID, nombre o código de barras.
     * 
     * @param Store $store Tienda
     * @param string $termino Término de búsqueda
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function buscarProductos(Store $store, string $termino)
    {
        return Product::where('store_id', $store->id)
            ->where('is_active', true)
            ->where(function($query) use ($termino) {
                $query->where('id', $termino)
                    ->orWhere('name', 'like', "%{$termino}%")
                    ->orWhere('barcode', 'like', "%{$termino}%");
            })
            ->orderBy('name')
            ->limit(20) // Limitar resultados para rendimiento
            ->get();
    }

    /**
     * Procesa un detalle de factura (crea la línea y valida stock en producto).
     * Legacy: ya no se usa desde crearFactura (ahora delega en crearFacturaSoloCabeceraYDetalles sin stock).
     */
    private function procesarDetalle(Store $store, Invoice $factura, array $item): void
    {
        $producto = Product::where('id', $item['product_id'])
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();

        $qty = (int) $item['quantity'];
        if ($producto->isProductoInventario() && $producto->stock < $qty) {
            throw new Exception(
                "Stock insuficiente en «{$producto->name}». Actual: {$producto->stock}, solicitado: {$qty}."
            );
        }

        InvoiceDetail::create([
            'invoice_id'   => $factura->id,
            'product_id'   => $producto->id,
            'product_name' => $producto->name,
            'unit_price'   => $item['unit_price'],
            'quantity'     => $qty,
            'subtotal'     => $item['subtotal'],
        ]);
    }

    /**
     * Crea un detalle de factura sin validar stock. Usado por crearFacturaSoloCabeceraYDetalles;
     * el orquestador (VentaService) es responsable de validar y descontar inventario.
     */
    private function crearDetalleSinValidarStock(Store $store, Invoice $factura, array $item): void
    {
        $producto = Product::where('id', $item['product_id'])
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();

        $qty = (int) ($item['quantity'] ?? 0);

        InvoiceDetail::create([
            'invoice_id'   => $factura->id,
            'product_id'   => $producto->id,
            'product_name' => $producto->name,
            'unit_price'   => $item['unit_price'],
            'quantity'     => $qty,
            'subtotal'     => $item['subtotal'],
        ]);
    }
}
