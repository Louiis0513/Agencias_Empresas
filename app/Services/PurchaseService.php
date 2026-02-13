<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\Activo;
use App\Models\BatchItem;
use App\Models\ProductItem;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected InventarioService $inventarioService,
        protected ActivoService $activoService
    ) {}

    /**
     * Crea una compra en estado BORRADOR.
     * La cuenta por pagar se crea solo al aprobar la compra (igual que los movimientos de stock).
     */
    public function crearCompra(Store $store, int $userId, array $data): Purchase
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $this->validarDatosCompra($data);

            $details = $data['details'] ?? [];
            unset($data['details']);

            $total = 0;
            foreach ($details as $d) {
                $subtotal = (float) ($d['quantity'] ?? 0) * (float) ($d['unit_cost'] ?? 0);
                $total += $subtotal;
            }

            $paymentStatus = $data['payment_status'] ?? Purchase::PAYMENT_PAGADO;
            $paymentType = $paymentStatus === Purchase::PAYMENT_PENDIENTE
                ? Purchase::PAYMENT_TYPE_CREDITO
                : Purchase::PAYMENT_TYPE_CONTADO;

            $purchaseType = $data['purchase_type'] ?? Purchase::TYPE_ACTIVO;
            if (! in_array($purchaseType, [Purchase::TYPE_ACTIVO, Purchase::TYPE_PRODUCTO], true)) {
                $purchaseType = Purchase::TYPE_ACTIVO;
            }

            $purchase = Purchase::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'status' => Purchase::STATUS_BORRADOR,
                'purchase_type' => $purchaseType,
                'payment_status' => $paymentStatus,
                'payment_type' => $paymentType,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'total' => $total,
            ]);

            foreach ($details as $d) {
                $this->crearDetalle($purchase, $d);
            }

            return $purchase->load(['details.product', 'details.activo', 'proveedor', 'user']);
        });
    }

    /**
     * Actualiza una compra en BORRADOR.
     */
    public function actualizarCompra(Store $store, int $purchaseId, array $data): Purchase
    {
        return DB::transaction(function () use ($store, $purchaseId, $data) {
            $purchase = Purchase::where('id', $purchaseId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            if (! $purchase->isBorrador()) {
                throw new Exception('Solo se pueden editar compras en estado BORRADOR.');
            }

            $this->validarDatosCompra($data, $purchase);

            $details = $data['details'] ?? null;
            unset($data['details']);

            if (isset($data['payment_status'])) {
                $data['payment_type'] = $data['payment_status'] === Purchase::PAYMENT_PENDIENTE
                    ? Purchase::PAYMENT_TYPE_CREDITO
                    : Purchase::PAYMENT_TYPE_CONTADO;
            }

            if ($details !== null) {
                $purchase->details()->delete();
                $total = 0;
                foreach ($details as $d) {
                    $det = $this->crearDetalle($purchase, $d);
                    $total += $det->subtotal;
                }
                $data['total'] = $total;
            }

            $purchase->update($data);

            $purchase->accountPayable?->delete();

            return $purchase->fresh()->load(['details.product', 'proveedor', 'user']);
        });
    }

    /**
     * Aprueba una compra: valida, marca como aprobada, registra movimientos de inventario/activos y crea cuenta por pagar.
     * Orden: validar → estado APROBADO → movimientos → cuenta por pagar (y pago si contado).
     *
     * @param  array<int, array<string>>|null  $serialsByDetailId  Para ACTIVO_FIJO serializado: [detailId => ['S/N1','S/N2']]
     */
    public function aprobarCompra(Store $store, int $purchaseId, int $userId, ?AccountPayableService $accountPayableService = null, ?array $paymentData = null, ?array $serialsByDetailId = null): Purchase
    {
        return DB::transaction(function () use ($store, $purchaseId, $userId, $accountPayableService, $paymentData, $serialsByDetailId) {
            $purchase = Purchase::where('id', $purchaseId)
                ->where('store_id', $store->id)
                ->with(['details.product', 'details.activo'])
                ->firstOrFail();

            if (! $purchase->isBorrador()) {
                throw new Exception('Solo se pueden aprobar compras en estado BORRADOR.');
            }

            $this->validarCompraParaAprobar($store, $purchase, $accountPayableService, $paymentData, $serialsByDetailId);

            $purchase->update(['status' => Purchase::STATUS_APROBADO]);

            $this->registrarMovimientosPorAprobacion($store, $purchase, $userId, $serialsByDetailId);

            if ($purchase->payment_status === Purchase::PAYMENT_PENDIENTE) {
                $this->crearCuentaPorPagar($purchase);
            } elseif ($purchase->payment_status === Purchase::PAYMENT_PAGADO) {
                $accountPayable = $this->crearCuentaPorPagar($purchase);
                $accountPayableService->registrarPago($store, $accountPayable->id, $userId, $paymentData);
            }

            return $purchase->fresh()->load(['details.product', 'details.activo', 'proveedor', 'user']);
        });
    }

    /**
     * Registra los movimientos de inventario y entradas de activos derivados de una compra aprobada.
     *
     * @param  array<int, array<string>>|null  $serialsByDetailId  Para ACTIVO_FIJO serializado: [detailId => ['S/N1','S/N2']]
     */
    protected function registrarMovimientosPorAprobacion(Store $store, Purchase $purchase, int $userId, ?array $serialsByDetailId = null): void
    {
        $reference = "Compra #{$purchase->id}";

        foreach ($purchase->details as $detail) {
            if ($detail->isInventario() && $detail->product_id) {
                $product = $detail->product;
                if (! $product) {
                    continue;
                }
                $baseDatos = [
                    'product_id' => $product->id,
                    'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
                    'quantity' => $detail->quantity,
                    'description' => "{$reference} - {$detail->description}",
                    'purchase_id' => $purchase->id,
                ];
                if ($product->isSerialized()) {
                    $serialItems = $detail->serial_items ?? [];
                    if (empty($serialItems)) {
                        throw new Exception("El producto «{$product->name}» es serializado. La compra debe incluir los números de serie por unidad.");
                    }
                    $this->inventarioService->registrarMovimiento($store, $userId, array_merge($baseDatos, [
                        'reference' => $reference,
                        'serial_items' => $serialItems,
                    ]));
                } elseif ($product->type === 'simple' || empty($product->type)) {
                    $this->inventarioService->registrarMovimiento($store, $userId, array_merge($baseDatos, [
                        'unit_cost' => (float) $detail->unit_cost,
                        'reference' => $reference,
                    ]));
                } elseif ($product->isBatch()) {
                    $batchItems = $detail->batch_items ?? null;
                    if (! empty($batchItems) && is_array($batchItems)) {
                        $items = [];
                        foreach ($batchItems as $bi) {
                            $qty = (int) ($bi['quantity'] ?? 0);
                            if ($qty < 1) {
                                continue;
                            }
                            $features = $bi['features'] ?? null;
                            $price = isset($bi['price']) && $bi['price'] !== null ? (float) $bi['price'] : null;

                            // Si viene batch_item_id, obtener features desde el BatchItem (fuente de verdad)
                            if (! empty($bi['batch_item_id'])) {
                                $sourceItem = BatchItem::where('id', $bi['batch_item_id'])
                                    ->whereHas('batch', fn ($q) => $q->where('product_id', $product->id)->where('store_id', $store->id))
                                    ->first();
                                if (! $sourceItem) {
                                    throw new Exception("La variante seleccionada (ID {$bi['batch_item_id']}) no existe o no pertenece al producto «{$product->name}».");
                                }
                                $features = $sourceItem->features;
                                // Si no venía price en la compra, heredar del BatchItem existente
                                if ($price === null && $sourceItem->price !== null) {
                                    $price = (float) $sourceItem->price;
                                }
                            }

                            // Si aún no hay price y tenemos features: buscar en batch_items existentes de esta variante
                            if ($price === null && $features !== null && is_array($features) && ! empty($features)) {
                                $key = InventarioService::detectorDeVariantesEnLotes($features);
                                $existingBi = BatchItem::whereHas('batch', fn ($q) => $q->where('product_id', $product->id)->where('store_id', $store->id))
                                    ->whereNotNull('price')
                                    ->get()
                                    ->first(fn (BatchItem $b) => InventarioService::detectorDeVariantesEnLotes($b->features) === $key);
                                if ($existingBi) {
                                    $price = (float) $existingBi->price;
                                }
                            }

                            $items[] = [
                                'quantity' => $qty,
                                'cost' => (float) ($bi['unit_cost'] ?? 0),
                                'unit_cost' => (float) ($bi['unit_cost'] ?? 0),
                                'price' => $price,
                                'features' => $features,
                            ];
                        }
                    } else {
                        $items = [
                            ['quantity' => $detail->quantity, 'cost' => (float) $detail->unit_cost, 'unit_cost' => (float) $detail->unit_cost, 'features' => null],
                        ];
                    }
                    $batchExpiration = isset($batchItems[0]['expiration_date']) && $batchItems[0]['expiration_date'] !== '' && $batchItems[0]['expiration_date'] !== null
                        ? $batchItems[0]['expiration_date']
                        : null;
                    $this->inventarioService->registrarMovimiento($store, $userId, array_merge($baseDatos, [
                        'unit_cost' => (float) $detail->unit_cost,
                        'batch_data' => [
                            'reference' => $reference,
                            'expiration_date' => $batchExpiration,
                            'items' => $items,
                        ],
                    ]));
                }
            }
            if ($detail->isActivoFijo() && $detail->activo_id) {
                $activo = $detail->activo;
                $purchaseDate = $purchase->invoice_date ?? $purchase->created_at;
                if ($activo && $activo->isSerializado()) {
                    if (! empty($activo->serial_number) && $activo->quantity === 0) {
                        $this->activoService->registrarEntrada($store, $detail->activo_id, $detail->quantity, (float) $detail->unit_cost, $userId, $purchase->id, $reference);
                        $this->activoService->actualizarActivoDesdeCompra($store, $detail->activo_id, $purchaseDate);
                    } else {
                        $serials = $serialsByDetailId[$detail->id] ?? [];
                        if (count($serials) !== $detail->quantity) {
                            throw new Exception("El activo «{$activo->name}» es serializado. Debes indicar {$detail->quantity} número(s) de serie.");
                        }
                        $instances = $this->activoService->crearInstanciasSerializadas($store, $detail->activo_id, $serials, (float) $detail->unit_cost, $userId, $purchase->id, $reference);
                        foreach ($instances as $inst) {
                            $this->activoService->actualizarActivoDesdeCompra($store, $inst->id, $purchaseDate);
                        }
                    }
                } else {
                    $this->activoService->registrarEntrada($store, $detail->activo_id, $detail->quantity, (float) $detail->unit_cost, $userId, $purchase->id, $reference);
                    $this->activoService->actualizarActivoDesdeCompra($store, $detail->activo_id, $purchaseDate);
                }
            }
        }
    }

    /**
     * Anula una compra (solo si está en BORRADOR).
     */
    public function anularCompra(Store $store, int $purchaseId): Purchase
    {
        $purchase = Purchase::where('id', $purchaseId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if (! $purchase->isBorrador()) {
            throw new Exception('Solo se pueden anular compras en estado BORRADOR.');
        }

        $purchase->update(['status' => Purchase::STATUS_ANULADO]);

        $purchase->accountPayable?->delete();

        return $purchase->fresh();
    }

    public function listarCompras(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = Purchase::deTienda($store->id)
            ->with(['details.product', 'details.activo', 'proveedor', 'user', 'accountPayable'])
            ->orderByDesc('created_at');

        if (! empty($filtros['status'])) {
            $query->porStatus($filtros['status']);
        }
        if (! empty($filtros['purchase_type'])) {
            $query->porTipo($filtros['purchase_type']);
        }
        if (! empty($filtros['payment_status'])) {
            $query->porPaymentStatus($filtros['payment_status']);
        }
        if (! empty($filtros['proveedor_id'])) {
            $query->where('proveedor_id', $filtros['proveedor_id']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    public function obtenerCompra(Store $store, int $purchaseId): Purchase
    {
        return Purchase::where('id', $purchaseId)
            ->where('store_id', $store->id)
            ->with(['details.product', 'details.activo', 'proveedor', 'user', 'accountPayable'])
            ->firstOrFail();
    }

    /**
     * Valida que la compra esté lista para aprobar (datos coherentes, pago si contado, seriales si aplica).
     * Lanzar antes de cambiar estado y de registrar movimientos.
     *
     * @param  array<int, array<string>>|null  $serialsByDetailId  Para ACTIVO_FIJO serializado: [detailId => ['S/N1','S/N2']]
     */
    protected function validarCompraParaAprobar(Store $store, Purchase $purchase, ?AccountPayableService $accountPayableService, ?array $paymentData, ?array $serialsByDetailId): void
    {
        $details = $purchase->details;
        $hasValidDetail = false;
        foreach ($details as $d) {
            $desc = trim($d->description ?? '');
            if ($desc !== '' || $d->product_id || $d->activo_id) {
                $hasValidDetail = true;
                break;
            }
        }
        if (! $hasValidDetail) {
            $validator = Validator::make([], []);
            $validator->errors()->add('details', 'La compra debe tener al menos un producto o bien en el detalle.');
            throw new ValidationException($validator);
        }

        if ($purchase->payment_status === Purchase::PAYMENT_PENDIENTE) {
            $rules = [
                'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            ];
            Validator::make(
                ['due_date' => $purchase->due_date, 'invoice_date' => $purchase->invoice_date],
                $rules,
                [
                    'due_date.required' => 'La fecha de vencimiento de la factura es obligatoria cuando la compra es a crédito.',
                    'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de la factura.',
                ]
            )->validate();
        }

        if ($purchase->payment_status === Purchase::PAYMENT_PAGADO) {
            if (! $accountPayableService || ! $paymentData || empty($paymentData['parts'])) {
                throw new Exception('Para compras de contado debe indicar de qué bolsillo(s) se paga.');
            }
            $sumaPartes = collect($paymentData['parts'])->sum(fn ($p) => (float) ($p['amount'] ?? 0));
            if (abs($sumaPartes - (float) $purchase->total) > 0.01) {
                $validator = Validator::make([], []);
                $validator->errors()->add('parts', "La suma de los montos ({$sumaPartes}) debe coincidir con el total de la compra ({$purchase->total}).");
                throw new ValidationException($validator);
            }
        }

        // Validar seriales de productos serializados: sin duplicados en la compra y sin repetir seriales ya en inventario
        $serialsByProduct = [];
        foreach ($details as $detail) {
            if ($detail->isInventario() && $detail->product_id) {
                $product = $detail->product;
                if (! $product) {
                    continue;
                }
                if ($product->isSerialized()) {
                    $serialItems = $detail->serial_items ?? [];
                    if (empty($serialItems) || ! is_array($serialItems)) {
                        throw new Exception("El producto «{$product->name}» es serializado. La compra debe incluir los números de serie por unidad. Edita la compra y añade las unidades con su serial.");
                    }
                    $productKey = (int) $detail->product_id;
                    if (! isset($serialsByProduct[$productKey])) {
                        $serialsByProduct[$productKey] = ['product' => $product, 'serials' => []];
                    }
                    foreach ($serialItems as $row) {
                        $serial = trim($row['serial_number'] ?? '');
                        if ($serial !== '') {
                            $serialsByProduct[$productKey]['serials'][] = $serial;
                        }
                    }
                }
            }
        }
        foreach ($serialsByProduct as $productId => $data) {
            $product = $data['product'];
            $serials = $data['serials'];
            $seen = [];
            foreach ($serials as $serial) {
                if (isset($seen[$serial])) {
                    $validator = Validator::make([], []);
                    $validator->errors()->add(
                        'serial_items',
                        "Número de serie duplicado en la compra: «{$serial}» (producto: {$product->name}). Corrige los seriales antes de aprobar."
                    );
                    throw new ValidationException($validator);
                }
                $seen[$serial] = true;
                if (ProductItem::where('store_id', $store->id)
                    ->where('product_id', $product->id)
                    ->where('serial_number', $serial)
                    ->exists()) {
                    $validator = Validator::make([], []);
                    $validator->errors()->add(
                        'serial_items',
                        "El número de serie «{$serial}» ya existe en el inventario (producto: {$product->name}). No se puede aprobar la compra hasta corregirlo."
                    );
                    throw new ValidationException($validator);
                }
            }
        }

        foreach ($details as $detail) {
            if ($detail->isActivoFijo() && $detail->activo_id) {
                $activo = $detail->activo;
                if ($activo && $activo->isSerializado() && (empty($activo->serial_number) || $activo->quantity > 0)) {
                    $serials = $serialsByDetailId[$detail->id] ?? [];
                    if (count($serials) !== $detail->quantity) {
                        throw new Exception("El activo «{$activo->name}» es serializado. Debes indicar {$detail->quantity} número(s) de serie.");
                    }
                }
            }
        }
    }

    /**
     * Valida los datos de la compra.
     * - Al menos un detalle debe tener producto o bien seleccionado.
     * - Cuando es a crédito, due_date es requerido y no puede ser anterior a invoice_date.
     */
    protected function validarDatosCompra(array $data, ?Purchase $purchase = null): void
    {
        $details = $data['details'] ?? [];
        $hasValidDetail = false;
        foreach ($details as $d) {
            $desc = trim($d['description'] ?? '');
            $productId = $d['product_id'] ?? null;
            $activoId = $d['activo_id'] ?? null;
            if ($desc !== '' || $productId || $activoId) {
                $hasValidDetail = true;
                break;
            }
        }
        if (! $hasValidDetail) {
            $validator = Validator::make([], []);
            $validator->errors()->add('details', 'Debes seleccionar al menos un producto o bien en el detalle de la compra.');
            throw new ValidationException($validator);
        }

        $paymentStatus = $data['payment_status'] ?? $purchase?->payment_status ?? Purchase::PAYMENT_PAGADO;

        if ($paymentStatus !== Purchase::PAYMENT_PENDIENTE) {
            return;
        }

        $rules = [
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
        ];

        Validator::make($data, $rules, [
            'due_date.required' => 'La fecha de vencimiento de la factura es obligatoria cuando la compra es a crédito.',
            'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de la factura.',
        ])->validate();
    }

    protected function crearDetalle(Purchase $purchase, array $d): PurchaseDetail
    {
        $quantity = (int) ($d['quantity'] ?? 0);
        $unitCost = (float) ($d['unit_cost'] ?? 0);
        $subtotal = $quantity * $unitCost;

        $itemType = $d['item_type'] ?? PurchaseDetail::TYPE_INVENTARIO;
        $productId = $d['product_id'] ?? null;
        $activoId = $d['activo_id'] ?? null;
        $description = $d['description'] ?? null;

        if ($productId) {
            $product = \App\Models\Product::where('id', $productId)->where('store_id', $purchase->store_id)->first();
            if ($product && empty($description)) {
                $description = $product->name;
            }
        }

        if ($activoId) {
            $activo = Activo::where('id', $activoId)->where('store_id', $purchase->store_id)->first();
            if ($activo && empty($description)) {
                $description = $activo->name;
            }
        }

        if (empty($description)) {
            throw new Exception('La descripción es obligatoria cuando no hay producto o activo vinculado.');
        }

        $serialItems = null;
        if (! empty($d['serial_items']) && is_array($d['serial_items'])) {
            $serialItems = array_values(array_map(function ($row) {
                return [
                    'serial_number' => trim($row['serial_number'] ?? ''),
                    'cost' => (float) ($row['cost'] ?? 0),
                    'features' => $row['features'] ?? null,
                ];
            }, $d['serial_items']));
        }

        $batchItems = null;
        if (! empty($d['batch_items']) && is_array($d['batch_items'])) {
            $batchItems = array_values($d['batch_items']);
        }

        return PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productId,
            'activo_id' => $activoId,
            'item_type' => $itemType,
            'description' => $description,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $subtotal,
            'serial_items' => $serialItems,
            'batch_items' => $batchItems,
        ]);
    }

    protected function crearCuentaPorPagar(Purchase $purchase): AccountPayable
    {
        return AccountPayable::firstOrCreate(
            ['purchase_id' => $purchase->id],
            [
                'store_id' => $purchase->store_id,
                'total_amount' => $purchase->total,
                'balance' => $purchase->total,
                'due_date' => $purchase->due_date ?? $purchase->invoice_date,
                'status' => AccountPayable::STATUS_PENDIENTE,
            ]
        );
    }

}
