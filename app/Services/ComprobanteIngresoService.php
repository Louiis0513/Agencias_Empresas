<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\AccountReceivableCuota;
use App\Models\ComprobanteIngreso;
use App\Models\ComprobanteIngresoAplicacion;
use App\Models\ComprobanteIngresoDestino;
use App\Models\Invoice;
use App\Models\MovimientoBolsillo;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ComprobanteIngresoService
{
    public function __construct(
        protected CajaService $cajaService,
        protected InvoiceService $invoiceService
    ) {}

    public function siguienteNumero(Store $store): string
    {
        $count = ComprobanteIngreso::deTienda($store->id)->count();

        return 'CI-' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Crea un comprobante de ingreso.
     * - destinos: [['bolsillo_id' => int, 'amount' => float, 'reference' => ?string], ...]
     * - aplicaciones (si es cobro): [['account_receivable_id' => int, 'amount' => float], ...]
     * - invoice_id (opcional): si viene, el comprobante es tipo PAGO_FACTURA y se asocia a la factura.
     * Si hay aplicaciones, reduce balance de cada cuenta y aplica a cuotas (FIFO por due_date).
     */
    public function crearComprobante(Store $store, int $userId, array $data): ComprobanteIngreso
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $destinos = $data['destinos'] ?? [];
            $aplicaciones = $data['aplicaciones'] ?? [];
            $invoiceId = isset($data['invoice_id']) ? (int) $data['invoice_id'] : null;

            $totalDestinos = array_sum(array_map(fn ($d) => (float) ($d['amount'] ?? 0), $destinos));
            if ($totalDestinos <= 0) {
                throw new Exception('Debe indicar al menos un destino (bolsillo) con monto mayor a cero.');
            }

            $totalAplicaciones = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $aplicaciones));
            if (count($aplicaciones) > 0 && abs($totalAplicaciones - $totalDestinos) > 0.01) {
                throw new Exception("La suma de aplicaciones a cuentas por cobrar ({$totalAplicaciones}) debe coincidir con el total del ingreso ({$totalDestinos}).");
            }

            $type = ComprobanteIngreso::TYPE_INGRESO_MANUAL;
            $customerId = $data['customer_id'] ?? null;

            if ($invoiceId > 0) {
                $type = ComprobanteIngreso::TYPE_PAGO_FACTURA;
                $factura = Invoice::where('id', $invoiceId)->where('store_id', $store->id)->firstOrFail();
                if ($customerId === null) {
                    $customerId = $factura->customer_id;
                }
            } elseif (count($aplicaciones) > 0) {
                $type = ComprobanteIngreso::TYPE_COBRO_CUENTA;
                if (count($aplicaciones) === 1 && ! $customerId) {
                    $ar = AccountReceivable::where('id', $aplicaciones[0]['account_receivable_id'])->where('store_id', $store->id)->first();
                    if ($ar) {
                        $customerId = $ar->customer_id;
                    }
                }
            }

            $comprobanteData = [
                'store_id' => $store->id,
                'number' => $this->siguienteNumero($store),
                'total_amount' => $totalDestinos,
                'date' => $data['date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'type' => $type,
                'customer_id' => $customerId,
                'user_id' => $userId,
            ];
            if ($invoiceId > 0) {
                $comprobanteData['invoice_id'] = $invoiceId;
            }

            $comprobante = ComprobanteIngreso::create($comprobanteData);

            foreach ($destinos as $d) {
                $amount = (float) ($d['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $bolsilloId = (int) ($d['bolsillo_id'] ?? 0);
                if (! $bolsilloId) {
                    throw new Exception('Debe indicar bolsillo_id para cada destino.');
                }

                ComprobanteIngresoDestino::create([
                    'comprobante_ingreso_id' => $comprobante->id,
                    'bolsillo_id' => $bolsilloId,
                    'amount' => $amount,
                    'reference' => $d['reference'] ?? null,
                ]);

                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id' => $bolsilloId,
                    'type' => MovimientoBolsillo::TYPE_INCOME,
                    'amount' => $amount,
                    'description' => 'Comprobante de ingreso ' . $comprobante->number,
                    'comprobante_ingreso_id' => $comprobante->id,
                ]);
            }

            foreach ($aplicaciones as $ap) {
                $accountReceivableId = (int) ($ap['account_receivable_id'] ?? 0);
                $amount = (float) ($ap['amount'] ?? 0);
                if ($accountReceivableId <= 0 || $amount <= 0) {
                    continue;
                }

                ComprobanteIngresoAplicacion::create([
                    'comprobante_ingreso_id' => $comprobante->id,
                    'account_receivable_id' => $accountReceivableId,
                    'amount' => $amount,
                ]);

                $this->aplicarCobroACuentaPorCobrar($store, $accountReceivableId, $amount);
            }

            return $comprobante->load(['destinos.bolsillo', 'aplicaciones.accountReceivable', 'invoice']);
        });
    }

    /**
     * Crea un comprobante de ingreso por pago de factura (tipo PAGO_FACTURA).
     *
     * @deprecated Usar crearComprobante() con invoice_id, notes y destinos en $data.
     * @param  array  $payments  [ ['payment_method' => 'CASH'|'CARD'|'TRANSFER', 'amount' => float, 'bolsillo_id' => int ], ... ]
     */
    public function crearComprobantePorPagoFactura(Store $store, int $userId, Invoice $factura, array $payments): ComprobanteIngreso
    {
        $destinos = [];
        foreach ($payments as $p) {
            $amount = (float) ($p['amount'] ?? 0);
            $bolsilloId = (int) ($p['bolsillo_id'] ?? 0);
            if ($amount <= 0 || ! $bolsilloId) {
                continue;
            }
            $destinos[] = [
                'bolsillo_id' => $bolsilloId,
                'amount' => $amount,
                'reference' => null,
            ];
        }
        if (empty($destinos)) {
            throw new Exception('Debe indicar al menos un pago (bolsillo y monto mayor a cero).');
        }

        return $this->crearComprobante($store, $userId, [
            'invoice_id' => $factura->id,
            'notes' => 'Pago Factura #' . $factura->id,
            'destinos' => $destinos,
        ]);
    }

    /**
     * Reduce el balance de la cuenta por cobrar y aplica el monto a las cuotas (FIFO por due_date).
     */
    protected function aplicarCobroACuentaPorCobrar(Store $store, int $accountReceivableId, float $amount): void
    {
        $account = AccountReceivable::where('id', $accountReceivableId)
            ->where('store_id', $store->id)
            ->with('cuotas')
            ->lockForUpdate()
            ->firstOrFail();

        if ($account->isPagado()) {
            throw new Exception('La cuenta por cobrar ya está saldada.');
        }

        $balance = (float) $account->balance;
        if ($amount > $balance) {
            throw new Exception("El monto a aplicar ({$amount}) no puede ser mayor al saldo pendiente ({$balance}).");
        }

        $remaining = $amount;
        $cuotas = $account->cuotas->sortBy('due_date');

        foreach ($cuotas as $cuota) {
            if ($remaining <= 0) {
                break;
            }
            $pending = (float) $cuota->amount - (float) $cuota->amount_paid;
            if ($pending <= 0) {
                continue;
            }
            $aplicar = min($remaining, $pending);
            $cuota->increment('amount_paid', $aplicar);
            $remaining -= $aplicar;
        }

        $newBalance = max(0, (float) $account->balance - $amount);
        $account->balance = $newBalance;
        $account->status = $newBalance <= 0 ? AccountReceivable::STATUS_PAGADO : AccountReceivable::STATUS_PARCIAL;
        $account->save();

        // En cada abono actualizar siempre el método de pago de la factura según los bolsillos usados en todas las aplicaciones.
        $invoice = $account->invoice;
        $bolsilloIds = $account->comprobanteIngresoAplicaciones()
            ->with('comprobanteIngreso.destinos')
            ->get()
            ->flatMap(fn (ComprobanteIngresoAplicacion $a) => $a->comprobanteIngreso->destinos->pluck('bolsillo_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();
        $paymentMethod = $this->invoiceService->derivarMetodoPagoDesdeBolsillos($store, $bolsilloIds) ?? 'CASH';
        $invoice->update(array_filter([
            'payment_method' => $paymentMethod,
            'status' => $newBalance <= 0 ? 'PAID' : null,
        ]));
    }

    public function listar(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = ComprobanteIngreso::deTienda($store->id)
            ->with(['customer', 'destinos.bolsillo', 'aplicaciones.accountReceivable.invoice'])
            ->orderByDesc('created_at');

        if (! empty($filtros['type'])) {
            $query->where('type', $filtros['type']);
        }
        if (! empty($filtros['customer_id'])) {
            $query->where('customer_id', $filtros['customer_id']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function obtener(Store $store, int $id): ComprobanteIngreso
    {
        return ComprobanteIngreso::deTienda($store->id)
            ->with(['customer', 'user', 'destinos.bolsillo', 'aplicaciones.accountReceivable.invoice'])
            ->findOrFail($id);
    }
}
