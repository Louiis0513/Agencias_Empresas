<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\AccountReceivableCuota;
use App\Models\Invoice;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountReceivableService
{
    /**
     * Crea la cuenta por cobrar (y cuotas) a partir de una factura a crédito.
     * Una factura PENDING genera 1 cuenta por cobrar. Las cuotas definen los vencimientos.
     *
     * @param  array  $cuotas  [['amount' => float, 'due_date' => 'Y-m-d'], ...]. Si vacío, se crea una sola cuota = total con due_date.
     */
    public function crearDesdeFactura(Store $store, Invoice $invoice, ?string $due_date = null, array $cuotas = []): AccountReceivable
    {
        if ($invoice->store_id !== $store->id) {
            throw new Exception('La factura no pertenece a esta tienda.');
        }
        if ($invoice->status !== 'PENDING') {
            throw new Exception('Solo se crea cuenta por cobrar para facturas en estado PENDING.');
        }

        $total = (float) $invoice->total;
        if ($total <= 0) {
            throw new Exception('El total de la factura debe ser mayor a cero.');
        }

        if (AccountReceivable::where('invoice_id', $invoice->id)->exists()) {
            throw new Exception('Esta factura ya tiene una cuenta por cobrar.');
        }

        return DB::transaction(function () use ($store, $invoice, $due_date, $cuotas, $total) {
            $account = AccountReceivable::create([
                'store_id' => $store->id,
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'total_amount' => $total,
                'balance' => $total,
                'due_date' => $due_date ? \Carbon\Carbon::parse($due_date) : null,
                'status' => AccountReceivable::STATUS_PENDIENTE,
            ]);

            if (count($cuotas) > 0) {
                $sumCuotas = 0;
                foreach ($cuotas as $i => $c) {
                    $amount = (float) ($c['amount'] ?? 0);
                    $date = $c['due_date'] ?? $due_date ?? now()->toDateString();
                    if ($amount <= 0) {
                        continue;
                    }
                    AccountReceivableCuota::create([
                        'account_receivable_id' => $account->id,
                        'sequence' => $i + 1,
                        'amount' => $amount,
                        'amount_paid' => 0,
                        'due_date' => $date,
                    ]);
                    $sumCuotas += $amount;
                }
                if (abs($sumCuotas - $total) > 0.01) {
                    throw new Exception("La suma de las cuotas ({$sumCuotas}) debe coincidir con el total de la factura ({$total}).");
                }
            } else {
                // Una sola cuota = total
                AccountReceivableCuota::create([
                    'account_receivable_id' => $account->id,
                    'sequence' => 1,
                    'amount' => $total,
                    'amount_paid' => 0,
                    'due_date' => $due_date ?? now()->addDays(30)->toDateString(),
                ]);
            }

            return $account->load('cuotas');
        });
    }

    public function listar(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = AccountReceivable::deTienda($store->id)
            ->with(['invoice', 'customer', 'cuotas'])
            ->orderByDesc('created_at');

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }
        if (! empty($filtros['customer_id'])) {
            $query->where('customer_id', $filtros['customer_id']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function obtener(Store $store, int $id): AccountReceivable
    {
        return AccountReceivable::deTienda($store->id)
            ->with(['invoice.details.product', 'customer', 'cuotas', 'comprobanteIngresoAplicaciones.comprobanteIngreso.destinos.bolsillo'])
            ->findOrFail($id);
    }

    /** Saldo total pendiente de cobro de la tienda. */
    public function saldoPendienteTotal(Store $store): float
    {
        return (float) AccountReceivable::deTienda($store->id)
            ->whereIn('status', [AccountReceivable::STATUS_PENDIENTE, AccountReceivable::STATUS_PARCIAL])
            ->sum('balance');
    }
}
