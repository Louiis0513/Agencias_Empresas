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
 *
 * Funciones para el carrito de ventas:
 * - verificadorCarrito: consulta disponibilidad (stock actual / variante / serial) vía InventarioService::stockDisponible.
 * - validarGuardadoItemCarrito: valida antes de agregar al carrito vía InventarioService::validarStockDisponible.
 * - buscarProductos: buscador de productos para la vista de ventas (delega en InventarioService), para usar la vista independiente de compras.
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
     * Buscador de productos para la vista de ventas (carrito / selector de producto).
     * Delega en InventarioService::buscarProductosInventario (productos con inventario: simple, serialized, batch).
     * Permite usar la vista de ventas de forma independiente del flujo de compras.
     *
     * @return \Illuminate\Support\Collection Productos con id, name, sku, stock, cost, type
     */
    public function buscarProductos(Store $store, string $term, int $limit = 15): \Illuminate\Support\Collection
    {
        return $this->inventarioService->buscarProductosInventario($store, $term, $limit);
    }

    /**
     * Verificador de disponibilidad para el carrito (solo consulta, no modifica inventario).
     * Delega en InventarioService::stockDisponible para que el selector muestre el máximo correcto:
     * - Simple: stock actual del producto.
     * - Lote (por batch_item_id): stock de ese ítem concreto.
     * - Serializado: disponible o no (por serial_number).
     *
     * @param  int|null  $batchItemId  Para producto por lote (ítem concreto), id del batch_item.
     * @param  string|null  $serialNumber  Para producto serializado, número de serie a consultar.
     * @return array{disponible: bool, cantidad: int, status: string|null}
     */
    public function verificadorCarrito(Store $store, int $productId, ?int $batchItemId = null, ?string $serialNumber = null): array
    {
        return $this->inventarioService->stockDisponible($store, $productId, $batchItemId, $serialNumber);
    }

    /**
     * Verificador de disponibilidad por variante (producto lote): stock total de esa variante en todos los lotes.
     * Delega en InventarioService::stockDisponible con variant_features (caso lote por variante).
     *
     * @param  array  $variantFeatures  Mapa atributo => valor (ej. ['1' => '250ml', '2' => 'Plastico'])
     * @return array{disponible: bool, cantidad: int, status: null}
     */
    public function verificadorCarritoVariante(Store $store, int $productId, array $variantFeatures): array
    {
        return $this->inventarioService->stockDisponible($store, $productId, null, null, $variantFeatures);
    }

    /**
     * Valida que haya stock disponible para los ítems antes de guardarlos en el carrito.
     * Se llama al pulsar "Agregar producto al carrito". Delega en InventarioService::validarStockDisponible.
     *
     * @param  array  $items  Ítems en formato: product_id + quantity | product_id + batch_item_id + quantity | product_id + serial_numbers[]
     * @throws \Exception Si algún producto no tiene stock suficiente o un serial no está disponible
     */
    public function validarGuardadoItemCarrito(Store $store, array $items): void
    {
        $this->inventarioService->validarStockDisponible($store, $items);
    }

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

            // 4. Inventario: descontar según tipo (seriales, variante FIFO, o cantidad FIFO)
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
                    $variantFeatures = $item['variant_features'] ?? null;
                    if (is_array($variantFeatures) && ! empty($variantFeatures)) {
                        $this->inventarioService->registrarSalidaPorVarianteFIFO(
                            $store,
                            $userId,
                            $productId,
                            $variantFeatures,
                            $qty,
                            'Venta a crédito Factura #' . $factura->id
                        );
                    } else {
                        $this->inventarioService->registrarSalidaPorCantidadFIFO(
                            $store,
                            $userId,
                            $productId,
                            $qty,
                            'Venta a crédito Factura #' . $factura->id
                        );
                    }
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
