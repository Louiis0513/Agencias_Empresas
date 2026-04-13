<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupportDocument;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductPurchasesBandejaService
{
    public const DOC_TYPE_ALL = 'all';

    public const DOC_TYPE_PURCHASES = 'purchases';

    public const DOC_TYPE_SUPPORT_DOCUMENTS = 'support_documents';

    public function __construct(
        protected PurchaseService $purchaseService,
        protected SupportDocumentService $supportDocumentService
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function listar(Store $store, array $query): LengthAwarePaginator
    {
        $docType = $query['doc_type'] ?? self::DOC_TYPE_ALL;
        if (! in_array($docType, [self::DOC_TYPE_ALL, self::DOC_TYPE_PURCHASES, self::DOC_TYPE_SUPPORT_DOCUMENTS], true)) {
            $docType = self::DOC_TYPE_ALL;
        }

        $filtrosCompras = $this->filtrosComprasProducto($query);
        $filtrosSoporte = $this->filtrosDocumentoSoporte($query);

        if ($docType === self::DOC_TYPE_PURCHASES) {
            return $this->purchaseService->listarCompras($store, $filtrosCompras)
                ->through(fn (Purchase $p) => $this->mapPurchaseRow($store, $p))
                ->withQueryString();
        }

        if ($docType === self::DOC_TYPE_SUPPORT_DOCUMENTS) {
            return $this->supportDocumentService->listarDocumentos($store, $filtrosSoporte)
                ->through(fn (SupportDocument $d) => $this->mapSupportDocumentRow($store, $d))
                ->withQueryString();
        }

        return $this->listarUnion($store, $filtrosCompras, $filtrosSoporte, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function filtrosComprasProducto(array $query): array
    {
        return [
            'status' => $query['status'] ?? null,
            'payment_status' => $query['payment_status'] ?? null,
            'proveedor_nombre' => $query['proveedor_nombre'] ?? null,
            'fecha_desde' => $query['fecha_desde'] ?? null,
            'fecha_hasta' => $query['fecha_hasta'] ?? null,
            'purchase_type' => Purchase::TYPE_PRODUCTO,
            'per_page' => (int) ($query['per_page'] ?? 15),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function filtrosDocumentoSoporte(array $query): array
    {
        return [
            'status' => $query['status'] ?? null,
            'payment_status' => $query['payment_status'] ?? null,
            'proveedor_nombre' => $query['proveedor_nombre'] ?? null,
            'fecha_desde' => $query['fecha_desde'] ?? null,
            'fecha_hasta' => $query['fecha_hasta'] ?? null,
            'per_page' => (int) ($query['per_page'] ?? 15),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtrosCompras
     * @param  array<string, mixed>  $filtrosSoporte
     * @param  array<string, mixed>  $query
     */
    protected function listarUnion(Store $store, array $filtrosCompras, array $filtrosSoporte, array $query): LengthAwarePaginator
    {
        $perPage = max(1, (int) ($filtrosCompras['per_page'] ?? 15));
        $currentPage = max(1, (int) Paginator::resolveCurrentPage());

        $purchasesSql = $this->comprasUnionSubquery($store->id, $filtrosCompras);
        $supportSql = $this->documentosUnionSubquery($store->id, $filtrosSoporte);

        $unionBindings = array_merge($purchasesSql['bindings'], $supportSql['bindings']);

        $countSql = 'select count(*) as aggregate from ('.$purchasesSql['sql'].' union all '.$supportSql['sql'].') as bandeja_union';
        $total = (int) DB::selectOne($countSql, $unionBindings)->aggregate;

        $orderedSql = 'select * from ('.$purchasesSql['sql'].' union all '.$supportSql['sql'].') as bandeja_union order by created_at desc';
        $offset = ($currentPage - 1) * $perPage;
        $pageSql = $orderedSql.' limit '.(int) $perPage.' offset '.(int) $offset;

        $rawRows = DB::select($pageSql, $unionBindings);

        $items = Collection::make($rawRows)
            ->map(fn ($row) => $this->mapUnionDbRow($store, $row));

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return $paginator->withQueryString();
    }

    /**
     * @return array{sql: string, bindings: array<int, mixed>}
     */
    protected function comprasUnionSubquery(int $storeId, array $filtros): array
    {
        $q = DB::table('purchases')
            ->leftJoin('proveedores', 'proveedores.id', '=', 'purchases.proveedor_id')
            ->where('purchases.store_id', $storeId)
            ->where('purchases.purchase_type', Purchase::TYPE_PRODUCTO)
            ->selectRaw("'purchase' as source")
            ->addSelect([
                'purchases.id as id',
                'purchases.created_at as created_at',
                'proveedores.nombre as proveedor_nombre',
                'purchases.total as total',
                'purchases.status as status',
                'purchases.payment_status as payment_status',
                'purchases.payment_type as payment_type',
                'purchases.invoice_number as invoice_number',
            ])
            ->selectRaw('null as doc_prefix')
            ->selectRaw('null as doc_number');

        if (! empty($filtros['status'])) {
            $q->where('purchases.status', $filtros['status']);
        }
        if (! empty($filtros['payment_status'])) {
            $q->where('purchases.payment_status', $filtros['payment_status']);
        }
        if (! empty(trim((string) ($filtros['proveedor_nombre'] ?? '')))) {
            $term = '%'.trim((string) $filtros['proveedor_nombre']).'%';
            $q->where('proveedores.nombre', 'like', $term);
        }
        if (! empty($filtros['fecha_desde'])) {
            $q->whereDate('purchases.created_at', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $q->whereDate('purchases.created_at', '<=', $filtros['fecha_hasta']);
        }

        return ['sql' => '('.$q->toSql().')', 'bindings' => $q->getBindings()];
    }

    /**
     * @return array{sql: string, bindings: array<int, mixed>}
     */
    protected function documentosUnionSubquery(int $storeId, array $filtros): array
    {
        $q = DB::table('support_documents')
            ->leftJoin('proveedores', 'proveedores.id', '=', 'support_documents.proveedor_id')
            ->where('support_documents.store_id', $storeId)
            ->selectRaw("'support_document' as source")
            ->addSelect([
                'support_documents.id as id',
                'support_documents.created_at as created_at',
                'proveedores.nombre as proveedor_nombre',
                'support_documents.total as total',
                'support_documents.status as status',
                'support_documents.payment_status as payment_status',
            ])
            ->selectRaw('null as payment_type')
            ->selectRaw('null as invoice_number')
            ->addSelect([
                'support_documents.doc_prefix as doc_prefix',
                'support_documents.doc_number as doc_number',
            ]);

        if (! empty($filtros['status'])) {
            $q->where('support_documents.status', $filtros['status']);
        }
        if (! empty($filtros['payment_status'])) {
            $q->where('support_documents.payment_status', $filtros['payment_status']);
        }
        if (! empty(trim((string) ($filtros['proveedor_nombre'] ?? '')))) {
            $term = '%'.trim((string) $filtros['proveedor_nombre']).'%';
            $q->where('proveedores.nombre', 'like', $term);
        }
        if (! empty($filtros['fecha_desde'])) {
            $q->whereDate('support_documents.issue_date', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $q->whereDate('support_documents.issue_date', '<=', $filtros['fecha_hasta']);
        }

        return ['sql' => '('.$q->toSql().')', 'bindings' => $q->getBindings()];
    }

    protected function mapPurchaseRow(Store $store, Purchase $purchase): object
    {
        $invoice = trim((string) ($purchase->invoice_number ?? ''));
        $numberLabel = $invoice !== '' ? $invoice : 'Compra #'.$purchase->id;

        return (object) [
            'source' => 'purchase',
            'id' => $purchase->id,
            'created_at' => $purchase->created_at,
            'proveedor_nombre' => $purchase->proveedor?->nombre,
            'total' => $purchase->total,
            'status' => $purchase->status,
            'payment_status' => $purchase->payment_status,
            'payment_type' => $purchase->payment_type,
            'number_label' => $numberLabel,
            'show_url' => route('stores.purchases.show', [$store, $purchase]),
            'edit_url' => $purchase->isBorrador()
                ? route('stores.product-purchases.edit', [$store, $purchase])
                : null,
        ];
    }

    protected function mapSupportDocumentRow(Store $store, SupportDocument $document): object
    {
        $prefix = trim((string) ($document->doc_prefix ?? ''));
        $numberLabel = $prefix !== ''
            ? $prefix.'-'.$document->doc_number
            : (string) $document->doc_number;

        return (object) [
            'source' => 'support_document',
            'id' => $document->id,
            'created_at' => $document->created_at,
            'proveedor_nombre' => $document->proveedor?->nombre,
            'total' => $document->total,
            'status' => $document->status,
            'payment_status' => $document->payment_status,
            'payment_type' => null,
            'number_label' => $numberLabel,
            'show_url' => route('stores.product-purchases.documento-soporte.edit', [$store, $document]),
            'edit_url' => $document->status === SupportDocument::STATUS_BORRADOR
                ? route('stores.product-purchases.documento-soporte.edit', [$store, $document])
                : null,
        ];
    }

    protected function mapUnionDbRow(Store $store, object $row): object
    {
        $source = (string) $row->source;
        $id = (int) $row->id;
        $status = (string) $row->status;

        $invoice = trim((string) ($row->invoice_number ?? ''));
        $prefix = trim((string) ($row->doc_prefix ?? ''));
        $docNumber = $row->doc_number;

        if ($source === 'purchase') {
            $numberLabel = $invoice !== '' ? $invoice : 'Compra #'.$id;
        } else {
            $numberLabel = $prefix !== '' ? $prefix.'-'.$docNumber : (string) $docNumber;
        }

        $showUrl = $source === 'purchase'
            ? route('stores.purchases.show', ['store' => $store, 'purchase' => $id])
            : route('stores.product-purchases.documento-soporte.edit', ['store' => $store, 'supportDocument' => $id]);

        $editUrl = null;
        if ($source === 'purchase' && $status === Purchase::STATUS_BORRADOR) {
            $editUrl = route('stores.product-purchases.edit', ['store' => $store, 'purchase' => $id]);
        }
        if ($source === 'support_document' && $status === SupportDocument::STATUS_BORRADOR) {
            $editUrl = route('stores.product-purchases.documento-soporte.edit', ['store' => $store, 'supportDocument' => $id]);
        }

        return (object) [
            'source' => $source,
            'id' => $id,
            'created_at' => Carbon::parse($row->created_at),
            'proveedor_nombre' => $row->proveedor_nombre,
            'total' => $row->total,
            'status' => $status,
            'payment_status' => $row->payment_status,
            'payment_type' => $row->payment_type,
            'number_label' => $numberLabel,
            'show_url' => $showUrl,
            'edit_url' => $editUrl,
        ];
    }
}
