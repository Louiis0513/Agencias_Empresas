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

    public function crearBolsillo(Store $store, array $datos): Bolsillo
    {
        $this->validarNombreBolsilloUnico($store->id, $datos['name']);

        return DB::transaction(function () use ($store, $datos) {
            return Bolsillo::create([
                'store_id'         => $store->id,
                'name'             => $datos['name'],
                'detalles'         => $datos['detalles'] ?? null,
                'saldo'            => (float) ($datos['saldo'] ?? 0),
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
        return DB::transaction(function () use ($store, $userId, $datos) {
            $bolsillo = Bolsillo::deTienda($store->id)
                ->where('id', $datos['bolsillo_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (($datos['type'] ?? '') === MovimientoBolsillo::TYPE_EXPENSE) {
                $this->validarFondos($bolsillo, (float) $datos['amount']);
            }

            $mov = MovimientoBolsillo::create([
                'store_id'        => $store->id,
                'bolsillo_id'     => $bolsillo->id,
                'invoice_id'      => $datos['invoice_id'] ?? null,
                'user_id'         => $userId,
                'type'            => $datos['type'],
                'amount'          => $datos['amount'],
                'payment_method'  => $datos['payment_method'] ?? null,
                'description'     => $datos['description'] ?? null,
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

    public function actualizarMovimiento(MovimientoBolsillo $movimiento, array $datos): MovimientoBolsillo
    {
        return DB::transaction(function () use ($movimiento, $datos) {
            if (isset($datos['bolsillo_id']) && (int) $datos['bolsillo_id'] !== $movimiento->bolsillo_id) {
                throw new Exception('No se puede cambiar el bolsillo de un movimiento.');
            }

            $bolsillo = $movimiento->bolsillo()->lockForUpdate()->first();
            $nuevoTipo = $datos['type'] ?? $movimiento->type;
            $nuevoMonto = (float) ($datos['amount'] ?? $movimiento->amount);

            if ($nuevoTipo === MovimientoBolsillo::TYPE_EXPENSE) {
                $saldoTemporal = (float) $bolsillo->saldo;
                if ($movimiento->type === MovimientoBolsillo::TYPE_INCOME) {
                    $saldoTemporal -= $movimiento->amount;
                } else {
                    $saldoTemporal += $movimiento->amount;
                }
                if ($saldoTemporal < $nuevoMonto) {
                    throw new Exception('Fondos insuficientes. No se puede actualizar el movimiento.');
                }
            }

            if ($movimiento->type === MovimientoBolsillo::TYPE_INCOME) {
                $bolsillo->saldo -= $movimiento->amount;
            } else {
                $bolsillo->saldo += $movimiento->amount;
            }

            $movimiento->update([
                'type'        => $nuevoTipo,
                'amount'      => $nuevoMonto,
                'description' => $datos['description'] ?? $movimiento->description,
                'invoice_id'  => $datos['invoice_id'] ?? $movimiento->invoice_id,
            ]);

            if ($nuevoTipo === MovimientoBolsillo::TYPE_INCOME) {
                $bolsillo->saldo += $nuevoMonto;
            } else {
                $bolsillo->saldo -= $nuevoMonto;
            }
            $bolsillo->save();

            return $movimiento->fresh(['bolsillo', 'user', 'invoice']);
        });
    }

    public function eliminarMovimiento(MovimientoBolsillo $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            $bolsillo = $movimiento->bolsillo()->lockForUpdate()->first();

            if ($movimiento->type === MovimientoBolsillo::TYPE_INCOME) {
                $bolsillo->saldo -= $movimiento->amount;
            } else {
                $bolsillo->saldo += $movimiento->amount;
            }
            if ($bolsillo->saldo < 0) {
                throw new Exception('No se puede eliminar este ingreso; el saldo quedaría negativo.');
            }
            $bolsillo->save();
            $movimiento->delete();
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
            ->with(['bolsillo:id,store_id,name,saldo,detalles', 'user:id,name', 'invoice:id,total'])
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
