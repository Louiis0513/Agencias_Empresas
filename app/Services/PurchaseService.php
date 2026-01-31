<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\Activo;
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

            $purchase = Purchase::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'status' => Purchase::STATUS_BORRADOR,
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
     * Aprueba una compra: suma inventario (INVENTARIO) y confirma.
     * Si es contado (PAGADO), requiere paymentData para registrar el pago en caja.
     */
    /**
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

            if ($purchase->payment_status === Purchase::PAYMENT_PAGADO) {
                if (! $accountPayableService || ! $paymentData || empty($paymentData['parts'])) {
                    throw new Exception('Para compras de contado debe indicar de qué bolsillo(s) se paga.');
                }
                $accountPayable = $this->crearCuentaPorPagar($purchase);
                $accountPayableService->registrarPago($store, $accountPayable->id, $userId, $paymentData);
            } elseif ($purchase->payment_status === Purchase::PAYMENT_PENDIENTE) {
                $this->crearCuentaPorPagar($purchase);
            }

            $purchase->update(['status' => Purchase::STATUS_APROBADO]);

            foreach ($purchase->details as $detail) {
                if ($detail->isInventario() && $detail->product_id) {
                    $product = $detail->product;
                    if ($product && $product->type === \App\Models\MovimientoInventario::PRODUCT_TYPE_INVENTARIO) {
                        $this->inventarioService->registrarMovimiento($store, $userId, [
                            'product_id' => $product->id,
                            'type' => \App\Models\MovimientoInventario::TYPE_ENTRADA,
                            'quantity' => $detail->quantity,
                            'unit_cost' => $detail->unit_cost,
                            'description' => "Compra #{$purchase->id} - {$detail->description}",
                            'purchase_id' => $purchase->id,
                        ]);
                    }
                }
                if ($detail->isActivoFijo() && $detail->activo_id) {
                    $activo = $detail->activo;
                    $purchaseDate = $purchase->invoice_date ?? $purchase->created_at;
                    if ($activo && $activo->isSerializado()) {
                        if (! empty($activo->serial_number) && $activo->quantity === 0) {
                            $this->activoService->registrarEntrada($store, $detail->activo_id, $detail->quantity, (float) $detail->unit_cost, $userId, $purchase->id, "Compra #{$purchase->id}");
                            $this->activoService->actualizarActivoDesdeCompra($store, $detail->activo_id, $purchaseDate);
                        } else {
                            $serials = $serialsByDetailId[$detail->id] ?? [];
                            if (count($serials) !== $detail->quantity) {
                                throw new Exception("El activo «{$activo->name}» es serializado. Debes indicar {$detail->quantity} número(s) de serie.");
                            }
                            $instances = $this->activoService->crearInstanciasSerializadas($store, $detail->activo_id, $serials, (float) $detail->unit_cost, $userId, $purchase->id, "Compra #{$purchase->id}");
                            foreach ($instances as $inst) {
                                $this->activoService->actualizarActivoDesdeCompra($store, $inst->id, $purchaseDate);
                            }
                        }
                    } else {
                        $this->activoService->registrarEntrada($store, $detail->activo_id, $detail->quantity, (float) $detail->unit_cost, $userId, $purchase->id, "Compra #{$purchase->id}");
                        $this->activoService->actualizarActivoDesdeCompra($store, $detail->activo_id, $purchaseDate);
                    }
                }
            }

            return $purchase->fresh()->load(['details.product', 'details.activo', 'proveedor', 'user']);
        });
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

        return PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productId,
            'activo_id' => $activoId,
            'item_type' => $itemType,
            'description' => $description,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $subtotal,
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
