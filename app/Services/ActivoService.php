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

            $controlType = $data['control_type'] ?? Activo::CONTROL_LOTE;
            $isSerializado = $controlType === Activo::CONTROL_SERIALIZADO;

            $serialNumber = trim($data['serial_number'] ?? '');
            if ($isSerializado && $quantity === 1) {
                if (empty($serialNumber)) {
                    throw new Exception('Para crear 1 unidad serializada debes indicar el número de serie.');
                }
            }
            if ($isSerializado && $quantity === 0 && ! empty($serialNumber)) {
                // Activo "listo" para compra: tiene serial, qty 0. Al aprobar compra se registrará entrada.
            }
            if ($isSerializado && $quantity > 1) {
                throw new Exception('Serializado: solo puedes crear 0 (catálogo/listo) o 1 (unidad única). Para más unidades, da de alta desde la compra.');
            }

            $initialQty = $isSerializado && $quantity === 1 ? 1 : 0;
            $initialCost = $isSerializado && $quantity === 1 ? $unitCost : 0;

            $activo = Activo::create([
                'store_id' => $store->id,
                'control_type' => $controlType,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'serial_number' => $isSerializado && ! empty($serialNumber) ? $serialNumber : null,
                'model' => $data['model'] ?? null,
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'] ?? null,
                'quantity' => $initialQty,
                'unit_cost' => $initialCost,
                'location' => $data['location'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'condition' => $data['condition'] ?? null,
                'status' => $data['status'] ?? Activo::STATUS_ACTIVO,
                'warranty_expiry' => $data['warranty_expiry'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($quantity > 0 && $userId && ! $isSerializado) {
                $this->registrarMovimiento($store, $userId, [
                    'activo_id' => $activo->id,
                    'type' => MovimientoActivo::TYPE_ENTRADA,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'description' => 'Alta inicial',
                ]);
            } elseif ($quantity > 0 && ! $isSerializado) {
                $activo->update(['quantity' => $quantity, 'unit_cost' => $unitCost]);
            }
            if ($isSerializado && $quantity === 1 && $userId) {
                MovimientoActivo::create([
                    'store_id' => $store->id,
                    'user_id' => $userId,
                    'activo_id' => $activo->id,
                    'type' => MovimientoActivo::TYPE_ENTRADA,
                    'quantity' => 1,
                    'unit_cost' => $unitCost,
                    'description' => 'Alta inicial (unidad única)',
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
            'serial_number' => array_key_exists('serial_number', $data) ? $data['serial_number'] : $activo->serial_number,
            'model' => array_key_exists('model', $data) ? $data['model'] : $activo->model,
            'brand' => array_key_exists('brand', $data) ? $data['brand'] : $activo->brand,
            'description' => $data['description'] ?? $activo->description,
            'location' => $data['location'] ?? $activo->location,
            'location_id' => array_key_exists('location_id', $data) ? $data['location_id'] : $activo->location_id,
            'assigned_to_user_id' => array_key_exists('assigned_to_user_id', $data) ? $data['assigned_to_user_id'] : $activo->assigned_to_user_id,
            'condition' => array_key_exists('condition', $data) ? $data['condition'] : $activo->condition,
            'status' => array_key_exists('status', $data) ? $data['status'] : $activo->status,
            'warranty_expiry' => array_key_exists('warranty_expiry', $data) ? $data['warranty_expiry'] : $activo->warranty_expiry,
            'purchase_date' => array_key_exists('purchase_date', $data) ? $data['purchase_date'] : $activo->purchase_date,
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
     * Crea N instancias serializadas desde una compra (5 computadores = 5 registros).
     * Cada instancia tiene quantity=1, serial_number y apunta al template.
     */
    public function crearInstanciasSerializadas(Store $store, int $templateActivoId, array $serialNumbers, float $unitCost, int $userId, ?int $purchaseId = null, string $description = ''): \Illuminate\Support\Collection
    {
        $template = Activo::where('id', $templateActivoId)
            ->where('store_id', $store->id)
            ->where('control_type', Activo::CONTROL_SERIALIZADO)
            ->whereNull('activo_template_id')
            ->firstOrFail();

        if (count($serialNumbers) === 0) {
            throw new Exception('Debes indicar al menos un número de serie.');
        }

        return DB::transaction(function () use ($store, $template, $serialNumbers, $unitCost, $userId, $purchaseId, $description) {
            $instances = collect();
            foreach ($serialNumbers as $serial) {
                $serial = trim((string) $serial);
                if ($serial === '') {
                    continue;
                }
                $activo = Activo::create([
                    'store_id' => $store->id,
                    'control_type' => Activo::CONTROL_SERIALIZADO,
                    'activo_template_id' => $template->id,
                    'name' => $template->name,
                    'code' => $template->code,
                    'serial_number' => $serial,
                    'model' => $template->model,
                    'brand' => $template->brand,
                    'description' => $template->description,
                    'quantity' => 1,
                    'unit_cost' => $unitCost,
                    'location' => $template->location,
                    'location_id' => $template->location_id,
                    'condition' => Activo::CONDITION_NUEVO,
                    'status' => Activo::STATUS_ACTIVO,
                    'warranty_expiry' => $template->warranty_expiry,
                    'purchase_date' => $template->purchase_date,
                    'is_active' => true,
                ]);
                MovimientoActivo::create([
                    'store_id' => $store->id,
                    'user_id' => $userId,
                    'activo_id' => $activo->id,
                    'purchase_id' => $purchaseId,
                    'type' => MovimientoActivo::TYPE_ENTRADA,
                    'quantity' => 1,
                    'unit_cost' => $unitCost,
                    'description' => $description ?: ($purchaseId ? "Compra #{$purchaseId}" : 'Alta serializado'),
                ]);
                $instances->push($activo);
            }
            return $instances;
        });
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
     * Suma cantidad a un activo LOTE (cuando se recibe de una compra aprobada).
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
            ->lote()
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'code', 'quantity', 'unit_cost', 'location']);
    }

    /**
     * Lista activos con filtros.
     */
    public function listarActivos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = Activo::deTienda($store->id)
            ->with(['locationRelation:id,name', 'assignedTo:id,name', 'template:id,name'])
            ->orderBy('name');

        if (! empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }
        if (isset($filtros['is_active'])) {
            $query->where('is_active', (bool) $filtros['is_active']);
        }
        if (! empty($filtros['control_type'])) {
            $query->where('control_type', $filtros['control_type']);
        }
        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
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

        return $query->templates()->orderBy('name')->limit($limit)->get(['id', 'name', 'code', 'control_type', 'quantity', 'unit_cost', 'location', 'serial_number']);
    }

    /**
     * Busca activos para añadir a una compra.
     * Muestra: todos los LOTE; SERIALIZADO solo cuando quantity=0 (catálogo disponible para comprar).
     */
    public function buscarActivosParaCompra(Store $store, string $term, int $limit = 25): \Illuminate\Support\Collection
    {
        $query = Activo::deTienda($store->id)->activos()->templates();

        $query->where(function ($q) {
            $q->where('control_type', Activo::CONTROL_LOTE)
                ->orWhere(function ($q2) {
                    $q2->where('control_type', Activo::CONTROL_SERIALIZADO)
                        ->where('quantity', 0);
                });
        });

        if (strlen(trim($term)) >= 2) {
            $query->buscar(trim($term));
        }

        return $query->orderBy('name')->limit($limit)->get(['id', 'name', 'code', 'control_type', 'quantity', 'unit_cost', 'location', 'serial_number']);
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
     * Da de baja un activo: crea movimiento de salida (para registrar la fecha) y cambia estado a BAJA.
     */
    public function darDeBaja(Store $store, int $activoId, int $userId, ?string $motivo = null): Activo
    {
        return DB::transaction(function () use ($store, $activoId, $userId, $motivo) {
            $activo = Activo::where('id', $activoId)
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($activo->status === Activo::STATUS_BAJA) {
                throw new Exception("El activo «{$activo->name}» ya está dado de baja.");
            }

            $quantity = (int) $activo->quantity;
            if ($quantity < 1) {
                throw new Exception("No se puede dar de baja un activo sin cantidad.");
            }

            $this->registrarMovimiento($store, $userId, [
                'activo_id' => $activo->id,
                'type' => MovimientoActivo::TYPE_SALIDA,
                'quantity' => $quantity,
                'description' => $motivo ? "Baja: {$motivo}" : 'Baja de activo',
            ]);

            $activo->update(['status' => Activo::STATUS_BAJA]);

            return $activo->fresh();
        });
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
