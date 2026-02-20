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
     * Crea un activo (1 físico = 1 registro). Serial obligatorio y único por tienda.
     * Registra movimiento tipo ALTA.
     */
    public function crearActivo(Store $store, array $data, ?int $userId = null): Activo
    {
        $serialNumber = trim((string) ($data['serial_number'] ?? ''));
        if ($serialNumber === '') {
            throw new Exception('El número de serie es obligatorio.');
        }

        $exists = Activo::where('store_id', $store->id)->where('serial_number', $serialNumber)->exists();
        if ($exists) {
            throw new Exception("Ya existe un activo con el serial «{$serialNumber}» en esta tienda.");
        }

        return DB::transaction(function () use ($store, $data, $userId, $serialNumber) {
            $unitCost = (float) ($data['unit_cost'] ?? 0);

            $activo = Activo::create([
                'store_id' => $store->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'serial_number' => $serialNumber,
                'model' => $data['model'] ?? null,
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => 1,
                'unit_cost' => $unitCost,
                'location' => $data['location'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'condition' => $data['condition'] ?? Activo::CONDITION_NUEVO,
                'status' => $data['status'] ?? Activo::STATUS_OPERATIVO,
                'warranty_expiry' => $data['warranty_expiry'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($userId) {
                $movDesc = $data['movimiento_description'] ?? $data['description'] ?? 'Alta inicial';
                $this->registrarAlta($store, $activo->id, $userId, $unitCost, $movDesc, $data['purchase_id'] ?? null);
            }

            return $activo->fresh();
        });
    }

    /**
     * Crea un activo desde compra aprobada (mismo flujo que crearActivo, con purchase_id en el movimiento).
     */
    public function crearActivoDesdeCompra(Store $store, array $data, int $userId, ?int $purchaseId = null): Activo
    {
        $data['purchase_id'] = $purchaseId;
        $data['movimiento_description'] = $purchaseId ? "Compra #{$purchaseId}" : 'Alta desde compra';
        return $this->crearActivo($store, $data, $userId);
    }

    /**
     * Actualiza un activo. Si cambia status, valida transición y registra CAMBIO_ESTADO.
     *
     * @param  int|null  $userId  Usuario que realiza el cambio (para historial; si null no se registra CAMBIO_ESTADO)
     */
    public function actualizarActivo(Store $store, int $activoId, array $data, ?int $userId = null): Activo
    {
        $activo = Activo::where('id', $activoId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $nuevoStatus = array_key_exists('status', $data) ? $data['status'] : null;
        if ($nuevoStatus !== null && $nuevoStatus !== $activo->status && ! $activo->puedePasarA($nuevoStatus)) {
            throw new Exception("No se puede cambiar el estado a «{$nuevoStatus}» cuando el activo está en «{$activo->status}».");
        }

        $serialNumber = array_key_exists('serial_number', $data) ? trim((string) $data['serial_number']) : $activo->serial_number;
        if ($serialNumber !== $activo->serial_number) {
            $exists = Activo::where('store_id', $store->id)->where('serial_number', $serialNumber)->where('id', '!=', $activo->id)->exists();
            if ($exists) {
                throw new Exception("Ya existe otro activo con el serial «{$serialNumber}» en esta tienda.");
            }
        }

        $prevStatus = $activo->status;

        $activo->update([
            'name' => $data['name'] ?? $activo->name,
            'code' => array_key_exists('code', $data) ? $data['code'] : $activo->code,
            'serial_number' => $serialNumber,
            'model' => array_key_exists('model', $data) ? $data['model'] : $activo->model,
            'brand' => array_key_exists('brand', $data) ? $data['brand'] : $activo->brand,
            'description' => $data['description'] ?? $activo->description,
            'location' => $data['location'] ?? $activo->location,
            'location_id' => array_key_exists('location_id', $data) ? $data['location_id'] : $activo->location_id,
            'assigned_to_user_id' => array_key_exists('assigned_to_user_id', $data) ? $data['assigned_to_user_id'] : $activo->assigned_to_user_id,
            'condition' => array_key_exists('condition', $data) ? $data['condition'] : $activo->condition,
            'status' => $nuevoStatus ?? $activo->status,
            'warranty_expiry' => array_key_exists('warranty_expiry', $data) ? $data['warranty_expiry'] : $activo->warranty_expiry,
            'purchase_date' => array_key_exists('purchase_date', $data) ? $data['purchase_date'] : $activo->purchase_date,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $activo->is_active,
        ]);

        if ($nuevoStatus !== null && $nuevoStatus !== $prevStatus && $userId) {
            $this->registrarCambioEstado($store, $activo->id, $userId, $prevStatus, $nuevoStatus);
        }

        return $activo->fresh();
    }

    /**
     * Registra movimiento de alta (1 unidad).
     */
    public function registrarAlta(Store $store, int $activoId, int $userId, float $unitCost, string $description = 'Alta', ?int $purchaseId = null): MovimientoActivo
    {
        return MovimientoActivo::create([
            'store_id' => $store->id,
            'user_id' => $userId,
            'activo_id' => $activoId,
            'purchase_id' => $purchaseId,
            'type' => MovimientoActivo::TYPE_ALTA,
            'quantity' => 1,
            'unit_cost' => $unitCost,
            'description' => $description,
        ]);
    }

    /**
     * Registra movimiento de baja.
     */
    public function registrarBaja(Store $store, int $activoId, int $userId, string $description = 'Baja de activo'): MovimientoActivo
    {
        return MovimientoActivo::create([
            'store_id' => $store->id,
            'user_id' => $userId,
            'activo_id' => $activoId,
            'type' => MovimientoActivo::TYPE_BAJA,
            'quantity' => 1,
            'description' => $description,
        ]);
    }

    /**
     * Registra cambio de estado (lifecycle) en el historial.
     */
    public function registrarCambioEstado(Store $store, int $activoId, int $userId, string $estadoAnterior, string $estadoNuevo): MovimientoActivo
    {
        return MovimientoActivo::create([
            'store_id' => $store->id,
            'user_id' => $userId,
            'activo_id' => $activoId,
            'type' => MovimientoActivo::TYPE_CAMBIO_ESTADO,
            'quantity' => null,
            'description' => "Estado: {$estadoAnterior} → {$estadoNuevo}",
            'metadata' => ['from' => $estadoAnterior, 'to' => $estadoNuevo],
        ]);
    }

    /**
     * Actualiza activo tras aprobar compra: antigüedad (fecha factura) y condición NUEVO.
     */
    public function actualizarActivoDesdeCompra(Store $store, int $activoId, $purchaseDate): Activo
    {
        $activo = Activo::where('id', $activoId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $updates = ['condition' => Activo::CONDITION_NUEVO];
        if ($purchaseDate) {
            $updates['purchase_date'] = $purchaseDate instanceof \Carbon\Carbon ? $purchaseDate : \Carbon\Carbon::parse($purchaseDate);
        }
        $activo->update($updates);

        return $activo->fresh();
    }

    /**
     * Lista movimientos de activos con filtros (historial global o por activo).
     */
    public function listarMovimientos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = MovimientoActivo::deTienda($store->id)
            ->with(['activo:id,store_id,name,code,serial_number,unit_cost', 'user:id,name', 'purchase:id,invoice_number'])
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
     * Activos de la tienda para selector (ej. en vista movimientos o filtros).
     */
    public function activosParaMovimientos(Store $store): \Illuminate\Database\Eloquent\Collection
    {
        return Activo::where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'code', 'serial_number', 'unit_cost', 'location']);
    }

    /**
     * Lista activos con filtros.
     */
    public function listarActivos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = Activo::deTienda($store->id)
            ->with(['locationRelation:id,name', 'assignedTo:id,name'])
            ->orderBy('name');

        if (! empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }
        if (isset($filtros['is_active'])) {
            $query->where('is_active', (bool) $filtros['is_active']);
        }
        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Busca activos por término.
     */
    public function buscarActivos(Store $store, string $term, int $limit = 15): \Illuminate\Support\Collection
    {
        $query = Activo::deTienda($store->id)->activos();

        if (strlen(trim($term)) >= 2) {
            $query->buscar(trim($term));
        }

        return $query->orderBy('name')->limit($limit)->get(['id', 'name', 'code', 'serial_number', 'unit_cost', 'location']);
    }

    /**
     * Busca activos para añadir a una compra (referencia nombre/marca/modelo; al aprobar se crean N activos con seriales).
     */
    public function buscarActivosParaCompra(Store $store, string $term, int $limit = 25): \Illuminate\Support\Collection
    {
        $query = Activo::deTienda($store->id)->activos();

        if (strlen(trim($term)) >= 2) {
            $query->buscar(trim($term));
        }

        return $query->orderBy('name')->limit($limit)->get(['id', 'name', 'code', 'serial_number', 'unit_cost', 'location', 'model', 'brand']);
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
     * Da de baja un activo: status → DADO_DE_BAJA y registra movimiento BAJA.
     * No se puede dar de baja si está EN_REPARACION o EN_PRESTAMO.
     */
    public function darDeBaja(Store $store, int $activoId, int $userId, ?string $motivo = null): Activo
    {
        return DB::transaction(function () use ($store, $activoId, $userId, $motivo) {
            $activo = Activo::where('id', $activoId)
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($activo->status === Activo::STATUS_DADO_DE_BAJA) {
                throw new Exception("El activo «{$activo->name}» ya está dado de baja.");
            }

            if (! $activo->puedePasarA(Activo::STATUS_DADO_DE_BAJA)) {
                throw new Exception("No se puede dar de baja el activo en estado «{$activo->status}». Devuélvelo de reparación o préstamo antes.");
            }

            $this->registrarBaja($store, $activo->id, $userId, $motivo ? "Baja: {$motivo}" : 'Baja de activo');
            $activo->update(['status' => Activo::STATUS_DADO_DE_BAJA]);

            return $activo->fresh();
        });
    }

    /**
     * Elimina un activo. Solo permitido en estados finales (DADO_DE_BAJA, VENDIDO).
     */
    public function eliminarActivo(Store $store, int $activoId): void
    {
        $activo = Activo::where('id', $activoId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $estadosFinales = [Activo::STATUS_DADO_DE_BAJA, Activo::STATUS_VENDIDO];
        if (! in_array($activo->status, $estadosFinales, true)) {
            throw new Exception('Solo se puede eliminar un activo dado de baja o vendido.');
        }

        $activo->delete();
    }
}
