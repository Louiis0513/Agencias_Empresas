<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteEgresoDestino;
use App\Models\Store;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class AccountPayableService
{
    public function __construct(
        protected CajaService $cajaService,
        protected ComprobanteEgresoService $comprobanteEgresoService
    ) {}

    /**
     * Registra un abono a una CxP.
     * Crea un ComprobanteEgreso internamente (adapter para compatibilidad con Compras).
     */
    public function registrarPago(Store $store, int $accountPayableId, int $userId, array $data): ComprobanteEgreso
    {
        $accountPayable = AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->with('purchase')
            ->firstOrFail();

        if ($accountPayable->isPagado()) {
            throw new Exception('Esta CxP ya está pagada.');
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

        $destinos = [
            [
                'type' => ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR,
                'account_payable_id' => $accountPayableId,
                'amount' => $totalAmount,
            ],
        ];

        $origenes = [];
        foreach ($parts as $p) {
            $amount = (float) ($p['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $bolsilloId = (int) ($p['bolsillo_id'] ?? 0);
            if (! $bolsilloId) {
                throw new Exception('Debe especificar el bolsillo para cada parte del pago.');
            }
            $origenes[] = [
                'bolsillo_id' => $bolsilloId,
                'amount' => $amount,
                'reference' => $p['reference'] ?? null,
                'payment_method' => $p['payment_method'] ?? null,
            ];
        }

        if (empty($origenes)) {
            throw new Exception('Debe indicar al menos un bolsillo con monto.');
        }

        $comprobanteData = [
            'proveedor_id' => $accountPayable->tercero_id ?? $accountPayable->purchase?->tercero_id,
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'destinos' => $destinos,
            'origenes' => $origenes,
        ];

        return $this->comprobanteEgresoService->crearComprobante($store, $userId, $comprobanteData);
    }

    /**
     * CxP sin compra (ej. cuenta de cobro de trabajador / prestador).
     */
    public function registrarCuentaPorPagarManual(Store $store, array $data): AccountPayable
    {
        $currency = $store->currency ?? 'COP';

        return DB::transaction(function () use ($store, $data, $currency) {
            $total = parse_money($data['total_amount'] ?? 0, $currency);
            if ($total <= 0) {
                throw new Exception('El monto total debe ser mayor a cero.');
            }

            return AccountPayable::create([
                'store_id' => $store->id,
                'purchase_id' => null,
                'tercero_id' => $data['tercero_id'] ?? $data['proveedor_id'] ?? null,
                'source' => AccountPayable::SOURCE_MANUAL,
                'creditor_name' => trim((string) $data['creditor_name']),
                'creditor_document' => filled($data['creditor_document'] ?? null) ? trim((string) $data['creditor_document']) : null,
                'document_reference' => filled($data['document_reference'] ?? null) ? trim((string) $data['document_reference']) : null,
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'total_amount' => $total,
                'balance' => $total,
                'due_date' => filled($data['due_date'] ?? null) ? $data['due_date'] : null,
                'status' => AccountPayable::STATUS_PENDIENTE,
            ]);
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

        if (array_key_exists('proveedor_id', $filtros)) {
            if ($filtros['proveedor_id'] === null || $filtros['proveedor_id'] === '') {
                $query->where(function ($q) {
                    $q->where('source', AccountPayable::SOURCE_MANUAL)
                        ->orWhereNull('tercero_id');
                });
            } else {
                $query->where('tercero_id', $filtros['proveedor_id']);
            }
        }

        if (! empty($filtros['fecha_vencimiento_desde'])) {
            $query->whereDate('due_date', '>=', $filtros['fecha_vencimiento_desde']);
        }

        if (! empty($filtros['fecha_vencimiento_hasta'])) {
            $query->whereDate('due_date', '<=', $filtros['fecha_vencimiento_hasta']);
        }

        if (! empty($filtros['exclude_ids'])) {
            $ids = is_array($filtros['exclude_ids']) ? $filtros['exclude_ids'] : [$filtros['exclude_ids']];
            $query->whereNotIn('id', array_filter(array_map('intval', $ids)));
        }

        if (! empty($filtros['search'])) {
            $term = trim($filtros['search']);
            $query->where(function ($q) use ($term) {
                $q->whereHas('purchase', function ($sub) use ($term) {
                    if (is_numeric($term)) {
                        $sub->where('id', (int) $term)
                            ->orWhere('invoice_number', 'like', "%{$term}%");
                    } else {
                        $sub->where('invoice_number', 'like', "%{$term}%");
                    }
                })
                    ->orWhereHas('purchase.proveedor', function ($sub) use ($term) {
                        $sub->where('nombre', 'like', "%{$term}%")
                            ->orWhere('numero_identificacion', 'like', "%{$term}%");
                    })
                    ->orWhere(function ($q2) use ($term) {
                        $q2->where('source', AccountPayable::SOURCE_MANUAL)
                            ->where(function ($q3) use ($term) {
                                $q3->where('creditor_name', 'like', "%{$term}%")
                                    ->orWhere('creditor_document', 'like', "%{$term}%")
                                    ->orWhere('document_reference', 'like', "%{$term}%")
                                    ->orWhere('description', 'like', "%{$term}%");
                                if (is_numeric($term)) {
                                    $q3->orWhere('id', (int) $term);
                                }
                            });
                    });
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $page = $filtros['page'] ?? null;

        $paginator = $page !== null
            ? $query->paginate($perPage, ['*'], 'page', $page)
            : $query->paginate($perPage);

        return $paginator->withQueryString();
    }

    /**
     * Sin paginar para exportación Excel (filtros alineados con {@see listarCuentasPorPagar} + período por vencimiento o alta).
     *
     * @param  array{status?: string, proveedor_id?: int|string|null, search?: string, fecha_desde?: string, fecha_hasta?: string, timezone?: string, fecha_vencimiento_desde?: string, fecha_vencimiento_hasta?: string}  $filtros
     * @return EloquentCollection<int, AccountPayable>
     */
    public function coleccionParaExportacionCuentasPorPagar(Store $store, array $filtros = []): EloquentCollection
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

        if (array_key_exists('proveedor_id', $filtros)) {
            if ($filtros['proveedor_id'] === null || $filtros['proveedor_id'] === '') {
                $query->where(function ($q) {
                    $q->where('source', AccountPayable::SOURCE_MANUAL)
                        ->orWhereNull('tercero_id');
                });
            } else {
                $query->where('tercero_id', $filtros['proveedor_id']);
            }
        }

        if (! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta'])) {
            $tz = ! empty($filtros['timezone']) ? (string) $filtros['timezone'] : (string) config('app.timezone');
            $desde = (string) $filtros['fecha_desde'];
            $hasta = (string) $filtros['fecha_hasta'];
            $startUtc = Carbon::parse($desde, $tz)->startOfDay()->utc();
            $endUtc = Carbon::parse($hasta, $tz)->endOfDay()->utc();

            $query->where(function ($q) use ($desde, $hasta, $startUtc, $endUtc) {
                $q->whereBetween('due_date', [$desde, $hasta])
                    ->orWhere(function ($q2) use ($startUtc, $endUtc) {
                        $q2->whereNull('due_date')
                            ->whereBetween('created_at', [$startUtc, $endUtc]);
                    });
            });
        } elseif (! empty($filtros['fecha_vencimiento_desde'])) {
            $query->whereDate('due_date', '>=', $filtros['fecha_vencimiento_desde']);
        }

        if (! empty($filtros['fecha_vencimiento_hasta'])) {
            $query->whereDate('due_date', '<=', $filtros['fecha_vencimiento_hasta']);
        }

        if (! empty($filtros['search'])) {
            $term = trim((string) $filtros['search']);
            $query->where(function ($q) use ($term) {
                $q->whereHas('purchase', function ($sub) use ($term) {
                    if (is_numeric($term)) {
                        $sub->where('id', (int) $term)
                            ->orWhere('invoice_number', 'like', "%{$term}%");
                    } else {
                        $sub->where('invoice_number', 'like', "%{$term}%");
                    }
                })
                    ->orWhereHas('purchase.proveedor', function ($sub) use ($term) {
                        $sub->where('nombre', 'like', "%{$term}%")
                            ->orWhere('numero_identificacion', 'like', "%{$term}%");
                    })
                    ->orWhere(function ($q2) use ($term) {
                        $q2->where('source', AccountPayable::SOURCE_MANUAL)
                            ->where(function ($q3) use ($term) {
                                $q3->where('creditor_name', 'like', "%{$term}%")
                                    ->orWhere('creditor_document', 'like', "%{$term}%")
                                    ->orWhere('document_reference', 'like', "%{$term}%")
                                    ->orWhere('description', 'like', "%{$term}%");
                                if (is_numeric($term)) {
                                    $q3->orWhere('id', (int) $term);
                                }
                            });
                    });
            });
        }

        return $query->get();
    }

    public function obtenerCuentaPorPagar(Store $store, int $accountPayableId): AccountPayable
    {
        return AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->with(['purchase.details.product', 'purchase.proveedor', 'comprobanteDestinos.comprobanteEgreso.origenes.bolsillo'])
            ->firstOrFail();
    }

    /**
     * Revierte un comprobante de egreso (delega a ComprobanteEgresoService).
     */
    public function reversarPago(Store $store, int $accountPayableId, int $comprobanteEgresoId, int $userId): void
    {
        $accountPayable = AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $destino = $accountPayable->comprobanteDestinos()
            ->where('comprobante_egreso_id', $comprobanteEgresoId)
            ->firstOrFail();

        $this->comprobanteEgresoService->reversar($store, $comprobanteEgresoId, $userId);
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
