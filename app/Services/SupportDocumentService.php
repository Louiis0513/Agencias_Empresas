<?php

namespace App\Services;

use App\Models\Bolsillo;
use App\Models\ComprobanteEgreso;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Proveedor;
use App\Models\Store;
use App\Models\SupportDocument;
use App\Models\SupportDocumentSequence;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupportDocumentService
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected ComprobanteEgresoService $comprobanteEgresoService
    ) {}

    public function crearBorrador(Store $store, int $userId, array $data): SupportDocument
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $normalized = $this->validarYNormalizarPayload($store, $data);

            [$docPrefix, $docNumber] = $this->asignarConsecutivo($store, $normalized['doc_prefix']);

            $document = SupportDocument::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'proveedor_id' => $normalized['proveedor_id'],
                'status' => SupportDocument::STATUS_BORRADOR,
                'payment_status' => $normalized['payment_status'],
                'due_date' => $normalized['due_date'],
                'doc_prefix' => $docPrefix,
                'doc_number' => $docNumber,
                'issue_date' => $normalized['issue_date'],
                'subtotal' => $normalized['totals']['subtotal'],
                'tax_total' => $normalized['totals']['tax_total'],
                'total' => $normalized['totals']['total'],
                'notes' => $normalized['notes'],
            ]);

            $this->persistirLineas($document, $normalized['inventory_items'], $normalized['service_items']);

            return $document->fresh()->load([
                'inventoryItems.product',
                'serviceItems',
                'proveedor',
                'user',
            ]);
        });
    }

    public function actualizarBorrador(Store $store, int $documentId, array $data): SupportDocument
    {
        return DB::transaction(function () use ($store, $documentId, $data) {
            $document = SupportDocument::where('id', $documentId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            if ($document->status !== SupportDocument::STATUS_BORRADOR) {
                throw new Exception('Solo se pueden editar documentos soporte en estado BORRADOR.');
            }

            $normalized = $this->validarYNormalizarPayload($store, $data);

            $document->update([
                'proveedor_id' => $normalized['proveedor_id'],
                'payment_status' => $normalized['payment_status'],
                'due_date' => $normalized['due_date'],
                'issue_date' => $normalized['issue_date'],
                'subtotal' => $normalized['totals']['subtotal'],
                'tax_total' => $normalized['totals']['tax_total'],
                'total' => $normalized['totals']['total'],
                'notes' => $normalized['notes'],
            ]);

            $document->inventoryItems()->delete();
            $document->serviceItems()->delete();
            $this->persistirLineas($document, $normalized['inventory_items'], $normalized['service_items']);

            return $document->fresh()->load([
                'inventoryItems.product',
                'serviceItems',
                'proveedor',
                'user',
            ]);
        });
    }

    public function listarDocumentos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = SupportDocument::deTienda($store->id)
            ->with(['proveedor', 'user'])
            ->orderByDesc('created_at');

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }
        if (! empty($filtros['payment_status'])) {
            $query->where('payment_status', $filtros['payment_status']);
        }
        if (! empty($filtros['proveedor_id'])) {
            $query->where('proveedor_id', (int) $filtros['proveedor_id']);
        }
        if (! empty(trim($filtros['proveedor_nombre'] ?? ''))) {
            $term = trim((string) $filtros['proveedor_nombre']);
            $query->whereHas('proveedor', fn ($q) => $q->where('nombre', 'like', '%'.$term.'%'));
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('issue_date', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('issue_date', '<=', $filtros['fecha_hasta']);
        }

        return $query->paginate((int) ($filtros['per_page'] ?? 15));
    }

    public function obtenerDocumento(Store $store, int $documentId): SupportDocument
    {
        return SupportDocument::where('id', $documentId)
            ->where('store_id', $store->id)
            ->with([
                'inventoryItems.product',
                'serviceItems',
                'proveedor',
                'user',
            ])
            ->firstOrFail();
    }

    public function anularBorrador(Store $store, int $documentId): SupportDocument
    {
        $document = SupportDocument::where('id', $documentId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($document->status !== SupportDocument::STATUS_BORRADOR) {
            throw new Exception('Solo se pueden anular documentos soporte en estado BORRADOR.');
        }

        $document->update(['status' => SupportDocument::STATUS_ANULADO]);

        return $document->fresh();
    }

    public function aprobarDocumento(Store $store, int $documentId, int $userId, array $paymentParts = []): SupportDocument
    {
        return DB::transaction(function () use ($store, $documentId, $userId, $paymentParts) {
            /** @var SupportDocument $document */
            $document = SupportDocument::where('id', $documentId)
                ->where('store_id', $store->id)
                ->with(['inventoryItems.product', 'serviceItems', 'proveedor'])
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedPaymentParts = $this->validarDocumentoParaAprobar($store, $document, $paymentParts);

            $this->registrarEntradasInventarioPorAprobacion($store, $document, $userId);

            $comprobante = null;
            if ($document->payment_status === SupportDocument::PAYMENT_PAGADO) {
                $comprobante = $this->crearComprobanteEgresoPorPagoContado($store, $document, $userId, $normalizedPaymentParts);
            }

            $document->update([
                'status' => SupportDocument::STATUS_APROBADO,
                'comprobante_egreso_id' => $comprobante?->id,
            ]);

            return $document->fresh()->load([
                'inventoryItems.product',
                'serviceItems',
                'proveedor',
                'user',
                'comprobanteEgreso',
            ]);
        });
    }

    protected function validarYNormalizarPayload(Store $store, array $data): array
    {
        $validator = Validator::make($data, [
            'proveedor_id' => ['required', 'integer'],
            'payment_status' => ['required', 'in:'.SupportDocument::PAYMENT_PAGADO.','.SupportDocument::PAYMENT_PENDIENTE],
            'due_date' => ['nullable', 'date'],
            'issue_date' => ['required', 'date'],
            'doc_prefix' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'inventory_items' => ['nullable', 'array'],
            'inventory_items.*.product_id' => ['required', 'integer'],
            'inventory_items.*.description' => ['nullable', 'string'],
            'inventory_items.*.quantity' => ['required', 'integer', 'min:1'],
            'inventory_items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'inventory_items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'service_items' => ['nullable', 'array'],
            'service_items.*.service_name' => ['required', 'string', 'max:255'],
            'service_items.*.description' => ['nullable', 'string'],
            'service_items.*.quantity' => ['required', 'integer', 'min:1'],
            'service_items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'service_items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ], [
            'proveedor_id.required' => 'Debes seleccionar un proveedor.',
            'payment_status.required' => 'La forma de pago es obligatoria.',
            'issue_date.required' => 'La fecha de emisión es obligatoria.',
            'inventory_items.*.product_id.required' => 'Cada línea de inventario debe tener producto.',
            'service_items.*.service_name.required' => 'Cada línea de servicio debe tener nombre del servicio.',
        ]);

        $validator->after(function ($validator) use ($store, $data) {
            $paymentStatus = $data['payment_status'] ?? null;
            $dueDate = $data['due_date'] ?? null;
            $issueDate = $data['issue_date'] ?? null;

            if ($paymentStatus === SupportDocument::PAYMENT_PENDIENTE) {
                if (empty($dueDate)) {
                    $validator->errors()->add('due_date', 'La fecha de vencimiento es obligatoria cuando el documento es a crédito.');
                } elseif (! empty($issueDate) && strtotime((string) $dueDate) < strtotime((string) $issueDate)) {
                    $validator->errors()->add('due_date', 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.');
                }
            }

            $proveedorId = (int) ($data['proveedor_id'] ?? 0);
            if ($proveedorId > 0) {
                $validProveedor = Proveedor::where('id', $proveedorId)->where('store_id', $store->id)->exists();
                if (! $validProveedor) {
                    $validator->errors()->add('proveedor_id', 'El proveedor seleccionado no pertenece a esta tienda.');
                }
            }

            $inventoryItems = $data['inventory_items'] ?? [];
            $serviceItems = $data['service_items'] ?? [];
            if (empty($inventoryItems) && empty($serviceItems)) {
                $validator->errors()->add('items', 'Debes agregar al menos una línea (inventario o servicio).');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $inventoryItems = array_values($data['inventory_items'] ?? []);
        $serviceItems = array_values($data['service_items'] ?? []);

        $totals = $this->calcularTotales($store, $inventoryItems, $serviceItems);

        return [
            'proveedor_id' => (int) $data['proveedor_id'],
            'payment_status' => (string) $data['payment_status'],
            'due_date' => $data['due_date'] ?? null,
            'issue_date' => (string) $data['issue_date'],
            'doc_prefix' => trim((string) ($data['doc_prefix'] ?? 'DSE')) ?: 'DSE',
            'notes' => $data['notes'] ?? null,
            'inventory_items' => $totals['inventory_items'],
            'service_items' => $totals['service_items'],
            'totals' => $totals['totals'],
        ];
    }

    protected function calcularTotales(Store $store, array $inventoryItems, array $serviceItems): array
    {
        $normalizedInventory = [];
        $normalizedServices = [];

        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($inventoryItems as $idx => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = Product::where('id', $productId)->where('store_id', $store->id)->first();
            if (! $product) {
                $validator = Validator::make([], []);
                $validator->errors()->add("inventory_items.{$idx}.product_id", 'El producto seleccionado no pertenece a esta tienda.');
                throw new ValidationException($validator);
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $unitCost = round((float) ($item['unit_cost'] ?? 0), 2);
            $taxRate = isset($item['tax_rate']) && $item['tax_rate'] !== '' ? (float) $item['tax_rate'] : null;

            $lineBase = round($quantity * $unitCost, 2);
            $lineTax = $taxRate !== null ? round($lineBase * ($taxRate / 100), 2) : 0.0;
            $lineTotal = round($lineBase + $lineTax, 2);

            $subtotal += $lineBase;
            $taxTotal += $lineTax;

            $normalizedInventory[] = [
                'product_id' => $productId,
                'description' => $item['description'] ?? $product->name,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
            ];
        }

        foreach ($serviceItems as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitCost = round((float) ($item['unit_cost'] ?? 0), 2);
            $taxRate = isset($item['tax_rate']) && $item['tax_rate'] !== '' ? (float) $item['tax_rate'] : null;

            $lineBase = round($quantity * $unitCost, 2);
            $lineTax = $taxRate !== null ? round($lineBase * ($taxRate / 100), 2) : 0.0;
            $lineTotal = round($lineBase + $lineTax, 2);

            $subtotal += $lineBase;
            $taxTotal += $lineTax;

            $normalizedServices[] = [
                'service_name' => trim((string) $item['service_name']),
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $taxTotal = round($taxTotal, 2);
        $total = round($subtotal + $taxTotal, 2);

        return [
            'inventory_items' => $normalizedInventory,
            'service_items' => $normalizedServices,
            'totals' => [
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
            ],
        ];
    }

    protected function persistirLineas(SupportDocument $document, array $inventoryItems, array $serviceItems): void
    {
        foreach ($inventoryItems as $item) {
            $document->inventoryItems()->create($item);
        }
        foreach ($serviceItems as $item) {
            $document->serviceItems()->create($item);
        }
    }

    protected function validarDocumentoParaAprobar(Store $store, SupportDocument $document, array $paymentParts = []): array
    {
        if ($document->status !== SupportDocument::STATUS_BORRADOR) {
            throw new Exception('Solo se pueden aprobar documentos soporte en estado BORRADOR.');
        }

        if ($document->inventoryItems->isEmpty() && $document->serviceItems->isEmpty()) {
            throw new Exception('El documento soporte debe tener al menos una línea de inventario o servicio para aprobar.');
        }

        foreach ($document->inventoryItems as $index => $line) {
            $product = $line->product;
            if (! $product || (int) $product->store_id !== (int) $store->id) {
                throw new Exception("La línea de inventario #".($index + 1)." tiene un producto inválido para esta tienda.");
            }

            if (! (bool) $product->is_active) {
                throw new Exception("No puedes aprobar con productos inactivos: «{$product->name}».");
            }

            if ($line->quantity < 1) {
                throw new Exception("La línea «{$product->name}» debe tener cantidad mayor a cero.");
            }
        }

        if ($document->payment_status !== SupportDocument::PAYMENT_PAGADO) {
            return [];
        }

        if (empty($paymentParts)) {
            throw new Exception('Debes indicar al menos un origen de pago (bolsillo y monto) para aprobar un documento de contado.');
        }

        $normalized = [];
        $totalParts = 0.0;
        foreach ($paymentParts as $index => $part) {
            $amount = round((float) ($part['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            $bolsilloId = (int) ($part['bolsillo_id'] ?? 0);
            if ($bolsilloId < 1) {
                throw new Exception('Cada origen de pago debe incluir un bolsillo válido.');
            }

            $bolsillo = Bolsillo::deTienda($store->id)
                ->activos()
                ->where('id', $bolsilloId)
                ->first();
            if (! $bolsillo) {
                throw new Exception("El bolsillo indicado en la línea ".($index + 1)." no existe o no está activo.");
            }

            $totalParts += $amount;
            $normalized[] = [
                'bolsillo_id' => $bolsilloId,
                'amount' => $amount,
                'reference' => isset($part['reference']) ? trim((string) $part['reference']) : null,
            ];
        }

        if (empty($normalized)) {
            throw new Exception('Debes indicar al menos un origen de pago con monto mayor a cero.');
        }

        $documentTotal = round((float) $document->total, 2);
        $totalParts = round($totalParts, 2);
        if (abs($totalParts - $documentTotal) > 0.01) {
            throw new Exception("La suma de orígenes de pago ({$totalParts}) debe coincidir con el total del documento ({$documentTotal}).");
        }

        return $normalized;
    }

    protected function registrarEntradasInventarioPorAprobacion(Store $store, SupportDocument $document, int $userId): void
    {
        $reference = "{$document->doc_prefix}-{$document->doc_number}";

        foreach ($document->inventoryItems as $line) {
            $product = $line->product;
            if (! $product) {
                continue;
            }

            if ($product->isSerialized() || $product->isBatch()) {
                throw new Exception("El producto «{$product->name}» requiere detalle por seriales o variantes para registrar la entrada. Por ahora, aprobación soporta productos simples.");
            }

            $description = "Documento Soporte #{$reference} - {$line->description}";

            $this->inventarioService->registrarMovimiento($store, $userId, [
                'product_id' => $product->id,
                'type' => MovimientoInventario::TYPE_ENTRADA,
                'quantity' => (int) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'description' => $description,
                'reference' => $reference,
                'support_document_id' => $document->id,
            ]);
        }
    }

    protected function crearComprobanteEgresoPorPagoContado(Store $store, SupportDocument $document, int $userId, array $paymentParts): ComprobanteEgreso
    {
        $reference = "{$document->doc_prefix}-{$document->doc_number}";
        $concepto = "Pago Documento Soporte #{$reference}";

        $destinos = [[
            'concepto' => $concepto,
            'beneficiario' => $document->proveedor?->nombre ?? 'Proveedor',
            'amount' => (float) $document->total,
        ]];

        $origenes = [];
        foreach ($paymentParts as $part) {
            $origenes[] = [
                'bolsillo_id' => (int) $part['bolsillo_id'],
                'amount' => (float) $part['amount'],
                'reference' => $part['reference'] ?? null,
            ];
        }

        return $this->comprobanteEgresoService->crearComprobante($store, $userId, [
            'proveedor_id' => $document->proveedor_id,
            'payment_date' => optional($document->issue_date)->toDateString() ?? now()->toDateString(),
            'notes' => $concepto,
            'destinos' => $destinos,
            'origenes' => $origenes,
        ]);
    }

    protected function asignarConsecutivo(Store $store, string $prefix = 'DSE'): array
    {
        $prefix = trim($prefix) !== '' ? strtoupper(trim($prefix)) : 'DSE';

        $sequence = SupportDocumentSequence::where('store_id', $store->id)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = SupportDocumentSequence::create([
                'store_id' => $store->id,
                'prefix' => $prefix,
                'last_number' => 0,
            ]);
            $sequence = SupportDocumentSequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $next = (int) $sequence->last_number + 1;
        $sequence->update(['last_number' => $next]);

        return [$prefix, $next];
    }
}
