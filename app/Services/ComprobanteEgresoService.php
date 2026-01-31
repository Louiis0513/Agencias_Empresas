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
     * Soporta pagos a cuentas por pagar y gastos directos.
     */
    public function crearComprobante(Store $store, int $userId, array $data): ComprobanteEgreso
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $destinos = $data['destinos'] ?? [];
            $origenes = $data['origenes'] ?? [];

            $totalDestinos = array_sum(array_column($destinos, 'amount'));
            $totalOrigenes = 0;
            foreach ($origenes as $o) {
                $totalOrigenes += (float) ($o['amount'] ?? 0);
            }

            if ($totalDestinos <= 0 || $totalOrigenes <= 0) {
                throw new Exception('Debe indicar al menos un destino y un origen con montos mayores a cero.');
            }

            if (abs($totalDestinos - $totalOrigenes) > 0.01) {
                throw new Exception("La suma de destinos ({$totalDestinos}) debe coincidir con la suma de orígenes ({$totalOrigenes}).");
            }

            $beneficiaryName = $this->calcularBeneficiaryName($store, $destinos);
            $type = $this->calcularTipo($destinos);

            $comprobante = ComprobanteEgreso::create([
                'store_id' => $store->id,
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

                $destinoType = $d['type'] ?? ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR;

                if ($destinoType === ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR) {
                    $accountPayableId = (int) ($d['account_payable_id'] ?? 0);
                    if (! $accountPayableId) {
                        throw new Exception('Debe indicar account_payable_id para destinos tipo CUENTA_POR_PAGAR.');
                    }
                    $this->aplicarPagoACuentaPorPagar($store, $accountPayableId, $amount);
                }

                ComprobanteEgresoDestino::create([
                    'comprobante_egreso_id' => $comprobante->id,
                    'type' => $destinoType,
                    'account_payable_id' => $destinoType === ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR
                        ? (int) ($d['account_payable_id'] ?? 0)
                        : null,
                    'concepto' => $d['concepto'] ?? null,
                    'beneficiario' => $d['beneficiario'] ?? null,
                    'amount' => $amount,
                ]);
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
     * Revierte un comprobante de egreso.
     * Crea movimientos INGRESO por cada origen y restaura balances de cuentas por pagar.
     */
    public function reversar(Store $store, int $comprobanteId, int $userId): void
    {
        DB::transaction(function () use ($store, $comprobanteId, $userId) {
            $comprobante = ComprobanteEgreso::where('id', $comprobanteId)
                ->where('store_id', $store->id)
                ->with(['destinos.accountPayable.purchase', 'origenes.bolsillo'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($comprobante->isReversed()) {
                throw new Exception('Este comprobante ya fue revertido.');
            }

            foreach ($comprobante->origenes as $origen) {
                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id' => $origen->bolsillo_id,
                    'type' => MovimientoBolsillo::TYPE_INCOME,
                    'amount' => $origen->amount,
                    'description' => "Reversa de comprobante {$comprobante->number}",
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
            ->with(['user:id,name', 'destinos.accountPayable.purchase.proveedor'])
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
            ->with(['user', 'destinos.accountPayable.purchase.proveedor', 'origenes.bolsillo'])
            ->firstOrFail();
    }

    private function calcularBeneficiaryName(Store $store, array $destinos): string
    {
        $nombres = [];
        foreach ($destinos as $d) {
            $type = $d['type'] ?? ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR;
            $amount = (float) ($d['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            if ($type === ComprobanteEgresoDestino::TYPE_GASTO_DIRECTO) {
                $nombres[] = $d['beneficiario'] ?? $d['concepto'] ?? 'Gasto directo';
            } else {
                $apId = (int) ($d['account_payable_id'] ?? 0);
                if ($apId) {
                    $ap = AccountPayable::where('store_id', $store->id)->with('purchase.proveedor')->find($apId);
                    $nombres[] = $ap?->purchase?->proveedor?->nombre ?? "Cuenta #{$apId}";
                }
            }
        }

        $unicos = array_unique(array_filter($nombres));

        return count($unicos) > 1 ? 'Varios' : ($unicos[0] ?? '—');
    }

    private function calcularTipo(array $destinos): string
    {
        $tieneCuenta = false;
        $tieneGasto = false;
        foreach ($destinos as $d) {
            $type = $d['type'] ?? ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR;
            if ($type === ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR) {
                $tieneCuenta = true;
            } else {
                $tieneGasto = true;
            }
        }

        return ($tieneCuenta && $tieneGasto) ? ComprobanteEgreso::TYPE_MIXTO
            : ($tieneGasto ? ComprobanteEgreso::TYPE_GASTO_DIRECTO : ComprobanteEgreso::TYPE_PAGO_CUENTA);
    }

    private function descripcionMovimiento(ComprobanteEgreso $comprobante, array $destinos): string
    {
        $partes = [];
        foreach ($destinos as $d) {
            $type = $d['type'] ?? ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR;
            $amount = (float) ($d['amount'] ?? 0);
            if ($type === ComprobanteEgresoDestino::TYPE_GASTO_DIRECTO) {
                $partes[] = ($d['concepto'] ?? 'Gasto') . ': ' . number_format($amount, 2);
            } else {
                $apId = (int) ($d['account_payable_id'] ?? 0);
                $ap = $apId ? AccountPayable::with('purchase')->find($apId) : null;
                $compraId = $ap?->purchase?->id ?? $apId;
                $partes[] = "Compra #{$compraId}: " . number_format($amount, 2);
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
        $accountPayable = $accountPayable->lockForUpdate()->fresh();
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
