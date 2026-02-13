<?php

namespace App\Services;

use App\Models\Bolsillo;
use App\Models\MovimientoBolsillo;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Exception;

class CajaService
{
    /**
     * Caja = suma de todos los bolsillos. Sin tabla.
     * Retorna el total (suma de saldos de bolsillos de la tienda).
     */
    public function totalCaja(Store $store): float
    {
        return (float) Bolsillo::deTienda($store->id)->sum('saldo');
    }

    /**
     * Crea un bolsillo con saldo inicial 0.
     * Si se desea saldo inicial, el caller debe crear un Comprobante de Ingreso
     * con destino a este bolsillo (ej. "Saldo inicial desde creación del bolsillo") para trazabilidad.
     */
    public function crearBolsillo(Store $store, array $datos): Bolsillo
    {
        $this->validarNombreBolsilloUnico($store->id, $datos['name']);

        return DB::transaction(function () use ($store, $datos) {
            return Bolsillo::create([
                'store_id'         => $store->id,
                'name'             => $datos['name'],
                'detalles'         => $datos['detalles'] ?? null,
                'saldo'            => 0,
                'is_bank_account'  => (bool) ($datos['is_bank_account'] ?? false),
                'is_active'        => (bool) ($datos['is_active'] ?? true),
            ]);
        });
    }

    public function actualizarBolsillo(Bolsillo $bolsillo, array $datos): Bolsillo
    {
        if (isset($datos['name']) && $datos['name'] !== $bolsillo->name) {
            $this->validarNombreBolsilloUnico($bolsillo->store_id, $datos['name'], $bolsillo->id);
        }

        $bolsillo->update([
            'name'            => $datos['name'] ?? $bolsillo->name,
            'detalles'        => $datos['detalles'] ?? $bolsillo->detalles,
            'is_bank_account' => $datos['is_bank_account'] ?? $bolsillo->is_bank_account,
            'is_active'       => $datos['is_active'] ?? $bolsillo->is_active,
        ]);
        return $bolsillo;
    }

    public function eliminarBolsillo(Bolsillo $bolsillo): void
    {
        if ($bolsillo->saldo != 0) {
            throw new Exception('No puedes eliminar un bolsillo con dinero. Realiza retiros o movimientos primero.');
        }
        $bolsillo->delete();
    }

    public function registrarMovimiento(Store $store, int $userId, array $datos): MovimientoBolsillo
    {
        $comprobanteIngresoId = $datos['comprobante_ingreso_id'] ?? null;
        $comprobanteEgresoId = $datos['comprobante_egreso_id'] ?? null;
        if (! $comprobanteIngresoId && ! $comprobanteEgresoId) {
            throw new Exception('Cada movimiento de caja debe estar vinculado a un Comprobante de Ingreso o de Egreso. Cree el comprobante desde el módulo correspondiente.');
        }

        return DB::transaction(function () use ($store, $userId, $datos) {
            $bolsillo = Bolsillo::deTienda($store->id)
                ->where('id', $datos['bolsillo_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (($datos['type'] ?? '') === MovimientoBolsillo::TYPE_EXPENSE) {
                $this->validarFondos($bolsillo, (float) $datos['amount']);
            }

            $mov = MovimientoBolsillo::create([
                'store_id'               => $store->id,
                'bolsillo_id'            => $bolsillo->id,
                'comprobante_egreso_id'   => $datos['comprobante_egreso_id'] ?? null,
                'comprobante_ingreso_id'  => $datos['comprobante_ingreso_id'] ?? null,
                'type'                   => $datos['type'],
                'amount'                 => $datos['amount'],
                'description'            => $datos['description'] ?? null,
            ]);

            if ($datos['type'] === MovimientoBolsillo::TYPE_INCOME) {
                $bolsillo->saldo += $datos['amount'];
            } else {
                $bolsillo->saldo -= $datos['amount'];
            }
            $bolsillo->save();

            return $mov;
        });
    }

    public function listarBolsillos(Store $store, array $filtros = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Bolsillo::deTienda($store->id)->orderBy('name');
        if (!empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }
        if (isset($filtros['is_active'])) {
            $query->where('is_active', (bool) $filtros['is_active']);
        }
        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function listarMovimientos(Store $store, array $filtros = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = MovimientoBolsillo::deTienda($store->id)
            ->with(['bolsillo:id,store_id,name,saldo,detalles', 'comprobanteIngreso:id,number,user_id', 'comprobanteIngreso.user:id,name', 'comprobanteEgreso:id,number,user_id', 'comprobanteEgreso.user:id,name'])
            ->orderByDesc('created_at');

        if (!empty($filtros['bolsillo_id'])) {
            $query->porBolsillo((int) $filtros['bolsillo_id']);
        }
        if (!empty($filtros['type'])) {
            $query->porTipo($filtros['type']);
        }
        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }
        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function obtenerBolsillo(Store $store, int $bolsilloId): Bolsillo
    {
        return Bolsillo::deTienda($store->id)->findOrFail($bolsilloId);
    }

    /**
     * Bolsillos disponibles para pago en factura.
     * Efectivo → solo no bancarios (caja física). Tarjeta/Transferencia → solo bancarios.
     */
    public function obtenerBolsillosParaPago(Store $store, string $paymentMethod): \Illuminate\Support\Collection
    {
        $query = Bolsillo::deTienda($store->id)->activos()->orderBy('name');
        if ($paymentMethod === 'CASH') {
            $query->where('is_bank_account', false);
        } else {
            $query->where('is_bank_account', true);
        }
        return $query->get();
    }

    private function validarNombreBolsilloUnico(int $storeId, string $name, ?int $ignorarId = null): void
    {
        $q = Bolsillo::deTienda($storeId)->where('name', $name);
        if ($ignorarId) {
            $q->where('id', '!=', $ignorarId);
        }
        if ($q->exists()) {
            throw new Exception("Ya existe un bolsillo con el nombre '{$name}' en esta tienda.");
        }
    }

    private function validarFondos(Bolsillo $bolsillo, float $monto): void
    {
        if ($bolsillo->saldo < $monto) {
            throw new Exception("Fondos insuficientes en '{$bolsillo->name}'. Saldo actual: {$bolsillo->saldo}");
        }
    }
}
