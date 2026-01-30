<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\MovimientoActivo;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ActivoService
{
    /**
     * Crea un activo en el catálogo.
     * Si quantity > 0, registra una ENTRADA "Alta inicial" para trazabilidad.
     */
    public function crearActivo(Store $store, array $data, ?int $userId = null): Activo
    {
        return DB::transaction(function () use ($store, $data, $userId) {
            $quantity = (int) ($data['quantity'] ?? 0);
            $unitCost = (float) ($data['unit_cost'] ?? 0);

            $activo = Activo::create([
                'store_id' => $store->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => 0,
                'unit_cost' => 0,
                'location' => $data['location'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($quantity > 0 && $userId) {
                $this->registrarMovimiento($store, $userId, [
                    'activo_id' => $activo->id,
                    'type' => MovimientoActivo::TYPE_ENTRADA,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'description' => 'Alta inicial',
                ]);
            } elseif ($quantity > 0) {
                // Sin userId (creación manual antigua): actualizar directo para compatibilidad
                $activo->update([
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ]);
            }

            return $activo->fresh();
        });
    }

    /**
     * Actualiza un activo (nombre, código, etc.).
     * quantity y unit_cost son derivados de movimientos, no se editan aquí.
     */
    public function actualizarActivo(Store $store, int $activoId, array $data): Activo
    {
        $activo = Activo::where('id', $activoId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $activo->update([
            'name' => $data['name'] ?? $activo->name,
            'code' => $data['code'] ?? $activo->code,
            'description' => $data['description'] ?? $activo->description,
            'location' => $data['location'] ?? $activo->location,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $activo->is_active,
        ]);

        return $activo->fresh();
    }

    /**
     * Registra un movimiento de activo (ENTRADA o SALIDA) y actualiza cantidad/costo.
     * Crea el registro en movimientos_activo para trazabilidad completa.
     */
    public function registrarMovimiento(Store $store, int $userId, array $datos): MovimientoActivo
    {
        return DB::transaction(function () use ($store, $userId, $datos) {
            $activo = Activo::where('id', $datos['activo_id'])
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();

            $type = $datos['type'];
            $quantity = (int) $datos['quantity'];
            if ($quantity < 1) {
                throw new Exception('La cantidad debe ser al menos 1.');
            }

            if ($type === MovimientoActivo::TYPE_SALIDA) {
                if ($activo->quantity < $quantity) {
                    throw new Exception(
                        "Cantidad insuficiente en «{$activo->name}». Actual: {$activo->quantity}, solicitado: {$quantity}."
                    );
                }
                $activo->quantity -= $quantity;
            } else {
                $unitCost = isset($datos['unit_cost']) ? (float) $datos['unit_cost'] : 0;
                $oldQty = (int) $activo->quantity;
                $oldCost = (float) $activo->unit_cost;

                if ($oldQty <= 0) {
                    $activo->unit_cost = $unitCost;
                } else {
                    $activo->unit_cost = ($oldQty * $oldCost + $quantity * $unitCost) / ($oldQty + $quantity);
                }
                $activo->quantity += $quantity;
            }

            $activo->save();

            $mov = MovimientoActivo::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'activo_id' => $activo->id,
                'purchase_id' => $datos['purchase_id'] ?? null,
                'type' => $type,
                'quantity' => $quantity,
                'description' => $datos['description'] ?? null,
                'unit_cost' => isset($datos['unit_cost']) ? (float) $datos['unit_cost'] : null,
            ]);

            return $mov;
        });
    }

    /**
     * Suma cantidad a un activo (cuando se recibe de una compra aprobada).
     * Crea movimiento para trazabilidad: "4 sillas compradas en enero a $50".
     */
    public function registrarEntrada(Store $store, int $activoId, int $quantity, float $unitCost, ?int $userId = null, ?int $purchaseId = null, string $description = ''): Activo
    {
        if (! $userId) {
            throw new Exception('Se requiere user_id para registrar entradas de activos.');
        }

        $this->registrarMovimiento($store, $userId, [
            'activo_id' => $activoId,
            'type' => MovimientoActivo::TYPE_ENTRADA,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'description' => $description ?: ($purchaseId ? "Compra #{$purchaseId}" : 'Entrada'),
            'purchase_id' => $purchaseId,
        ]);

        return Activo::where('id', $activoId)->where('store_id', $store->id)->firstOrFail()->fresh();
    }

    /**
     * Lista movimientos de activos con filtros.
     */
    public function listarMovimientos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = MovimientoActivo::deTienda($store->id)
            ->with(['activo:id,store_id,name,code,quantity,unit_cost', 'user:id,name', 'purchase:id,invoice_number'])
            ->orderByDesc('created_at');

        if (! empty($filtros['activo_id'])) {
            $query->porActivo((int) $filtros['activo_id']);
        }
        if (! empty($filtros['type'])) {
            $query->porTipo($filtros['type']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Activos de la tienda (para selector en movimientos).
     */
    public function activosParaMovimientos(Store $store): \Illuminate\Database\Eloquent\Collection
    {
        return Activo::where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'code', 'quantity', 'unit_cost', 'location']);
    }

    /**
     * Lista activos con filtros.
     */
    public function listarActivos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = Activo::deTienda($store->id)->orderBy('name');

        if (! empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }
        if (isset($filtros['is_active'])) {
            $query->where('is_active', (bool) $filtros['is_active']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Busca activos por término (para buscador en compras).
     */
    public function buscarActivos(Store $store, string $term, int $limit = 15): \Illuminate\Support\Collection
    {
        $query = Activo::deTienda($store->id)->activos();

        if (strlen(trim($term)) >= 2) {
            $query->buscar(trim($term));
        }

        return $query->orderBy('name')->limit($limit)->get(['id', 'name', 'code', 'quantity', 'unit_cost', 'location']);
    }

    /**
     * Obtiene un activo por ID.
     */
    public function obtenerActivo(Store $store, int $activoId): Activo
    {
        return Activo::where('id', $activoId)
            ->where('store_id', $store->id)
            ->firstOrFail();
    }

    /**
     * Elimina un activo (solo si quantity = 0).
     */
    public function eliminarActivo(Store $store, int $activoId): void
    {
        $activo = Activo::where('id', $activoId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($activo->quantity > 0) {
            throw new Exception('No puedes eliminar un activo con cantidad mayor a cero. Registra salidas primero.');
        }

        $activo->delete();
    }
}
