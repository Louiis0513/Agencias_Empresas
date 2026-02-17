<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Store;
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
        protected VentaService $ventaService
    ) {}

    /**
     * Crea una cotización a partir del carrito actual.
     *
     * @param  array  $carrito  Array del carrito (VentasCarrito): cada línea con product_id, type, quantity, name, variant_features?, serial_numbers?, variant_display_name?
     * @throws \InvalidArgumentException Si el carrito está vacío
     */
    public function crearDesdeCarrito(Store $store, int $userId, ?int $customerId, string $nota, array $carrito): Cotizacion
    {
        if (empty($carrito)) {
            throw new \InvalidArgumentException('El carrito está vacío. Agrega productos antes de guardar la cotización.');
        }

        return DB::transaction(function () use ($store, $userId, $customerId, $nota, $carrito) {
            $cotizacion = Cotizacion::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'nota' => $nota,
            ]);

            foreach ($carrito as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId < 1) {
                    continue;
                }

                $type = $item['type'] ?? 'simple';
                $name = $item['name'] ?? null;
                $quantity = (int) ($item['quantity'] ?? 0);

                $cotizacion->items()->create([
                    'product_id' => $productId,
                    'type' => $type,
                    'quantity' => max(1, $quantity),
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
     * Obtiene los ítems de la cotización enriquecidos con precio unitario actual.
     * Todo precio se obtiene vía VentaService::verPrecio (centralizado).
     *
     * @return array<int, array{item: \App\Models\CotizacionItem, unit_price: float, subtotal: float}>
     */
    public function obtenerItemsConPrecios(Store $store, Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing('items.product');
        $result = [];

        foreach ($cotizacion->items as $item) {
            $unitPrice = $this->ventaService->verPrecio(
                $store,
                $item->product_id,
                $item->type,
                $item->product_variant_id ?? null,
                $item->serial_numbers
            );
            $quantity = (int) $item->quantity;
            $subtotal = round($unitPrice * $quantity, 2);

            $result[] = [
                'item' => $item,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        return $result;
    }
}
