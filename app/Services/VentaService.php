<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use Carbon\Carbon;
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
        protected AccountReceivableService $accountReceivableService,
        protected ComprobanteIngresoService $comprobanteIngresoService,
        protected SubscriptionService $subscriptionService
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
     * Delega en InventarioService::stockDisponible con product_variant_id.
     *
     * @param  int  $productVariantId  ID de la variante en product_variants
     * @return array{disponible: bool, cantidad: int, status: null}
     */
    public function verificadorCarritoVariante(Store $store, int $productId, int $productVariantId): array
    {
        return $this->inventarioService->stockDisponible($store, $productId, null, null, $productVariantId);
    }

    /**
     * Retorna el precio unitario de venta del ítem seleccionado.
     * Centraliza la lógica de precios: todo precio mostrado en el carrito y cotizaciones debe obtenerse aquí.
     * Delega en InventarioService::precioParaItem.
     *
     * @param  int|null  $productVariantId  Para batch: ID de la variante
     * @param  array|null  $serialNumbers  Para serialized: lista de números de serie
     * @return float Precio unitario
     */
    public function verPrecio(Store $store, int $productId, string $type, ?int $productVariantId = null, ?array $serialNumbers = null): float
    {
        return $this->inventarioService->precioParaItem($store, $productId, $type, $productVariantId, $serialNumbers);
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
     * Separa los detalles en líneas de producto (inventario) y líneas de suscripción.
     *
     * @return array{0: array, 1: array} [productDetails, subscriptionDetails]
     */
    private function separarDetallesProductoSuscripcion(array $details): array
    {
        $productDetails = [];
        $subscriptionDetails = [];
        foreach ($details as $item) {
            if (! empty($item['store_plan_id']) && (int) $item['store_plan_id'] > 0) {
                $subscriptionDetails[] = $item;
            } else {
                $productDetails[] = $item;
            }
        }
        return [$productDetails, $subscriptionDetails];
    }

    /**
     * Venta a crédito: valida stock → crea factura PENDING → crea cuenta por cobrar (cuotas) → descuenta inventario → crea suscripciones.
     * Todo dentro de una transacción.
     *
     * @param  array  $datos  customer_id, subtotal, tax, discount, total, details, account_receivable (due_date?, cuotas: [{ amount, due_date }])
     * @return Invoice
     */
    public function ventaACredito(Store $store, int $userId, array $datos): Invoice
    {
        return DB::transaction(function () use ($store, $userId, $datos) {
            $details = $datos['details'] ?? [];
            [$productDetails, $subscriptionDetails] = $this->separarDetallesProductoSuscripcion($details);
            $arData = $datos['account_receivable'] ?? [];
            $cuotas = $arData['cuotas'] ?? [];
            $dueDate = $arData['due_date'] ?? null;

            // 1. Inventario: validar stock solo de líneas producto
            if (! empty($productDetails)) {
                $this->inventarioService->validarStockDisponible($store, $productDetails);
            }

            // 2. Facturación: crear la factura en estado PENDING (cabecera + todos los detalles)
            $factura = $this->invoiceService->crearFacturaPendienteSoloCabeceraYDetalles($store, $userId, $datos);

            // 3. Cuentas por cobrar: crear la cuenta vinculada a la factura y las cuotas (valida que suma cuotas = total)
            $this->accountReceivableService->crearDesdeFactura($store, $factura, $dueDate, $cuotas);

            // 4. Inventario: descontar solo líneas producto (las de suscripción no tienen product_id y se omiten en el loop)
            foreach ($details as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId < 1) {
                    continue;
                }
                $product = Product::where('id', $productId)->where('store_id', $store->id)->first();
                $descBase = 'Venta a crédito Factura #' . $factura->id . ' - ' . ($product ? $product->name : 'Producto #' . $productId);

                $serialNumbers = $item['serial_numbers'] ?? null;
                if (is_array($serialNumbers) && ! empty($serialNumbers)) {
                    $serialPart = $this->formatSerialDescriptionsForMovement($product, $store->id, $serialNumbers);
                    $description = $descBase . ' - ' . $serialPart;
                    $this->inventarioService->registrarSalidaPorSeriales(
                        $store,
                        $userId,
                        $productId,
                        $serialNumbers,
                        $description
                    );
                } else {
                    $qty = (int) ($item['quantity'] ?? 0);
                    if ($qty < 1) {
                        continue;
                    }
                    $productVariantId = $item['product_variant_id'] ?? null;
                    if ($productVariantId) {
                        $variant = ProductVariant::with('product.category.attributes')
                            ->where('id', (int) $productVariantId)
                            ->where('product_id', $productId)
                            ->first();
                        $description = $descBase . ($variant ? ' - ' . $variant->display_name : '');
                        $this->inventarioService->registrarSalidaPorVarianteFIFO(
                            $store,
                            $userId,
                            $productId,
                            (int) $productVariantId,
                            $qty,
                            $description
                        );
                    } else {
                        $this->inventarioService->registrarSalidaPorCantidadFIFO(
                            $store,
                            $userId,
                            $productId,
                            $qty,
                            $descBase
                        );
                    }
                }
            }

            // 5. Crear suscripciones por cada línea de tipo suscripción (valida solapamiento en SubscriptionService)
            foreach ($subscriptionDetails as $item) {
                $this->subscriptionService->createSubscription(
                    $store,
                    (int) $factura->customer_id,
                    (int) $item['store_plan_id'],
                    Carbon::parse($item['subscription_starts_at'])
                );
            }

            return $factura->fresh()->load(['details.product', 'details.storePlan', 'customer', 'user', 'accountReceivable.cuotas']);
        });
    }

    /**
     * Venta al contado: valida stock → crea factura PAID (solo cabecera + detalles) → descuenta inventario → crea comprobante de ingreso (PAGO_FACTURA) → crea suscripciones.
     *
     * @param  array  $datos  customer_id, subtotal, tax, discount, total, details, destinos [ ['bolsillo_id' => int, 'amount' => float], ... ]
     * @return Invoice
     */
    public function registrarVentaContado(Store $store, int $userId, array $datos): Invoice
    {
        return DB::transaction(function () use ($store, $userId, $datos) {
            $details = $datos['details'] ?? [];
            [$productDetails, $subscriptionDetails] = $this->separarDetallesProductoSuscripcion($details);
            $destinos = $datos['destinos'] ?? [];

            if (empty($destinos)) {
                throw new \Exception('Venta al contado requiere al menos un destino (bolsillo y monto).');
            }

            // 1. Validar stock solo de líneas producto
            if (! empty($productDetails)) {
                $this->inventarioService->validarStockDisponible($store, $productDetails);
            }

            // 2. Factura PAID solo cabecera + detalles (método de pago derivado de bolsillos)
            $datosFactura = $datos;
            $datosFactura['status'] = 'PAID';
            $bolsilloIds = array_values(array_map(fn ($d) => (int) ($d['bolsillo_id'] ?? 0), $destinos));
            $datosFactura['payment_method'] = $this->invoiceService->derivarMetodoPagoDesdeBolsillos($store, $bolsilloIds) ?? 'CASH';
            $factura = $this->invoiceService->crearFacturaSoloCabeceraYDetalles($store, $userId, $datosFactura);

            // 3. Descontar inventario
            foreach ($details as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId < 1) {
                    continue;
                }
                $product = Product::where('id', $productId)->where('store_id', $store->id)->first();
                $descBase = 'Venta Factura #' . $factura->id . ' - ' . ($product ? $product->name : 'Producto #' . $productId);

                $serialNumbers = $item['serial_numbers'] ?? null;
                if (is_array($serialNumbers) && ! empty($serialNumbers)) {
                    $serialPart = $this->formatSerialDescriptionsForMovement($product, $store->id, $serialNumbers);
                    $description = $descBase . ' - ' . $serialPart;
                    $this->inventarioService->registrarSalidaPorSeriales(
                        $store,
                        $userId,
                        $productId,
                        $serialNumbers,
                        $description
                    );
                } else {
                    $qty = (int) ($item['quantity'] ?? 0);
                    if ($qty < 1) {
                        continue;
                    }
                    $productVariantId = $item['product_variant_id'] ?? null;
                    if ($productVariantId) {
                        $variant = ProductVariant::with('product.category.attributes')
                            ->where('id', (int) $productVariantId)
                            ->where('product_id', $productId)
                            ->first();
                        $description = $descBase . ($variant ? ' - ' . $variant->display_name : '');
                        $this->inventarioService->registrarSalidaPorVarianteFIFO(
                            $store,
                            $userId,
                            $productId,
                            (int) $productVariantId,
                            $qty,
                            $description
                        );
                    } else {
                        $this->inventarioService->registrarSalidaPorCantidadFIFO(
                            $store,
                            $userId,
                            $productId,
                            $qty,
                            $descBase
                        );
                    }
                }
            }

            // 4. Comprobante de ingreso (PAGO_FACTURA) con destinos
            $this->comprobanteIngresoService->crearComprobante($store, $userId, [
                'invoice_id' => $factura->id,
                'notes' => 'Pago Factura #' . $factura->id,
                'destinos' => array_map(fn ($d) => [
                    'bolsillo_id' => (int) ($d['bolsillo_id'] ?? 0),
                    'amount' => (float) ($d['amount'] ?? 0),
                    'reference' => $d['reference'] ?? null,
                ], $destinos),
            ]);

            // 5. Crear suscripciones por cada línea de tipo suscripción (valida solapamiento en SubscriptionService)
            foreach ($subscriptionDetails as $item) {
                $this->subscriptionService->createSubscription(
                    $store,
                    (int) $factura->customer_id,
                    (int) $item['store_plan_id'],
                    Carbon::parse($item['subscription_starts_at'])
                );
            }

            return $factura->fresh()->load(['details.product', 'details.storePlan', 'customer', 'user']);
        });
    }

    /**
     * Registra una venta: orquesta factura + inventario + comprobante (contado) o cuenta por cobrar (crédito).
     *
     * @param  array  $datos  customer_id, subtotal, tax, discount, total, status ('PAID'|'PENDING'),
     *                        details, payments (si PAID: [ bolsillo_id, amount, payment_method? ]), account_receivable (si PENDING)
     * @return Invoice
     */
    public function registrarVenta(Store $store, int $userId, array $datos): Invoice
    {
        $status = $datos['status'] ?? 'PAID';

        if ($status === 'PAID') {
            $payments = $datos['payments'] ?? [];
            $destinos = array_values(array_map(fn ($p) => [
                'bolsillo_id' => (int) ($p['bolsillo_id'] ?? 0),
                'amount' => (float) ($p['amount'] ?? 0),
                'reference' => $p['reference'] ?? null,
            ], array_filter($payments, fn ($p) => ((float) ($p['amount'] ?? 0)) > 0)));
            $datos['destinos'] = $destinos;
            return $this->registrarVentaContado($store, $userId, $datos);
        }

        return $this->ventaACredito($store, $userId, $datos);
    }

    /**
     * Construye la descripción de seriales con atributos para movimientos de salida.
     * Ej: "Serial: X (Marca: Y, Sabor: Z); Serial: W (Marca: Y2)"
     *
     * @param  Product|null  $product
     * @param  int  $storeId
     * @param  array  $serialNumbers
     * @return string
     */
    private function formatSerialDescriptionsForMovement(?Product $product, int $storeId, array $serialNumbers): string
    {
        if (! $product) {
            return 'Serial: ' . implode(', ', $serialNumbers);
        }
        $product->load('category.attributes');
        $attrNames = $product->category
            ? $product->category->attributes->pluck('name', 'id')->all()
            : [];

        $items = ProductItem::where('product_id', $product->id)
            ->where('store_id', $storeId)
            ->whereIn('serial_number', $serialNumbers)
            ->get();

        $parts = [];
        foreach ($serialNumbers as $sn) {
            $item = $items->firstWhere('serial_number', $sn);
            $featStr = $item
                ? ProductVariant::formatFeaturesWithAttributeNames($item->features ?? [], $attrNames)
                : '';
            $parts[] = $featStr !== ''
                ? "Serial: {$sn} ({$featStr})"
                : "Serial: {$sn}";
        }
        return implode('; ', $parts);
    }
}
