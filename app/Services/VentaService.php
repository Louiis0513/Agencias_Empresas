<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de Venta (flujo de venta al cliente).
 *
 * Orquesta el proceso completo de una venta para que sea entendible y mantenible.
 * Centraliza qué servicios intervienen y en qué orden.
 *
 * Servicios que participan en el flujo de venta:
 *
 * 1. InvoiceService
 *    - Crear la factura (cabecera + detalles).
 *    - Para crédito: crear solo cabecera + detalles (sin inventario ni cuenta).
 *
 * 2. InventarioService
 *    - Validar stock disponible antes de comprometer (venta a crédito).
 *    - Registrar salida de stock (FIFO) por cada línea.
 *
 * 3. AccountReceivableService
 *    - Crear cuenta por cobrar y cuotas a partir de la factura PENDING.
 *
 * 4. CajaService
 *    - Solo si la venta es de contado (PAID): registrar ingreso(s) en bolsillo(s).
 */
class VentaService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected InventarioService $inventarioService,
        protected CajaService $cajaService,
        protected AccountReceivableService $accountReceivableService
    ) {}

    /**
     * Venta a crédito: valida stock → crea factura PENDING → crea cuenta por cobrar (cuotas) → descuenta inventario.
     * Todo dentro de una transacción.
     *
     * @param  array  $datos  customer_id, subtotal, tax, discount, total, details, account_receivable (due_date?, cuotas: [{ amount, due_date }])
     * @return Invoice
     */
    public function ventaACredito(Store $store, int $userId, array $datos): Invoice
    {
        return DB::transaction(function () use ($store, $userId, $datos) {
            $details = $datos['details'] ?? [];
            $arData = $datos['account_receivable'] ?? [];
            $cuotas = $arData['cuotas'] ?? [];
            $dueDate = $arData['due_date'] ?? null;

            // 1. Inventario: validar que hay stock de todos los productos de la venta
            $this->inventarioService->validarStockDisponible($store, $details);

            // 2. Facturación: crear la factura en estado PENDING (solo cabecera + detalles, sin tocar inventario ni caja)
            $factura = $this->invoiceService->crearFacturaPendienteSoloCabeceraYDetalles($store, $userId, $datos);

            // 3. Cuentas por cobrar: crear la cuenta vinculada a la factura y las cuotas (valida que suma cuotas = total)
            $this->accountReceivableService->crearDesdeFactura($store, $factura, $dueDate, $cuotas);

            // 4. Inventario: descontar según tipo (seriales elegidos o FIFO por cantidad)
            foreach ($details as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId < 1) {
                    continue;
                }
                $serialNumbers = $item['serial_numbers'] ?? null;
                if (is_array($serialNumbers) && ! empty($serialNumbers)) {
                    $this->inventarioService->registrarSalidaPorSeriales(
                        $store,
                        $userId,
                        $productId,
                        $serialNumbers,
                        'Venta a crédito Factura #' . $factura->id
                    );
                } else {
                    $qty = (int) ($item['quantity'] ?? 0);
                    if ($qty < 1) {
                        continue;
                    }
                    $this->inventarioService->registrarSalidaPorCantidadFIFO(
                        $store,
                        $userId,
                        $productId,
                        $qty,
                        'Venta a crédito Factura #' . $factura->id
                    );
                }
            }

            return $factura->fresh()->load(['details.product', 'customer', 'user', 'accountReceivable.cuotas']);
        });
    }

    /**
     * Registra una venta (factura + inventario + caja si aplica).
     * Contado: factura PAID + inventario + caja. Crédito: usar ventaACredito().
     *
     * @param  array  $datos  customer_id, subtotal, tax, discount, total, status, details, payments (si PAID)
     * @return Invoice
     */
    public function registrarVenta(Store $store, int $userId, array $datos): Invoice
    {
        return $this->invoiceService->crearFactura($store, $userId, $datos);
    }
}
