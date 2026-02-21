<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Invoice;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de Cotizaciones.
 *
 * Crea cotizaciones a partir del carrito de ventas.
 * La cotización guarda: producto + cantidad (identificación por product_id, variant_features o serial_numbers según tipo).
 */
class CotizacionService
{
    public function __construct(
        protected VentaService $ventaService,
        protected InventarioService $inventarioService
    ) {}

    /**
     * Crea una cotización a partir del carrito actual.
     *
     * @param  array  $carrito  Array del carrito (VentasCarrito): cada línea con product_id, type, quantity, name, variant_features?, serial_numbers?, variant_display_name?
     * @param  \Carbon\CarbonInterface|null  $venceAt  Fecha de vencimiento opcional
     * @throws \InvalidArgumentException Si el carrito está vacío
     */
    public function crearDesdeCarrito(Store $store, int $userId, ?int $customerId, string $nota, array $carrito, ?\Carbon\CarbonInterface $venceAt = null): Cotizacion
    {
        if (empty($carrito)) {
            throw new \InvalidArgumentException('El carrito está vacío. Agrega productos antes de guardar la cotización.');
        }

        return DB::transaction(function () use ($store, $userId, $customerId, $nota, $carrito, $venceAt) {
            $cotizacion = Cotizacion::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'nota' => $nota,
                'vence_at' => $venceAt,
            ]);

            foreach ($carrito as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId < 1) {
                    continue;
                }

                $type = $item['type'] ?? 'simple';
                $name = $item['name'] ?? null;
                $quantity = (int) ($item['quantity'] ?? 0);

                $unitPrice = $this->ventaService->verPrecio(
                    $store,
                    $productId,
                    $type,
                    $item['product_variant_id'] ?? null,
                    $item['serial_numbers'] ?? null
                );

                $cotizacion->items()->create([
                    'product_id' => $productId,
                    'type' => $type,
                    'quantity' => max(1, $quantity),
                    'unit_price' => round($unitPrice, 2),
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'variant_features' => $item['variant_features'] ?? null,
                    'serial_numbers' => $item['serial_numbers'] ?? null,
                    'name' => $name,
                    'variant_display_name' => $item['variant_display_name'] ?? null,
                ]);
            }

            return $cotizacion->load('items.product');
        });
    }

    /**
     * Elimina una cotización y sus ítems de forma atómica.
     */
    public function eliminarCotizacion(Cotizacion $cotizacion): void
    {
        DB::transaction(function () use ($cotizacion) {
            $cotizacion->items()->delete();
            $cotizacion->delete();
        });
    }

    /**
     * Obtiene los ítems de la cotización con su precio unitario cotizado y actual.
     * Usa el precio guardado en la cotización (foto al crear) cuando existe;
     * si unit_price es null (cotizaciones antiguas), usa el precio actual vía verPrecio.
     *
     * @return array<int, array{item: \App\Models\CotizacionItem, unit_price: float, unit_price_actual: float, subtotal: float, precio_cambio: bool}>
     */
    public function obtenerItemsConPrecios(Store $store, Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing('items.product');
        $result = [];

        foreach ($cotizacion->items as $item) {
            if ($item->unit_price !== null && $item->unit_price !== '') {
                $unitPrice = (float) $item->unit_price;
            } else {
                $unitPrice = $this->ventaService->verPrecio(
                    $store,
                    $item->product_id,
                    $item->type,
                    $item->product_variant_id ?? null,
                    $item->serial_numbers
                );
            }
            $unitPriceActual = $this->ventaService->verPrecio(
                $store,
                $item->product_id,
                $item->type,
                $item->product_variant_id ?? null,
                $item->serial_numbers
            );
            $quantity = (int) $item->quantity;
            $subtotal = round($unitPrice * $quantity, 2);
            $precioCambio = abs($unitPrice - $unitPriceActual) > 0.005;

            $result[] = [
                'item' => $item,
                'unit_price' => $unitPrice,
                'unit_price_actual' => (float) $unitPriceActual,
                'subtotal' => $subtotal,
                'precio_cambio' => $precioCambio,
            ];
        }

        return $result;
    }

    /**
     * Obtiene el total cotizado y el total actual de una cotización.
     *
     * @return array{total_cotizado: float, total_actual: float}
     */
    public function obtenerTotalesCotizacionYActual(Store $store, Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing('items');
        $preciosActuales = $this->obtenerPreciosActualesEnOrden($store, $cotizacion);
        $totalCotizado = 0.0;
        $totalActual = 0.0;
        foreach ($cotizacion->items as $i => $item) {
            $qty = (int) $item->quantity;
            $precioCotizado = $item->unit_price !== null && $item->unit_price !== ''
                ? (float) $item->unit_price
                : ($preciosActuales[$i] ?? 0);
            $totalCotizado += $precioCotizado * $qty;
            $totalActual += ($preciosActuales[$i] ?? 0) * $qty;
        }

        return [
            'total_cotizado' => round($totalCotizado, 2),
            'total_actual' => round($totalActual, 2),
        ];
    }

    /**
     * Valida estado pre-conversión: ya facturada, vencida.
     *
     * @return array{ya_facturada: bool, vencida: bool, mensaje_vencida: string|null}
     */
    public function validarPreConversion(Cotizacion $cotizacion): array
    {
        $yaFacturada = $cotizacion->estado === Cotizacion::ESTADO_FACTURADA || $cotizacion->invoice_id !== null;
        $vencida = $cotizacion->vence_at && $cotizacion->vence_at->isPast();
        $mensajeVencida = $vencida
            ? 'La cotización está vencida (vencimiento: ' . $cotizacion->vence_at->format('d/m/Y') . '). ¿Desea continuar?'
            : null;

        return [
            'ya_facturada' => $yaFacturada,
            'vencida' => $vencida,
            'mensaje_vencida' => $mensajeVencida,
        ];
    }

    /**
     * Obtiene las discrepancias de precio entre lo cotizado (unit_price del ítem) y el precio actual.
     *
     * @return array<int, array{item_id: int, product_id: int, product_name: string, precio_cotizado: float, precio_actual: float}>
     */
    public function obtenerDiscrepanciasPrecio(Store $store, Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing('items.product');
        $discrepancias = [];

        foreach ($cotizacion->items as $item) {
            $precioCotizado = $item->unit_price !== null ? (float) $item->unit_price : null;
            if ($precioCotizado === null) {
                continue;
            }
            $precioActual = $this->ventaService->verPrecio(
                $store,
                $item->product_id,
                $item->type,
                $item->product_variant_id,
                $item->serial_numbers
            );
            if (abs($precioCotizado - $precioActual) > 0.005) {
                $discrepancias[$item->id] = [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->name ?? $item->product?->name ?? 'Producto #' . $item->product_id,
                    'precio_cotizado' => $precioCotizado,
                    'precio_actual' => $precioActual,
                ];
            }
        }

        return $discrepancias;
    }

    /**
     * Comprueba si los details enviados tienen precios resueltos por ítem (cada unit_price
     * coincide con el precio cotizado o el actual de ese ítem). Orden de details debe coincidir con ítems.
     */
    protected function validarPreciosResueltosPorItem(Store $store, Cotizacion $cotizacion, array $details): bool
    {
        $cotizacion->loadMissing('items');
        $preciosActuales = $this->obtenerPreciosActualesEnOrden($store, $cotizacion);
        $tolerancia = 0.01;
        if (count($details) !== $cotizacion->items->count()) {
            return false;
        }
        foreach ($cotizacion->items as $i => $item) {
            $detail = $details[$i] ?? null;
            if (! $detail || ! isset($detail['unit_price'])) {
                return false;
            }
            $precioEnviado = (float) $detail['unit_price'];
            $precioCotizado = $item->unit_price !== null && $item->unit_price !== '' ? (float) $item->unit_price : ($preciosActuales[$i] ?? 0);
            $precioActual = $preciosActuales[$i] ?? 0;
            $coincideCotizado = abs($precioEnviado - $precioCotizado) <= $tolerancia;
            $coincideActual = abs($precioEnviado - $precioActual) <= $tolerancia;
            if (! $coincideCotizado && ! $coincideActual) {
                return false;
            }
        }
        return true;
    }

    /**
     * Precios cotizados (unit_price de cada ítem) en el mismo orden que los ítems de la cotización.
     *
     * @return array<float>
     */
    public function obtenerPreciosCotizadosEnOrden(Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing('items');
        return $cotizacion->items->map(fn ($item) => (float) ($item->unit_price ?? 0))->all();
    }

    /**
     * Precios actuales (verPrecio) en el mismo orden que los ítems de la cotización.
     *
     * @return array<float>
     */
    public function obtenerPreciosActualesEnOrden(Store $store, Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing('items');
        $prices = [];
        foreach ($cotizacion->items as $item) {
            $prices[] = $this->ventaService->verPrecio(
                $store,
                $item->product_id,
                $item->type,
                $item->product_variant_id,
                $item->serial_numbers
            );
        }
        return $prices;
    }

    /**
     * Convierte una cotización en factura: lock, validaciones, transacción (factura + inventario + caja + actualizar cotización).
     *
     * @param  array  $datosFactura  Mismo formato que VentaService::registrarVenta (customer_id, subtotal, tax, discount, total, status, details, payments / account_receivable)
     * @param  array  $opciones  confirmar_vencida (bool), usar_precio ('cotizado'|'actual')
     * @throws \RuntimeException Si ya facturada, no confirmó vencida, stock insuficiente, precios no elegidos o lock no obtenido
     */
    public function convertirAFactura(Store $store, int $userId, Cotizacion $cotizacion, array $datosFactura, array $opciones = []): Invoice
    {
        $lockKey = 'cotizacion.convertir.' . $cotizacion->id;
        $lock = Cache::lock($lockKey, 30);
        if (! $lock->get()) {
            throw new \RuntimeException('Otro usuario está facturando esta cotización. Intente en unos segundos.');
        }

        try {
            $pre = $this->validarPreConversion($cotizacion);
            if ($pre['ya_facturada']) {
                throw new \RuntimeException('Esta cotización ya fue facturada.');
            }
            if ($pre['vencida'] && ! ($opciones['confirmar_vencida'] ?? false)) {
                throw new \RuntimeException($pre['mensaje_vencida'] ?? 'La cotización está vencida. Confirme para continuar.');
            }

            $details = $datosFactura['details'] ?? [];
            $fallosStock = $this->inventarioService->validarStockDisponibleResult($store, $details);
            if ($fallosStock->isNotEmpty()) {
                $mensajes = $fallosStock->map(fn ($f) => $f['message'])->implode(' ');
                throw new \RuntimeException('Stock insuficiente: ' . $mensajes);
            }

            $discrepancias = $this->obtenerDiscrepanciasPrecio($store, $cotizacion);
            if (! empty($discrepancias)) {
                $usarPrecio = $opciones['usar_precio'] ?? null;
                $preciosResueltosPorItem = $this->validarPreciosResueltosPorItem($store, $cotizacion, $details);
                if (! $preciosResueltosPorItem && $usarPrecio !== 'cotizado' && $usarPrecio !== 'actual') {
                    throw new \RuntimeException('Precios han cambiado; elija mantener precio cotizado o usar precio actual.');
                }
            }

            return DB::transaction(function () use ($store, $userId, $cotizacion, $datosFactura, $opciones, $discrepancias) {
                $factura = $this->ventaService->registrarVenta($store, $userId, $datosFactura);
                $cotizacion->update([
                    'estado' => Cotizacion::ESTADO_FACTURADA,
                    'invoice_id' => $factura->id,
                ]);
                return $factura;
            });
        } finally {
            $lock->release();
        }
    }
}
