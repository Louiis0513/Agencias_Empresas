<?php

namespace App\Services;

use App\Models\Bolsillo;
use App\Models\SesionCaja;
use App\Models\SesionCajaDetalle;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SesionCajaService
{
    public function __construct(
        protected ComprobanteIngresoService $comprobanteIngresoService,
        protected ComprobanteEgresoService $comprobanteEgresoService
    ) {}

    public function obtenerSesionAbierta(Store $store): ?SesionCaja
    {
        return SesionCaja::deTienda($store->id)->abierta()->with(['user', 'detalles.bolsillo'])->first();
    }

    /**
     * Retorna [bolsillo_id => saldo_esperado] por cada bolsillo activo.
     * Si hay última sesión cerrada: usa saldo_esperado_cierre del detalle por bolsillo; si el bolsillo es nuevo, usa su saldo actual.
     * Si no hay sesiones previas: saldo actual del bolsillo.
     */
    public function obtenerSaldosEsperadosParaApertura(Store $store): array
    {
        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        $ultimaCerrada = SesionCaja::deTienda($store->id)->whereNotNull('closed_at')->orderByDesc('closed_at')->first();

        $result = [];
        foreach ($bolsillos as $b) {
            if ($ultimaCerrada) {
                $detalle = $ultimaCerrada->detalles()->where('bolsillo_id', $b->id)->first();
                // Usar saldo_fisico_cierre: tras el ajuste de cierre el saldo en sistema = físico; es lo que debe esperarse al abrir de nuevo.
                $result[$b->id] = $detalle && $detalle->saldo_fisico_cierre !== null
                    ? (float) $detalle->saldo_fisico_cierre
                    : (float) $b->saldo;
            } else {
                $result[$b->id] = (float) $b->saldo;
            }
        }
        return $result;
    }

    /**
     * Abre una sesión de caja. Por cada bolsillo activo crea detalle; si hay descuadre, delega en CI/CE.
     *
     * @param  array<int, float>  $saldosFisicosPorBolsillo  [bolsillo_id => saldo_fisico_apertura]
     */
    public function abrirSesion(Store $store, int $userId, array $saldosFisicosPorBolsillo, ?string $nota = null): SesionCaja
    {
        if ($this->obtenerSesionAbierta($store)) {
            throw new Exception('Ya hay una sesión de caja abierta. Cierre la sesión actual antes de abrir otra.');
        }

        $saldosEsperados = $this->obtenerSaldosEsperadosParaApertura($store);
        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        if ($bolsillos->isEmpty()) {
            throw new Exception('No hay bolsillos activos. Cree al menos un bolsillo antes de abrir la caja.');
        }

        return DB::transaction(function () use ($store, $userId, $saldosFisicosPorBolsillo, $nota, $saldosEsperados, $bolsillos) {
            $sesion = SesionCaja::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'opened_at' => now(),
                'nota_apertura' => $nota,
            ]);

            foreach ($bolsillos as $b) {
                $esperado = $saldosEsperados[$b->id] ?? 0.0;
                $fisico = (float) ($saldosFisicosPorBolsillo[$b->id] ?? 0);
                SesionCajaDetalle::create([
                    'sesion_caja_id' => $sesion->id,
                    'bolsillo_id' => $b->id,
                    'saldo_esperado_apertura' => $esperado,
                    'saldo_fisico_apertura' => $fisico,
                ]);

                $diferencia = $fisico - $esperado;
                if (abs($diferencia) < 0.01) {
                    continue;
                }
                if ($diferencia > 0) {
                    $this->comprobanteIngresoService->crearComprobante($store, $userId, [
                        'notes' => 'Ajuste inicial por descuadre',
                        'date' => now()->toDateString(),
                        'destinos' => [['bolsillo_id' => $b->id, 'amount' => $diferencia, 'reference' => null]],
                    ]);
                } else {
                    $this->comprobanteEgresoService->crearComprobante($store, $userId, [
                        'notes' => 'Ajuste inicial por descuadre',
                        'payment_date' => now()->toDateString(),
                        'destinos' => [
                            ['concepto' => 'Ajuste inicial por descuadre', 'beneficiario' => '', 'amount' => abs($diferencia)],
                        ],
                        'origenes' => [['bolsillo_id' => $b->id, 'amount' => abs($diferencia), 'reference' => null]],
                    ]);
                }
            }

            return $sesion->fresh(['detalles.bolsillo']);
        });
    }

    /**
     * Paso B + C del cierre: asegura detalle por cada bolsillo activo (crea con 0/0 si falta), actualiza saldos de cierre, genera ajustes por delegación, cierra sesión.
     *
     * @param  array<int, float>  $saldosFisicosCierrePorBolsillo  [bolsillo_id => saldo_fisico_cierre]
     */
    public function cerrarSesion(Store $store, int $userId, array $saldosFisicosCierrePorBolsillo, ?string $notaCierre = null): SesionCaja
    {
        $sesion = $this->obtenerSesionAbierta($store);
        if (! $sesion) {
            throw new Exception('No hay una sesión de caja abierta para cerrar.');
        }

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        if ($bolsillos->isEmpty()) {
            throw new Exception('No hay bolsillos activos.');
        }

        return DB::transaction(function () use ($store, $userId, $saldosFisicosCierrePorBolsillo, $notaCierre, $sesion, $bolsillos) {
            $sesion->load('detalles');

            foreach ($bolsillos as $b) {
                $detalle = $sesion->detalles->firstWhere('bolsillo_id', $b->id);
                if (! $detalle) {
                    SesionCajaDetalle::create([
                        'sesion_caja_id' => $sesion->id,
                        'bolsillo_id' => $b->id,
                        'saldo_esperado_apertura' => 0,
                        'saldo_fisico_apertura' => 0,
                        'saldo_esperado_cierre' => (float) $b->fresh()->saldo,
                        'saldo_fisico_cierre' => (float) ($saldosFisicosCierrePorBolsillo[$b->id] ?? 0),
                    ]);
                    $detalle = $sesion->detalles()->where('bolsillo_id', $b->id)->first();
                } else {
                    $detalle->update([
                        'saldo_esperado_cierre' => (float) $b->fresh()->saldo,
                        'saldo_fisico_cierre' => (float) ($saldosFisicosCierrePorBolsillo[$b->id] ?? 0),
                    ]);
                }
            }

            $detalles = $sesion->fresh('detalles.bolsillo')->detalles;

            foreach ($detalles as $det) {
                $esperado = (float) $det->saldo_esperado_cierre;
                $fisico = (float) $det->saldo_fisico_cierre;
                $diferencia = $fisico - $esperado;
                if (abs($diferencia) < 0.01) {
                    continue;
                }
                $bolsilloId = $det->bolsillo_id;
                $monto = abs($diferencia);
                if ($diferencia > 0) {
                    $this->comprobanteIngresoService->crearComprobante($store, $userId, [
                        'notes' => 'Descuadre de cierre de caja' . ($notaCierre ? ': ' . $notaCierre : ''),
                        'date' => now()->toDateString(),
                        'destinos' => [['bolsillo_id' => $bolsilloId, 'amount' => $monto, 'reference' => null]],
                    ]);
                } else {
                    $this->comprobanteEgresoService->crearComprobante($store, $userId, [
                        'notes' => 'Descuadre de cierre de caja' . ($notaCierre ? ': ' . $notaCierre : ''),
                        'payment_date' => now()->toDateString(),
                        'destinos' => [
                            ['concepto' => 'Descuadre de cierre de caja', 'beneficiario' => '', 'amount' => $monto],
                        ],
                        'origenes' => [['bolsillo_id' => $bolsilloId, 'amount' => $monto, 'reference' => null]],
                    ]);
                }
            }

            $sesion->update([
                'closed_at' => now(),
                'closed_by_user_id' => $userId,
                'nota_cierre' => $notaCierre,
            ]);

            return $sesion->fresh(['detalles.bolsillo', 'user', 'closedByUser']);
        });
    }

    public function listarSesiones(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = SesionCaja::deTienda($store->id)
            ->with(['user:id,name', 'closedByUser:id,name', 'detalles.bolsillo:id,name'])
            ->orderByDesc('opened_at');

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('opened_at', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('opened_at', '<=', $filtros['fecha_hasta']);
        }
        if (isset($filtros['solo_cerradas']) && $filtros['solo_cerradas']) {
            $query->whereNotNull('closed_at');
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function obtenerSesion(Store $store, int $sesionId): SesionCaja
    {
        return SesionCaja::deTienda($store->id)
            ->with(['user', 'closedByUser', 'detalles.bolsillo'])
            ->findOrFail($sesionId);
    }
}
