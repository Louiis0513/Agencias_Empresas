<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\AccountPayablePayment;
use App\Models\AccountPayablePaymentPart;
use App\Models\MovimientoBolsillo;
use App\Models\Purchase;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountPayableService
{
    public function __construct(
        protected CajaService $cajaService
    ) {}

    /**
     * Registra un abono a una cuenta por pagar.
     * Crea EGRESO en cada bolsillo usado (integridad financiera).
     */
    public function registrarPago(Store $store, int $accountPayableId, int $userId, array $data): AccountPayablePayment
    {
        return DB::transaction(function () use ($store, $accountPayableId, $userId, $data) {
            $accountPayable = AccountPayable::where('id', $accountPayableId)
                ->where('store_id', $store->id)
                ->with('purchase')
                ->lockForUpdate()
                ->firstOrFail();

            if ($accountPayable->isPagado()) {
                throw new Exception('Esta cuenta por pagar ya está pagada.');
            }

            $parts = $data['parts'] ?? [];
            $totalAmount = 0;
            foreach ($parts as $p) {
                $totalAmount += (float) ($p['amount'] ?? 0);
            }

            if ($totalAmount <= 0) {
                throw new Exception('El monto del pago debe ser mayor a cero.');
            }

            if ($totalAmount > $accountPayable->balance) {
                throw new Exception("El monto del pago ({$totalAmount}) no puede exceder el saldo pendiente ({$accountPayable->balance}).");
            }

            $payment = AccountPayablePayment::create([
                'store_id' => $store->id,
                'account_payable_id' => $accountPayable->id,
                'amount' => $totalAmount,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'user_id' => $userId,
            ]);

            foreach ($parts as $p) {
                $amount = (float) ($p['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $bolsilloId = (int) ($p['bolsillo_id'] ?? 0);
                if (! $bolsilloId) {
                    throw new Exception('Debe especificar el bolsillo para cada parte del pago.');
                }

                AccountPayablePaymentPart::create([
                    'account_payable_payment_id' => $payment->id,
                    'bolsillo_id' => $bolsilloId,
                    'amount' => $amount,
                ]);

                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id' => $bolsilloId,
                    'type' => \App\Models\MovimientoBolsillo::TYPE_EXPENSE,
                    'amount' => $amount,
                    'payment_method' => $p['payment_method'] ?? null,
                    'description' => "Pago cuenta por pagar - Compra #{$accountPayable->purchase->id}",
                    'account_payable_payment_id' => $payment->id,
                ]);
            }

            $nuevoBalance = $accountPayable->balance - $totalAmount;
            $nuevoStatus = $nuevoBalance <= 0
                ? AccountPayable::STATUS_PAGADO
                : AccountPayable::STATUS_PARCIAL;

            $accountPayable->update([
                'balance' => max(0, $nuevoBalance),
                'status' => $nuevoStatus,
            ]);

            // Sincronizar estado de pago en la compra cuando la deuda queda saldada
            if ($nuevoStatus === AccountPayable::STATUS_PAGADO) {
                $accountPayable->purchase->update(['payment_status' => \App\Models\Purchase::PAYMENT_PAGADO]);
            }

            return $payment->load(['parts.bolsillo', 'accountPayable.purchase']);
        });
    }

    public function listarCuentasPorPagar(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = AccountPayable::deTienda($store->id)
            ->with(['purchase.proveedor', 'purchase.user'])
            ->orderBy('due_date');

        if (isset($filtros['status']) && $filtros['status'] !== '') {
            if ($filtros['status'] === 'pendientes') {
                $query->pendientes();
            } else {
                $query->where('status', $filtros['status']);
            }
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function obtenerCuentaPorPagar(Store $store, int $accountPayableId): AccountPayable
    {
        return AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->with(['purchase.details.product', 'purchase.proveedor', 'payments.parts.bolsillo'])
            ->firstOrFail();
    }

    /**
     * Revierte un pago registrado (estilo bancario).
     * Crea movimientos de INGRESO por cada bolsillo (trazabilidad completa).
     * Restaura el saldo de la cuenta por pagar y actualiza estados.
     * No elimina el pago ni los movimientos originales.
     */
    public function reversarPago(Store $store, int $accountPayableId, int $paymentId, int $userId): void
    {
        DB::transaction(function () use ($store, $accountPayableId, $paymentId, $userId) {
            $accountPayable = AccountPayable::where('id', $accountPayableId)
                ->where('store_id', $store->id)
                ->with('purchase')
                ->lockForUpdate()
                ->firstOrFail();

            $payment = AccountPayablePayment::where('id', $paymentId)
                ->where('account_payable_id', $accountPayable->id)
                ->with('parts.bolsillo')
                ->firstOrFail();

            if ($payment->isReversed()) {
                throw new Exception('Este pago ya fue revertido.');
            }

            $eraPagado = $accountPayable->isPagado();
            $montoPago = (float) $payment->amount;
            $compraId = $accountPayable->purchase->id;

            // Crear movimientos de INGRESO (reversa) en cada bolsillo - trazabilidad bancaria
            foreach ($payment->parts as $part) {
                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id'                           => $part->bolsillo_id,
                    'type'                                  => MovimientoBolsillo::TYPE_INCOME,
                    'amount'                                => $part->amount,
                    'description'                           => "Reversa de pago - Cuenta por pagar Compra #{$compraId}",
                    'reversal_of_account_payable_payment_id' => $payment->id,
                ]);
            }

            // Restaurar saldo en cuenta por pagar
            $nuevoBalance = $accountPayable->balance + $montoPago;
            // Lógica correcta: balance=0→PAGADO, balance=total→PENDIENTE, 0<balance<total→PARCIAL
            $nuevoStatus = $nuevoBalance <= 0
                ? AccountPayable::STATUS_PAGADO
                : ($nuevoBalance >= $accountPayable->total_amount
                    ? AccountPayable::STATUS_PENDIENTE
                    : AccountPayable::STATUS_PARCIAL);

            $accountPayable->update([
                'balance' => $nuevoBalance,
                'status'  => $nuevoStatus,
            ]);

            // Si la cuenta estaba pagada, la compra vuelve a pendiente
            if ($eraPagado) {
                $accountPayable->purchase->update(['payment_status' => Purchase::PAYMENT_PENDIENTE]);
            }

            // Marcar pago como revertido (no se elimina - trazabilidad)
            $payment->update([
                'reversed_at'      => now(),
                'reversal_user_id' => $userId,
            ]);
        });
    }

    /**
     * Deuda total pendiente de la tienda.
     */
    public function deudaTotal(Store $store): float
    {
        return (float) AccountPayable::deTienda($store->id)
            ->pendientes()
            ->sum('balance');
    }
}
