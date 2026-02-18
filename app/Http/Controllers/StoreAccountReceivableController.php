<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use App\Models\Bolsillo;
use App\Models\Store;
use App\Services\AccountReceivableService;
use App\Services\ComprobanteIngresoService;
use App\Services\CustomerService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreAccountReceivableController extends Controller
{
    public function __construct(
        protected AccountReceivableService $accountReceivableService,
        protected ComprobanteIngresoService $comprobanteIngresoService,
        protected CustomerService $customerService,
        protected StorePermissionService $permissionService
    ) {}

    public function index(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'accounts-receivables.view');

        $filtros = [
            'status' => $request->get('status'),
            'customer_id' => $request->get('customer_id'),
            'per_page' => $request->get('per_page', 15),
        ];

        $cuentas = $this->accountReceivableService->listar($store, $filtros);
        $saldoPendiente = $this->accountReceivableService->saldoPendienteTotal($store);
        $customers = $this->customerService->getAllStoreCustomers($store);

        return view('stores.cuentas-por-cobrar', compact('store', 'cuentas', 'saldoPendiente', 'customers'));
    }

    public function show(Store $store, AccountReceivable $accountReceivable)
    {
        $this->permissionService->authorize($store, 'accounts-receivables.view');

        if ($accountReceivable->store_id !== $store->id) {
            abort(404);
        }

        $accountReceivable = $this->accountReceivableService->obtener($store, $accountReceivable->id);
        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.cuenta-por-cobrar-detalle', compact('store', 'accountReceivable', 'bolsillos'));
    }

    public function cobrar(Store $store, AccountReceivable $accountReceivable, Request $request)
    {
        $this->permissionService->authorize($store, 'accounts-receivables.cobrar');

        if ($accountReceivable->store_id !== $store->id) {
            abort(404);
        }

        $amount = (float) $request->input('amount', 0);
        $parts = $request->input('parts', []);

        $destinos = [];
        foreach (is_array($parts) ? $parts : [] as $p) {
            $amt = (float) ($p['amount'] ?? 0);
            $bolsilloId = (int) ($p['bolsillo_id'] ?? 0);
            if ($amt > 0 && $bolsilloId > 0) {
                $destinos[] = [
                    'bolsillo_id' => $bolsilloId,
                    'amount' => $amt,
                    'reference' => $p['reference'] ?? null,
                ];
            }
        }

        $sumDestinos = array_sum(array_column($destinos, 'amount'));
        if ($amount <= 0 || $sumDestinos <= 0 || abs($sumDestinos - $amount) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'El monto a cobrar debe ser mayor a cero y la suma de los destinos (bolsillos) debe coincidir con el monto.');
        }

        $data = [
            'date' => $request->input('date', now()->toDateString()),
            'notes' => $request->input('notes'),
            'destinos' => $destinos,
            'aplicaciones' => [
                ['account_receivable_id' => $accountReceivable->id, 'amount' => $amount],
            ],
        ];

        try {
            $this->comprobanteIngresoService->crearComprobante($store, Auth::id(), $data);

            return redirect()->route('stores.accounts-receivables.show', [$store, $accountReceivable])
                ->with('success', 'Cobro registrado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
