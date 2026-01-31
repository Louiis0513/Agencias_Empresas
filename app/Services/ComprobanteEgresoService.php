<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteEgresoDestino;
use App\Models\ComprobanteEgresoOrigen;
use App\Models\MovimientoBolsillo;
use App\Models\Purchase;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ComprobanteEgresoService
{
    public function __construct(
        protected CajaService $cajaService
    ) {}

    /**
     * Genera el siguiente número consecutivo CE-XXX por tienda.
     */
    public function siguienteNumero(Store $store): string
    {
        $count = ComprobanteEgreso::deTienda($store->id)->count();

        return 'CE-' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Crea un comprobante de egreso con múltiples destinos y orígenes.
     * Regla de Oro: Un Comprobante = Un Proveedor (o NULL para gasto directo).
     * - proveedor_id: pago de facturas del proveedor
     * - proveedor_id NULL: gastos directos (ítems libres)
     */
    public function crearComprobante(Store $store, int $userId, array $data): ComprobanteEgreso
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $proveedorId = ! empty($data['proveedor_id']) ? (int) $data['proveedor_id'] : null;
            $destinos = $data['destinos'] ?? [];
            $origenes = $data['origenes'] ?? [];

            $totalDestinos = array_sum(array_map(fn ($d) => (float) ($d['amount'] ?? 0), $destinos));
            $totalOrigenes = array_sum(array_map(fn ($o) => (float) ($o['amount'] ?? 0), $origenes));

            if ($totalDestinos <= 0 || $totalOrigenes <= 0) {
                throw new Exception('Debe indicar al menos un destino y un origen con montos mayores a cero.');
            }

            if (abs($totalDestinos - $totalOrigenes) > 0.01) {
                throw new Exception("La suma de destinos ({$totalDestinos}) debe coincidir con la suma de orígenes ({$totalOrigenes}).");
            }

            $tieneCuentasPorPagar = collect($destinos)->contains(fn ($d) => ! empty($d['account_payable_id'] ?? null));
            $beneficiaryName = $this->calcularBeneficiaryName($store, $proveedorId, $destinos, $tieneCuentasPorPagar);
            $type = $tieneCuentasPorPagar ? ComprobanteEgreso::TYPE_PAGO_CUENTA : ComprobanteEgreso::TYPE_GASTO_DIRECTO;

            $comprobante = ComprobanteEgreso::create([
                'store_id' => $store->id,
                'proveedor_id' => $proveedorId,
                'number' => $this->siguienteNumero($store),
                'total_amount' => $totalDestinos,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'type' => $type,
                'beneficiary_name' => $beneficiaryName,
                'user_id' => $userId,
            ]);

            foreach ($destinos as $d) {
                $amount = (float) ($d['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $accountPayableId = ! empty($d['account_payable_id']) ? (int) $d['account_payable_id'] : null;

                // Si tiene account_payable_id = pago a cuenta por pagar (factura), aunque proveedor sea null
                if ($accountPayableId) {
                    if ($proveedorId !== null) {
                        $this->validarCuentaPerteneceAProveedor($store, $accountPayableId, $proveedorId);
                    }
                    $this->aplicarPagoACuentaPorPagar($store, $accountPayableId, $amount);

                    ComprobanteEgresoDestino::create([
                        'comprobante_egreso_id' => $comprobante->id,
                        'type' => ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR,
                        'account_payable_id' => $accountPayableId,
                        'concepto' => null,
                        'beneficiario' => null,
                        'amount' => $amount,
                    ]);
                } else {
                    // Gasto directo: requiere concepto
                    $concepto = trim($d['concepto'] ?? '');
                    if (! $concepto) {
                        throw new Exception('Debe indicar el concepto para cada ítem de gasto directo.');
                    }

                    ComprobanteEgresoDestino::create([
                        'comprobante_egreso_id' => $comprobante->id,
                        'type' => ComprobanteEgresoDestino::TYPE_GASTO_DIRECTO,
                        'account_payable_id' => null,
                        'concepto' => $concepto,
                        'beneficiario' => trim($d['beneficiario'] ?? ''),
                        'amount' => $amount,
                    ]);
                }
            }

            foreach ($origenes as $o) {
                $amount = (float) ($o['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $bolsilloId = (int) ($o['bolsillo_id'] ?? 0);
                if (! $bolsilloId) {
                    throw new Exception('Debe indicar bolsillo_id para cada origen.');
                }

                ComprobanteEgresoOrigen::create([
                    'comprobante_egreso_id' => $comprobante->id,
                    'bolsillo_id' => $bolsilloId,
                    'amount' => $amount,
                    'reference' => $o['reference'] ?? null,
                ]);

                $descripcion = $this->descripcionMovimiento($comprobante, $destinos);
                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id' => $bolsilloId,
                    'type' => MovimientoBolsillo::TYPE_EXPENSE,
                    'amount' => $amount,
                    'payment_method' => $o['payment_method'] ?? null,
                    'description' => $descripcion,
                    'comprobante_egreso_id' => $comprobante->id,
                ]);
            }

            return $comprobante->load(['destinos.accountPayable.purchase.proveedor', 'origenes.bolsillo']);
        });
    }

    /**
     * Revierte un comprobante de egreso (usa origenes originales).
     * Usado internamente por AccountPayableService.
     */
    public function reversar(Store $store, int $comprobanteId, int $userId): void
    {
        $comprobante = ComprobanteEgreso::where('id', $comprobanteId)
            ->where('store_id', $store->id)
            ->with(['origenes'])
            ->firstOrFail();

        $origenes = $comprobante->origenes->map(fn ($o) => [
            'bolsillo_id' => $o->bolsillo_id,
            'amount' => (float) $o->amount,
            'reference' => $o->reference,
        ])->toArray();

        $this->anularComprobante($store, $comprobanteId, $userId, $origenes);
    }

    /**
     * Anula un comprobante de egreso.
     * - Registra INGRESOS en los bolsillos indicados (concepto: Reverso comprobante de egreso)
     * - Restaura saldos de cuentas por pagar
     * - Marca el comprobante como revertido
     *
     * @param  array  $origenes  [['bolsillo_id' => int, 'amount' => float, 'reference' => ?string], ...]
     */
    public function anularComprobante(Store $store, int $comprobanteId, int $userId, array $origenes): void
    {
        DB::transaction(function () use ($store, $comprobanteId, $userId, $origenes) {
            $comprobante = ComprobanteEgreso::where('id', $comprobanteId)
                ->where('store_id', $store->id)
                ->with(['destinos.accountPayable.purchase', 'origenes.bolsillo'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($comprobante->isReversed()) {
                throw new Exception('Este comprobante ya fue anulado.');
            }

            $totalOrigenes = 0;
            foreach ($origenes as $o) {
                $amount = (float) ($o['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $bolsilloId = (int) ($o['bolsillo_id'] ?? 0);
                if (! $bolsilloId) {
                    throw new Exception('Debe indicar bolsillo para cada origen del reverso.');
                }
                $totalOrigenes += $amount;
            }

            $totalComprobante = (float) $comprobante->total_amount;
            if (abs($totalOrigenes - $totalComprobante) > 0.01) {
                throw new Exception("La suma de los bolsillos del reverso ({$totalOrigenes}) debe coincidir con el total del comprobante ({$totalComprobante}).");
            }

            $concepto = "Reverso comprobante de egreso {$comprobante->number}";

            foreach ($origenes as $o) {
                $amount = (float) ($o['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id' => (int) $o['bolsillo_id'],
                    'type' => MovimientoBolsillo::TYPE_INCOME,
                    'amount' => $amount,
                    'description' => $concepto,
                    'reversal_of_comprobante_egreso_id' => $comprobante->id,
                ]);
            }

            foreach ($comprobante->destinos as $destino) {
                if ($destino->isCuentaPorPagar() && $destino->account_payable_id) {
                    $this->revertirPagoACuentaPorPagar($destino->accountPayable, (float) $destino->amount);
                }
            }

            $comprobante->update([
                'reversed_at' => now(),
                'reversal_user_id' => $userId,
            ]);
        });
    }

    public function listar(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = ComprobanteEgreso::deTienda($store->id)
            ->with(['user:id,name', 'proveedor:id,nombre', 'destinos.accountPayable.purchase.proveedor'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if (! empty($filtros['type'])) {
            $query->where('type', $filtros['type']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('payment_date', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('payment_date', '<=', $filtros['fecha_hasta']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function obtener(Store $store, int $comprobanteId): ComprobanteEgreso
    {
        return ComprobanteEgreso::where('id', $comprobanteId)
            ->where('store_id', $store->id)
            ->with(['user', 'proveedor', 'destinos.accountPayable.purchase.proveedor', 'origenes.bolsillo'])
            ->firstOrFail();
    }

    /**
     * Actualiza campos editables del comprobante (fecha, notas).
     * Los montos y destinos/orígenes no se pueden editar sin reversar.
     */
    public function actualizarComprobante(Store $store, int $comprobanteId, array $data): ComprobanteEgreso
    {
        $comprobante = $this->obtener($store, $comprobanteId);

        if ($comprobante->isReversed()) {
            throw new Exception('No se puede editar un comprobante revertido.');
        }

        $comprobante->update([
            'payment_date' => $data['payment_date'] ?? $comprobante->payment_date,
            'notes' => $data['notes'] ?? $comprobante->notes,
        ]);

        return $comprobante->load(['user', 'proveedor', 'destinos.accountPayable.purchase.proveedor', 'origenes.bolsillo']);
    }

    private function calcularBeneficiaryName(Store $store, ?int $proveedorId, array $destinos, bool $tieneCuentasPorPagar = false): string
    {
        if ($proveedorId) {
            $proveedor = \App\Models\Proveedor::find($proveedorId);

            return $proveedor?->nombre ?? 'Proveedor';
        }

        if ($tieneCuentasPorPagar) {
            return 'Sin proveedor';
        }

        $primerConcepto = collect($destinos)->firstWhere(fn ($d) => (float) ($d['amount'] ?? 0) > 0);
        $concepto = $primerConcepto['concepto'] ?? $primerConcepto['beneficiario'] ?? null;

        return $concepto ?: 'Gasto directo';
    }

    private function validarCuentaPerteneceAProveedor(Store $store, int $accountPayableId, int $proveedorId): void
    {
        $ap = AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->with('purchase')
            ->first();

        if (! $ap || $ap->purchase->proveedor_id != $proveedorId) {
            throw new Exception("La cuenta por pagar #{$accountPayableId} no pertenece al proveedor seleccionado.");
        }
    }

    private function descripcionMovimiento(ComprobanteEgreso $comprobante, array $destinos): string
    {
        $partes = [];
        foreach ($destinos as $d) {
            $amount = (float) ($d['amount'] ?? 0);
            if ($d['account_payable_id'] ?? null) {
                $apId = (int) $d['account_payable_id'];
                $ap = AccountPayable::with('purchase')->find($apId);
                $compraId = $ap?->purchase?->id ?? $apId;
                $partes[] = "Compra #{$compraId}: " . number_format($amount, 2);
            } else {
                $partes[] = ($d['concepto'] ?? 'Gasto') . ': ' . number_format($amount, 2);
            }
        }

        return 'Comprobante ' . $comprobante->number . ' - ' . implode(' | ', $partes);
    }

    private function aplicarPagoACuentaPorPagar(Store $store, int $accountPayableId, float $amount): void
    {
        $accountPayable = AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($accountPayable->isPagado()) {
            throw new Exception("La cuenta por pagar #{$accountPayableId} ya está pagada.");
        }

        if ($amount > $accountPayable->balance) {
            throw new Exception("El monto ({$amount}) excede el saldo pendiente ({$accountPayable->balance}) de la cuenta por pagar.");
        }

        $nuevoBalance = $accountPayable->balance - $amount;
        $nuevoStatus = $nuevoBalance <= 0
            ? AccountPayable::STATUS_PAGADO
            : AccountPayable::STATUS_PARCIAL;

        $accountPayable->update([
            'balance' => max(0, $nuevoBalance),
            'status' => $nuevoStatus,
        ]);

        if ($nuevoStatus === AccountPayable::STATUS_PAGADO) {
            $accountPayable->purchase->update(['payment_status' => Purchase::PAYMENT_PAGADO]);
        }
    }

    private function revertirPagoACuentaPorPagar(AccountPayable $accountPayable, float $monto): void
    {
        $accountPayable = AccountPayable::where('id', $accountPayable->id)->lockForUpdate()->firstOrFail();
        $eraPagado = $accountPayable->isPagado();
        $nuevoBalance = $accountPayable->balance + $monto;
        $nuevoStatus = $nuevoBalance <= 0
            ? AccountPayable::STATUS_PAGADO
            : ($nuevoBalance >= $accountPayable->total_amount
                ? AccountPayable::STATUS_PENDIENTE
                : AccountPayable::STATUS_PARCIAL);

        $accountPayable->update([
            'balance' => $nuevoBalance,
            'status' => $nuevoStatus,
        ]);

        if ($eraPagado) {
            $accountPayable->purchase->update(['payment_status' => Purchase::PAYMENT_PENDIENTE]);
        }
    }
}
