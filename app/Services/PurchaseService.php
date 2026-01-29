<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        protected InventarioService $inventarioService
    ) {}

    /**
     * Crea una compra en estado BORRADOR.
     * Si payment_status = PENDIENTE, crea la cuenta por pagar.
     */
    public function crearCompra(Store $store, int $userId, array $data): Purchase
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $details = $data['details'] ?? [];
            unset($data['details']);

            $total = 0;
            foreach ($details as $d) {
                $subtotal = (float) ($d['quantity'] ?? 0) * (float) ($d['unit_cost'] ?? 0);
                $total += $subtotal;
            }

            $purchase = Purchase::create([
                'store_id' => $store->id,
                'user_id' => $userId,
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'status' => Purchase::STATUS_BORRADOR,
                'payment_status' => $data['payment_status'] ?? Purchase::PAYMENT_PAGADO,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'total' => $total,
            ]);

            foreach ($details as $d) {
                $this->crearDetalle($purchase, $d);
            }

            if ($purchase->payment_status === Purchase::PAYMENT_PENDIENTE) {
                $this->crearCuentaPorPagar($purchase);
            }

            return $purchase->load(['details.product', 'proveedor', 'user']);
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

            $details = $data['details'] ?? null;
            unset($data['details']);

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

            $this->sincronizarCuentaPorPagar($purchase);

            return $purchase->fresh()->load(['details.product', 'proveedor', 'user']);
        });
    }

    /**
     * Aprueba una compra: suma inventario (INVENTARIO) y confirma.
     */
    public function aprobarCompra(Store $store, int $purchaseId, int $userId): Purchase
    {
        return DB::transaction(function () use ($store, $purchaseId, $userId) {
            $purchase = Purchase::where('id', $purchaseId)
                ->where('store_id', $store->id)
                ->with('details.product')
                ->firstOrFail();

            if (! $purchase->isBorrador()) {
                throw new Exception('Solo se pueden aprobar compras en estado BORRADOR.');
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
            }

            return $purchase->fresh()->load(['details.product', 'proveedor', 'user']);
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
            ->with(['details.product', 'proveedor', 'user', 'accountPayable'])
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
            ->with(['details.product', 'proveedor', 'user', 'accountPayable'])
            ->firstOrFail();
    }

    protected function crearDetalle(Purchase $purchase, array $d): PurchaseDetail
    {
        $quantity = (int) ($d['quantity'] ?? 0);
        $unitCost = (float) ($d['unit_cost'] ?? 0);
        $subtotal = $quantity * $unitCost;

        $itemType = $d['item_type'] ?? PurchaseDetail::TYPE_INVENTARIO;
        $productId = $d['product_id'] ?? null;
        $description = $d['description'] ?? null;

        if ($productId) {
            $product = \App\Models\Product::where('id', $productId)->where('store_id', $purchase->store_id)->first();
            if ($product && empty($description)) {
                $description = $product->name;
            }
        }

        if (empty($description)) {
            throw new Exception('La descripción es obligatoria cuando no hay producto vinculado.');
        }

        return PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productId,
            'item_type' => $itemType,
            'description' => $description,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $subtotal,
        ]);
    }

    protected function crearCuentaPorPagar(Purchase $purchase): AccountPayable
    {
        return AccountPayable::create([
            'store_id' => $purchase->store_id,
            'purchase_id' => $purchase->id,
            'total_amount' => $purchase->total,
            'balance' => $purchase->total,
            'due_date' => $purchase->invoice_date,
            'status' => AccountPayable::STATUS_PENDIENTE,
        ]);
    }

    protected function sincronizarCuentaPorPagar(Purchase $purchase): void
    {
        if ($purchase->payment_status === Purchase::PAYMENT_PENDIENTE) {
            if (! $purchase->accountPayable) {
                $this->crearCuentaPorPagar($purchase);
            } else {
                $purchase->accountPayable->update([
                    'total_amount' => $purchase->total,
                    'balance' => $purchase->total,
                ]);
            }
        } else {
            $purchase->accountPayable?->delete();
        }
    }
}
