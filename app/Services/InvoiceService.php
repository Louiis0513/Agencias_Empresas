<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Store;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InvoiceService
{
    /**
     * Obtiene el rango de fechas para filtrar facturas (últimos 31 días por defecto).
     *
     * @return array{fecha_desde: Carbon, fecha_hasta: Carbon}
     */
    public function getRangoFechasPorDefecto(): array
    {
        $fechaHasta = now();
        $fechaDesde = now()->subDays(30);

        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ];
    }

    /**
     * Lista facturas con filtros opcionales y paginación.
     * Por defecto muestra solo las facturas de los últimos 31 días.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarFacturas(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = Invoice::deTienda($store->id)
            ->with(['user:id,name,email', 'customer:id,name,email', 'details']);

        $this->aplicarFiltrosListadoFacturas($query, $filtros);

        $perPage = $filtros['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Misma lógica de filtros que {@see listarFacturas}, sin paginación (exportación Excel).
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Invoice>
     */
    public function listarFacturasParaExportacion(Store $store, array $filtros = []): Collection
    {
        $query = Invoice::deTienda($store->id)
            ->with(['user:id,name,email', 'customer:id,name,email']);

        $this->aplicarFiltrosListadoFacturas($query, $filtros);

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  array<string, mixed>  $filtros
     */
    protected function aplicarFiltrosListadoFacturas(Builder $query, array $filtros): void
    {
        if (isset($filtros['fecha_desde']) && isset($filtros['fecha_hasta'])) {
            $desde = Carbon::parse($filtros['fecha_desde'])->startOfDay();
            $hasta = Carbon::parse($filtros['fecha_hasta'])->endOfDay();
            $query->whereBetween('created_at', [$desde, $hasta]);
        } else {
            $rango = $this->getRangoFechasPorDefecto();
            $query->whereBetween('created_at', [
                $rango['fecha_desde']->copy()->startOfDay(),
                $rango['fecha_hasta']->copy()->endOfDay(),
            ]);
        }

        if (isset($filtros['status']) && ! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (isset($filtros['customer_id']) && ! empty($filtros['customer_id'])) {
            $query->where('tercero_id', $filtros['customer_id']);
        }

        if (isset($filtros['payment_method']) && $filtros['payment_method'] !== '') {
            if (in_array(strtoupper((string) $filtros['payment_method']), ['NULL', 'SIN_METODO'], true)) {
                $query->whereNull('payment_method');
            } else {
                $query->where('payment_method', $filtros['payment_method']);
            }
        }

        if (isset($filtros['bolsillo_id']) && (int) $filtros['bolsillo_id'] > 0) {
            $bolsilloId = (int) $filtros['bolsillo_id'];
            $query->where(function ($q) use ($bolsilloId) {
                $q->whereHas('comprobantesIngresoDirectos', function ($sub) use ($bolsilloId) {
                    $sub->whereHas('destinos', fn ($d) => $d->where('bolsillo_id', $bolsilloId));
                })->orWhereHas('accountReceivable', function ($sub) use ($bolsilloId) {
                    $sub->whereHas('comprobanteIngresoAplicaciones', function ($sub2) use ($bolsilloId) {
                        $sub2->whereHas('comprobanteIngreso', function ($sub3) use ($bolsilloId) {
                            $sub3->whereHas('destinos', fn ($d) => $d->where('bolsillo_id', $bolsilloId));
                        });
                    });
                });
            });
        }

        if (isset($filtros['search']) && ! empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }
    }

    /**
     * Obtiene una factura específica por ID, validando que pertenezca a la tienda.
     *
     * @throws Exception Si la factura no existe o no pertenece a la tienda
     */
    public function obtenerFactura(Store $store, int $invoiceId): Invoice
    {
        $factura = Invoice::deTienda($store->id)
            ->with([
                'details.product',
                'customer',
                'user',
                'accountReceivable.comprobanteIngresoAplicaciones.comprobanteIngreso',
                'comprobantesIngresoDirectos',
            ])
            ->find($invoiceId);

        if (! $factura) {
            throw new Exception("La factura #{$invoiceId} no existe o no pertenece a esta tienda.");
        }

        return $factura;
    }

    /**
     * Anula una factura marcándola como VOID.
     * Solo cambia el estado; no modifica inventario.
     *
     * @throws Exception Si la factura no existe, no pertenece a la tienda o ya está anulada
     */
    public function anularFactura(Store $store, int $invoiceId): Invoice
    {
        $factura = Invoice::deTienda($store->id)->find($invoiceId);

        if (! $factura) {
            throw new Exception("La factura #{$invoiceId} no existe o no pertenece a esta tienda.");
        }

        if ($factura->status === 'VOID') {
            throw new Exception('La factura ya está anulada.');
        }

        $factura->status = 'VOID';
        $factura->save();

        return $factura;
    }
}
