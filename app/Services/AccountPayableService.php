<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteEgresoDestino;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountPayableService
{
    public function __construct(
        protected CajaService $cajaService,
        protected ComprobanteEgresoService $comprobanteEgresoService
    ) {}

    /**
     * Registra un abono a una cuenta por pagar.
     * Crea un ComprobanteEgreso internamente (adapter para compatibilidad con Compras).
     */
    public function registrarPago(Store $store, int $accountPayableId, int $userId, array $data): ComprobanteEgreso
    {
        $accountPayable = AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->with('purchase')
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
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'destinos' => $destinos,
            'origenes' => $origenes,
        ];

        return $this->comprobanteEgresoService->crearComprobante($store, $userId, $comprobanteData);
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

        if (! empty($filtros['proveedor_id'])) {
            $query->whereHas('purchase', fn ($q) => $q->where('proveedor_id', $filtros['proveedor_id']));
        }

        if (! empty($filtros['fecha_vencimiento_desde'])) {
            $query->whereDate('due_date', '>=', $filtros['fecha_vencimiento_desde']);
        }

        if (! empty($filtros['fecha_vencimiento_hasta'])) {
            $query->whereDate('due_date', '<=', $filtros['fecha_vencimiento_hasta']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $page = $filtros['page'] ?? null;

        return $page !== null
            ? $query->paginate($perPage, ['*'], 'page', $page)
            : $query->paginate($perPage);
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
