<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class InvoiceService
{
    /* -------------------------------------------------------------------------- */
    /* CONSULTAS (LECTURA)                                                         */
    /* -------------------------------------------------------------------------- */

    /**
     * Lista facturas con filtros opcionales y paginación.
     * 
     * @param Store $store Tienda
     * @param array $filtros Filtros opcionales: status, customer_id, search, per_page
     * @return LengthAwarePaginator
     */
    public function listarFacturas(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = Invoice::deTienda($store->id)
            ->with(['user:id,name,email', 'customer:id,name,email', 'details']);

        // Filtro por estado
        if (isset($filtros['status']) && !empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        // Filtro por cliente
        if (isset($filtros['customer_id']) && !empty($filtros['customer_id'])) {
            $query->where('customer_id', $filtros['customer_id']);
        }

        // Búsqueda (usa el scope del modelo)
        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }

        // Paginación
        $perPage = $filtros['per_page'] ?? 15;

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
     * Crea una nueva factura, procesa detalles y actualiza stock.
     * 
     * @param Store $store Tienda
     * @param int $userId ID del usuario que crea la factura
     * @param array $datos Datos de la factura: customer_id, subtotal, tax, discount, total, status, payment_method, details
     * @return Invoice
     */
    public function crearFactura(Store $store, int $userId, array $datos): Invoice
    {
        return DB::transaction(function () use ($store, $userId, $datos) {
            
            // 1. Crear Header
            $factura = Invoice::create([
                'store_id'      => $store->id,
                'user_id'       => $userId,
                'customer_id'   => $datos['customer_id'] ?? null,
                'subtotal'      => $datos['subtotal'],
                'tax'           => $datos['tax'] ?? 0,
                'discount'      => $datos['discount'] ?? 0,
                'total'         => $datos['total'],
                'status'        => $datos['status'] ?? 'PAID',
                'payment_method' => $datos['payment_method'] ?? 'CASH',
            ]);

            // 2. Procesar Detalles y Actualizar Stock
            foreach ($datos['details'] as $item) {
                $this->procesarDetalle($store, $factura, $item);
            }

            return $factura->load(['details.product', 'customer', 'user']);
        });
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
     * Procesa un detalle de factura y actualiza el stock del producto.
     */
    private function procesarDetalle(Store $store, Invoice $factura, array $item): void
    {
        $producto = Product::where('id', $item['product_id'])
            ->where('store_id', $store->id)
            ->firstOrFail();

        // Validar stock disponible (solo si el producto tiene stock)
        if ($producto->stock !== null && $producto->stock < $item['quantity']) {
            throw new Exception("No hay suficiente stock para el producto '{$producto->name}'. Stock disponible: {$producto->stock}");
        }

        // Crear el detalle de factura (snapshot)
        InvoiceDetail::create([
            'invoice_id'   => $factura->id,
            'product_id'   => $producto->id,
            'product_name' => $producto->name, // Snapshot
            'unit_price'   => $item['unit_price'],
            'quantity'     => $item['quantity'],
            'subtotal'     => $item['subtotal'],
        ]);

        // Actualizar stock del producto (solo si tiene stock)
        if ($producto->stock !== null) {
            $producto->decrement('stock', $item['quantity']);
        }
    }
}
