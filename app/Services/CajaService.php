<?php

namespace App\Services;

use App\Models\Bolsillo;
use App\Models\CuentaContable;
use App\Models\MovimientoBolsillo;
use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\DB;

class CajaService
{
    public function __construct(
        protected CuentaContableService $cuentaContableService,
    ) {}

    /**
     * Caja = suma de todos los bolsillos. Sin tabla.
     * Retorna el total (suma de saldos de bolsillos de la tienda).
     */
    public function totalCaja(Store $store): float
    {
        return (float) Bolsillo::deTienda($store->id)->sum('saldo');
    }

    /**
     * Crea un bolsillo con saldo inicial 0 y su cuenta auxiliar del Disponible (11).
     *
     * datos.tipo_disponible: efectivo|corriente_cop|ahorro|divisas
     * (fallback legacy: is_bank_account true → corriente_cop, false → efectivo)
     */
    public function crearBolsillo(Store $store, array $datos): Bolsillo
    {
        $this->validarNombreBolsilloUnico($store->id, $datos['name']);

        $tipo = $datos['tipo_disponible']
            ?? ((bool) ($datos['is_bank_account'] ?? false) ? 'corriente_cop' : 'efectivo');

        if (! isset(CuentaContable::TIPOS_BOLSILLO_PADRE[$tipo])) {
            throw new Exception('Tipo de disponible inválido.');
        }

        $codigoPadre = CuentaContable::TIPOS_BOLSILLO_PADRE[$tipo];
        $visible = (bool) ($datos['is_active'] ?? true);

        return DB::transaction(function () use ($store, $datos, $codigoPadre, $visible) {
            $cuenta = $this->cuentaContableService->crearAuxiliarSinBolsillo($store, $codigoPadre, [
                'nombre' => $datos['name'],
                'activo' => $visible,
            ]);

            return Bolsillo::create([
                'store_id' => $store->id,
                'cuenta_contable_id' => $cuenta->id,
                'name' => $datos['name'],
                'detalles' => $datos['detalles'] ?? null,
                'saldo' => 0,
                'is_bank_account' => CuentaContable::codigoEsBanco($cuenta->codigo),
                'is_active' => $visible,
            ]);
        });
    }

    public function actualizarBolsillo(Bolsillo $bolsillo, array $datos): Bolsillo
    {
        if (isset($datos['name']) && $datos['name'] !== $bolsillo->name) {
            $this->validarNombreBolsilloUnico($bolsillo->store_id, $datos['name'], $bolsillo->id);
        }

        return DB::transaction(function () use ($bolsillo, $datos) {
            $name = $datos['name'] ?? $bolsillo->name;
            $isActive = array_key_exists('is_active', $datos)
                ? (bool) $datos['is_active']
                : $bolsillo->is_active;

            $bolsillo->update([
                'name' => $name,
                'detalles' => array_key_exists('detalles', $datos) ? $datos['detalles'] : $bolsillo->detalles,
                'is_active' => $isActive,
                // is_bank_account no se cambia en edición (lo define la cuenta PUC).
            ]);

            if ($bolsillo->cuenta_contable_id) {
                $cuenta = CuentaContable::query()->find($bolsillo->cuenta_contable_id);
                if ($cuenta) {
                    $cuenta->update([
                        'nombre' => $name,
                        'activo' => $isActive,
                    ]);
                }
            }

            return $bolsillo->fresh(['cuentaContable']);
        });
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
        $sesion = app(SesionCajaService::class)->obtenerSesionAbierta($store);
        if (! $sesion) {
            throw new Exception('No hay una sesión de caja abierta. Abra la caja para registrar movimientos.');
        }

        $comprobanteIngresoId = $datos['comprobante_ingreso_id'] ?? null;
        $comprobanteEgresoId = $datos['comprobante_egreso_id'] ?? null;
        if (! $comprobanteIngresoId && ! $comprobanteEgresoId) {
            throw new Exception('Cada movimiento de caja debe estar vinculado a un Comprobante de Ingreso o de Egreso. Cree el comprobante desde el módulo correspondiente.');
        }

        return DB::transaction(function () use ($store, $datos, $sesion) {
            $bolsillo = Bolsillo::deTienda($store->id)
                ->where('id', $datos['bolsillo_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (($datos['type'] ?? '') === MovimientoBolsillo::TYPE_EXPENSE) {
                $this->validarFondos($bolsillo, (float) $datos['amount']);
            }

            $mov = MovimientoBolsillo::create([
                'store_id' => $store->id,
                'bolsillo_id' => $bolsillo->id,
                'sesion_caja_id' => $sesion->id,
                'comprobante_egreso_id' => $datos['comprobante_egreso_id'] ?? null,
                'comprobante_ingreso_id' => $datos['comprobante_ingreso_id'] ?? null,
                'type' => $datos['type'],
                'amount' => $datos['amount'],
                'description' => $datos['description'] ?? null,
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
        $query = Bolsillo::deTienda($store->id)
            ->with('cuentaContable:id,codigo,nombre,activo')
            ->orderBy('name');
        if (! empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }
        if (isset($filtros['is_active'])) {
            $query->where('is_active', (bool) $filtros['is_active']);
        }
        $perPage = (int) ($filtros['per_page'] ?? 15);
        $pageName = isset($filtros['page_name']) && is_string($filtros['page_name']) && $filtros['page_name'] !== ''
            ? $filtros['page_name']
            : 'page';

        return $query->paginate($perPage, ['*'], $pageName);
    }

    public function listarMovimientos(Store $store, array $filtros = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = MovimientoBolsillo::deTienda($store->id)
            ->with(['bolsillo:id,store_id,name,saldo,detalles', 'comprobanteIngreso:id,number,user_id', 'comprobanteIngreso.user:id,name', 'comprobanteEgreso:id,number,user_id', 'comprobanteEgreso.user:id,name'])
            ->orderByDesc('created_at');

        if (! empty($filtros['bolsillo_id'])) {
            $query->porBolsillo((int) $filtros['bolsillo_id']);
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

    public function obtenerBolsillo(Store $store, int $bolsilloId): Bolsillo
    {
        return Bolsillo::deTienda($store->id)
            ->with('cuentaContable')
            ->findOrFail($bolsilloId);
    }

    /**
     * Código auxiliar que se asignaría al crear un bolsillo del tipo dado.
     */
    public function previewCodigoAuxiliar(Store $store, string $tipoDisponible): ?string
    {
        $codigoPadre = CuentaContable::TIPOS_BOLSILLO_PADRE[$tipoDisponible] ?? null;
        if (! $codigoPadre) {
            return null;
        }

        $padre = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', $codigoPadre)
            ->where('es_auxiliar', false)
            ->first();

        // Si no está la de 6 dígitos, basta con que exista la cuenta de 4 (o el 11) para poder crearla al guardar.
        if (! $padre) {
            $codigoCuenta = substr($codigoPadre, 0, 4);
            $tieneBase = CuentaContable::query()
                ->deStore($store)
                ->whereIn('codigo', [$codigoCuenta, '11'])
                ->exists();
            if (! $tieneBase) {
                return null;
            }

            return $codigoPadre.'01';
        }

        try {
            return $this->cuentaContableService->siguienteCodigoAuxiliar($store, $codigoPadre);
        } catch (Exception) {
            return null;
        }
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

    /**
     * Vincula bolsillos existentes sin cuenta (requiere PUC importado).
     */
    public function backfillCuentasContables(Store $store): array
    {
        return $this->cuentaContableService->backfillBolsillosSinCuenta($store);
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
