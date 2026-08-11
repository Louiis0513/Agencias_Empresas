<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use Exception;

class InventarioService
{
    /**
     * Registra un movimiento de inventario (ledger físico).
     *
     * @param  array{
     *   product_id: int,
     *   bodega_id?: int|null,
     *   fecha: string|\DateTimeInterface,
     *   clase_movimiento: string,
     *   direccion: string,
     *   cantidad: float|int|string,
     *   costo_unitario_entrada?: float|int|string|null,
     *   valor_entrada?: float|int|string|null,
     *   documento?: \Illuminate\Database\Eloquent\Model|null,
     *   documento_etiqueta?: string|null,
     *   descripcion?: string|null
     * }  $datos
     */
    public function registrarMovimiento(Store $store, int $userId, array $datos): MovimientoInventario
    {
        $productId = (int) ($datos['product_id'] ?? 0);
        $product = Product::query()
            ->where('store_id', $store->id)
            ->whereKey($productId)
            ->first();

        if (! $product) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        if (! $product->es_inventariable) {
            throw new Exception('El producto «'.$product->codigo.'» no es inventariable.');
        }

        $direccion = strtoupper(trim((string) ($datos['direccion'] ?? '')));
        if (! in_array($direccion, [MovimientoInventario::DIRECCION_ENTRADA, MovimientoInventario::DIRECCION_SALIDA], true)) {
            throw new Exception('La dirección del movimiento debe ser ENTRADA o SALIDA.');
        }

        $cantidad = round((float) ($datos['cantidad'] ?? 0), 4);
        if ($cantidad <= 0) {
            throw new Exception('La cantidad del movimiento debe ser mayor a 0.');
        }

        $bodegaId = $datos['bodega_id'] ?? null;
        if ($store->maneja_bodegas) {
            if (! $bodegaId) {
                throw new Exception('Debe indicar la bodega cuando la tienda maneja bodegas.');
            }
            $bodega = Bodega::query()->deStore($store)->whereKey((int) $bodegaId)->first();
            if (! $bodega) {
                throw new Exception('La bodega no pertenece a esta tienda.');
            }
            if (! $bodega->activo) {
                throw new Exception('La bodega «'.$bodega->codigo.'» está inactiva.');
            }
            $bodegaId = $bodega->id;
        } else {
            $bodegaId = null;
        }

        $costoUnitario = null;
        $valorEntrada = null;
        if ($direccion === MovimientoInventario::DIRECCION_ENTRADA) {
            $costoUnitario = isset($datos['costo_unitario_entrada'])
                ? round((float) $datos['costo_unitario_entrada'], 4)
                : null;
            $valorEntrada = isset($datos['valor_entrada'])
                ? round((float) $datos['valor_entrada'], 2)
                : null;

            if ($costoUnitario !== null && $costoUnitario < 0) {
                throw new Exception('El costo unitario de entrada no puede ser negativo.');
            }
            if ($valorEntrada === null && $costoUnitario !== null) {
                $valorEntrada = round($cantidad * $costoUnitario, 2);
            }
        }

        $documento = $datos['documento'] ?? null;

        return MovimientoInventario::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'bodega_id' => $bodegaId,
            'fecha' => $datos['fecha'],
            'clase_movimiento' => (string) ($datos['clase_movimiento'] ?? ''),
            'direccion' => $direccion,
            'cantidad' => $cantidad,
            'costo_unitario_entrada' => $costoUnitario,
            'valor_entrada' => $valorEntrada,
            'documento_type' => $documento ? $documento::class : null,
            'documento_id' => $documento?->getKey(),
            'documento_etiqueta' => $datos['documento_etiqueta'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'user_id' => $userId,
        ]);
    }

    public function tieneMovimientosBodega(Bodega $bodega): bool
    {
        return MovimientoInventario::query()
            ->where('store_id', $bodega->store_id)
            ->where('bodega_id', $bodega->id)
            ->exists();
    }
}
