<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\Bolsillo;
use App\Models\Store;
use App\Services\AccountPayableService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreAccountPayableController extends Controller
{
    public function __construct(
        protected AccountPayableService $accountPayableService,
        protected StorePermissionService $permissionService
    ) {}

    public function index(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'accounts-payables.view');

        $filtros = [
            'status' => $request->get('status'),
            'per_page' => $request->get('per_page', 15),
        ];

        $accountsPayables = $this->accountPayableService->listarCuentasPorPagar($store, $filtros);
        $deudaTotal = $this->accountPayableService->deudaTotal($store);

        return view('stores.cuentas-por-pagar', compact('store', 'accountsPayables', 'deudaTotal'));
    }

    public function show(Store $store, AccountPayable $accountPayable)
    {
        $this->permissionService->authorize($store, 'accounts-payables.view');

        if ($accountPayable->store_id !== $store->id) {
            abort(404);
        }

        $accountPayable = $this->accountPayableService->obtenerCuentaPorPagar($store, $accountPayable->id);
        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.cuenta-por-pagar-detalle', compact('store', 'accountPayable', 'bolsillos'));
    }

    public function pay(Store $store, AccountPayable $accountPayable, Request $request)
    {
        $this->permissionService->authorize($store, 'accounts-payables.pay');

        if ($accountPayable->store_id !== $store->id) {
            abort(404);
        }

        $data = [
            'parts' => $request->input('parts', []),
            'payment_date' => $request->input('payment_date'),
            'notes' => $request->input('notes'),
        ];

        try {
            $this->accountPayableService->registrarPago($store, $accountPayable->id, Auth::id(), $data);

            return redirect()->route('stores.accounts-payables.show', [$store, $accountPayable])
                ->with('success', 'Pago registrado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
