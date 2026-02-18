<?php

namespace App\Http\Controllers;

use App\Models\Bolsillo;
use App\Models\Purchase;
use App\Models\Store;
use App\Services\AccountPayableService;
use App\Services\PurchaseService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StorePurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected StorePermissionService $permissionService,
        protected AccountPayableService $accountPayableService
    ) {}

    public function index(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'purchases.view');

        $filtros = [
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'proveedor_id' => $request->get('proveedor_id'),
            'purchase_type' => Purchase::TYPE_ACTIVO,
            'per_page' => $request->get('per_page', 15),
        ];

        $purchases = $this->purchaseService->listarCompras($store, $filtros);
        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compras', compact('store', 'purchases', 'proveedores'));
    }

    public function create(Store $store)
    {
        $this->permissionService->authorize($store, 'purchases.create');

        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compra-crear', compact('store', 'proveedores'));
    }

    public function store(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'purchases.create');

        $data = $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_type' => ['nullable', 'string'],
            'details.*.product_id' => ['nullable'],
            'details.*.activo_id' => ['nullable'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $data['purchase_type'] = Purchase::TYPE_ACTIVO;

        try {
            $this->purchaseService->crearCompra($store, Auth::id(), $data);

            return redirect()->route('stores.purchases', $store)
                ->with('success', 'Compra creada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Store $store, Purchase $purchase)
    {
        $this->permissionService->authorize($store, 'purchases.view');

        if ($purchase->store_id !== $store->id) {
            abort(404);
        }

        $purchase = $this->purchaseService->obtenerCompra($store, $purchase->id);
        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.compra-detalle', compact('store', 'purchase', 'bolsillos'));
    }

    public function edit(Store $store, Purchase $purchase)
    {
        $this->permissionService->authorize($store, 'purchases.create');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador()) {
            abort(404);
        }

        $purchase->load(['details.product', 'details.activo', 'proveedor']);
        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compra-editar', compact('store', 'purchase', 'proveedores'));
    }

    public function update(Store $store, Purchase $purchase, Request $request)
    {
        $this->permissionService->authorize($store, 'purchases.create');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador()) {
            abort(404);
        }

        $data = $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_type' => ['nullable', 'string'],
            'details.*.product_id' => ['nullable'],
            'details.*.activo_id' => ['nullable'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->purchaseService->actualizarCompra($store, $purchase->id, $data);

            return redirect()->route('stores.purchases.show', [$store, $purchase])
                ->with('success', 'Compra actualizada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(Store $store, Purchase $purchase, Request $request)
    {
        $this->permissionService->authorize($store, 'purchases.approve');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador()) {
            abort(404);
        }

        $serialsByDetailId = $request->input('serials');
        if (! is_array($serialsByDetailId)) {
            $serialsByDetailId = null;
        } else {
            foreach ($serialsByDetailId as $detailId => $serials) {
                $serialsByDetailId[$detailId] = is_array($serials) ? array_values($serials) : [];
            }
        }

        $paymentData = null;
        if ($purchase->payment_status === Purchase::PAYMENT_PAGADO) {
            $parts = $request->input('parts', []);
            $paymentData = [
                'parts' => is_array($parts) ? array_values($parts) : [],
                'payment_date' => $request->input('payment_date'),
                'notes' => $request->input('notes'),
            ];
        }

        try {
            $this->purchaseService->aprobarCompra(
                $store,
                $purchase->id,
                Auth::id(),
                $this->accountPayableService,
                $paymentData,
                $serialsByDetailId
            );

            return redirect()->route('stores.purchases.show', [$store, $purchase])
                ->with('success', 'Compra aprobada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function void(Store $store, Purchase $purchase)
    {
        $this->permissionService->authorize($store, 'purchases.void');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador()) {
            abort(404);
        }

        try {
            $this->purchaseService->anularCompra($store, $purchase->id);

            return redirect()->route('stores.purchases', $store)
                ->with('success', 'Compra anulada correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
