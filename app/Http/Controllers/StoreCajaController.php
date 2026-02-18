<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBolsilloRequest;
use App\Http\Requests\StoreComprobanteEgresoRequest;
use App\Http\Requests\StoreComprobanteIngresoRequest;
use App\Models\Bolsillo;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteIngreso;
use App\Models\Store;
use App\Services\AccountPayableService;
use App\Services\CajaService;
use App\Services\ComprobanteEgresoService;
use App\Services\ComprobanteIngresoService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StoreCajaController extends Controller
{
    protected CajaService $cajaService;
    protected ComprobanteIngresoService $comprobanteIngresoService;
    protected ComprobanteEgresoService $comprobanteEgresoService;
    protected AccountPayableService $accountPayableService;
    protected StorePermissionService $permissionService;

    public function __construct(
        CajaService $cajaService,
        ComprobanteIngresoService $comprobanteIngresoService,
        ComprobanteEgresoService $comprobanteEgresoService,
        AccountPayableService $accountPayableService,
        StorePermissionService $permissionService
    ) {
        $this->cajaService = $cajaService;
        $this->comprobanteIngresoService = $comprobanteIngresoService;
        $this->comprobanteEgresoService = $comprobanteEgresoService;
        $this->accountPayableService = $accountPayableService;
        $this->permissionService = $permissionService;
    }

    public function index(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'caja.view');

        $filtros = [
            'search' => $request->get('search'),
            'is_active' => $request->has('is_active') ? (bool) $request->get('is_active') : null,
            'per_page' => $request->get('per_page', 15),
        ];
        $bolsillos = $this->cajaService->listarBolsillos($store, $filtros);
        $totalCaja = $this->cajaService->totalCaja($store);
        return view('stores.caja', compact('store', 'bolsillos', 'totalCaja'));
    }

    public function showBolsillo(Store $store, Bolsillo $bolsillo, Request $request)
    {
        $this->permissionService->authorize($store, 'caja.view');
        if ($bolsillo->store_id !== $store->id) {
            abort(404);
        }

        $filtros = [
            'bolsillo_id' => $bolsillo->id,
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'per_page' => $request->get('per_page', 15),
        ];
        $movimientos = $this->cajaService->listarMovimientos($store, $filtros);
        $bolsillosActivos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        return view('stores.bolsillo-detalle', compact('store', 'bolsillo', 'movimientos', 'bolsillosActivos'));
    }

    public function storeBolsillo(Store $store, StoreBolsilloRequest $request)
    {
        $this->permissionService->authorize($store, 'caja.bolsillos.create');
        
        try {
            $bolsillo = $this->cajaService->crearBolsillo($store, [
                'name' => $request->input('name'),
                'detalles' => $request->input('detalles'),
                'is_bank_account' => (bool) $request->input('is_bank_account', false),
                'is_active' => (bool) $request->input('is_active', true),
            ]);

            $saldoInicial = (float) ($request->input('saldo') ?? 0);
            if ($saldoInicial > 0) {
                $this->comprobanteIngresoService->crearComprobante($store, Auth::id(), [
                    'date' => now()->toDateString(),
                    'notes' => 'Saldo inicial desde creación del bolsillo "' . $bolsillo->name . '"',
                    'destinos' => [
                        ['bolsillo_id' => $bolsillo->id, 'amount' => $saldoInicial],
                    ],
                ]);
            }

            return redirect()->route('stores.cajas', $store)->with('success', $saldoInicial > 0
                ? 'Bolsillo creado correctamente. Se registró un comprobante de ingreso por el saldo inicial.'
                : 'Bolsillo creado correctamente.');
        } catch (Exception $e) {
            return redirect()->route('stores.cajas', $store)->with('error', $e->getMessage());
        }
    }

    public function updateBolsillo(Store $store, Bolsillo $bolsillo, StoreBolsilloRequest $request)
    {
        $this->permissionService->authorize($store, 'caja.bolsillos.edit');
        if ($bolsillo->store_id !== $store->id) {
            abort(404);
        }
        try {
            $this->cajaService->actualizarBolsillo($bolsillo, [
                'name' => $request->input('name'),
                'detalles' => $request->input('detalles'),
                'is_bank_account' => (bool) $request->input('is_bank_account', false),
                'is_active' => (bool) $request->input('is_active', true),
            ]);
            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with('success', 'Bolsillo actualizado correctamente.');
        } catch (Exception $e) {
            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with('error', $e->getMessage());
        }
    }

    public function destroyBolsillo(Store $store, Bolsillo $bolsillo)
    {
        $this->permissionService->authorize($store, 'caja.bolsillos.destroy');
        if ($bolsillo->store_id !== $store->id) {
            abort(404);
        }
        try {
            $this->cajaService->eliminarBolsillo($bolsillo);
            return redirect()->route('stores.cajas', $store)->with('success', 'Bolsillo eliminado correctamente.');
        } catch (Exception $e) {
            return redirect()->route('stores.cajas', $store)->with('error', $e->getMessage());
        }
    }

    public function comprobantesIngreso(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.view');

        $filtros = [
            'type' => $request->get('type'),
            'customer_id' => $request->get('customer_id'),
        ];
        $comprobantes = $this->comprobanteIngresoService->listar($store, $filtros);

        return view('stores.comprobantes-ingreso', compact('store', 'comprobantes'));
    }

    public function createComprobanteIngreso(Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.create');

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.comprobante-ingreso-crear', compact('store', 'bolsillos'));
    }

    public function storeComprobanteIngreso(Store $store, StoreComprobanteIngresoRequest $request)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.create');
        
        $data = [
            'date' => $request->date,
            'notes' => $request->notes,
            'destinos' => collect($request->input('parts'))->map(fn ($p) => ['bolsillo_id' => $p['bolsillo_id'], 'amount' => (float) $p['amount'], 'reference' => $p['reference'] ?? null])->filter(fn ($d) => $d['amount'] > 0)->values()->all(),
        ];

        try {
            $comprobante = $this->comprobanteIngresoService->crearComprobante($store, Auth::id(), $data);
            return redirect()->route('stores.comprobantes-ingreso.show', [$store, $comprobante])->with('success', 'Comprobante de ingreso creado correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showComprobanteIngreso(Store $store, ComprobanteIngreso $comprobanteIngreso)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.view');
        if ($comprobanteIngreso->store_id !== $store->id) {
            abort(404);
        }

        $comprobanteIngreso = $this->comprobanteIngresoService->obtener($store, $comprobanteIngreso->id);

        return view('stores.comprobante-ingreso-detalle', compact('store', 'comprobanteIngreso'));
    }

    public function comprobantesEgreso(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.view');

        $filtros = [
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];
        $comprobantes = $this->comprobanteEgresoService->listar($store, $filtros);

        return view('stores.comprobantes-egreso', compact('store', 'comprobantes'));
    }

    public function createComprobanteEgreso(Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.create');

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.comprobante-egreso-crear', compact('store', 'bolsillos'));
    }

    public function cuentasPorPagarProveedor(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.create');

        $proveedorId = $request->get('proveedor_id');
        if (! $proveedorId) {
            return response()->json([]);
        }

        $cuentas = $this->accountPayableService->listarCuentasPorPagar($store, [
            'proveedor_id' => (int) $proveedorId,
            'status' => 'pendientes',
            'per_page' => 100,
        ]);

        $data = collect($cuentas->items())->map(fn ($ap) => [
            'id' => $ap->id,
            'purchase_id' => $ap->purchase->id ?? null,
            'proveedor_nombre' => $ap->purchase->proveedor->nombre ?? '—',
            'total_amount' => (float) $ap->total_amount,
            'balance' => (float) $ap->balance,
            'due_date' => $ap->due_date?->format('Y-m-d'),
            'status' => $ap->status,
        ])->values()->all();

        return response()->json($data);
    }

    public function storeComprobanteEgreso(Store $store, StoreComprobanteEgresoRequest $request)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.create');

        $input = $request->all();
        if (isset($input['proveedor_id']) && $input['proveedor_id'] === '') {
            $input['proveedor_id'] = null;
        }
        $request->merge($input);

        try {
            $comprobante = $this->comprobanteEgresoService->crearComprobante($store, Auth::id(), $request->all());
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobante])->with('success', 'Comprobante de egreso registrado correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showComprobanteEgreso(Store $store, ComprobanteEgreso $comprobanteEgreso)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.view');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        $comprobante = $this->comprobanteEgresoService->obtener($store, $comprobanteEgreso->id);
        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.comprobante-egreso-detalle', compact('store', 'comprobante', 'bolsillos'));
    }

    public function editComprobanteEgreso(Store $store, ComprobanteEgreso $comprobanteEgreso)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.edit');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }
        if ($comprobanteEgreso->isReversed()) {
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('error', 'No se puede editar un comprobante revertido.');
        }

        $comprobante = $this->comprobanteEgresoService->obtener($store, $comprobanteEgreso->id);

        return view('stores.comprobante-egreso-editar', compact('store', 'comprobante'));
    }

    public function updateComprobanteEgreso(Store $store, ComprobanteEgreso $comprobanteEgreso, Request $request)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.edit');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->comprobanteEgresoService->actualizarComprobante($store, $comprobanteEgreso->id, $request->only(['payment_date', 'notes']));
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('success', 'Comprobante actualizado correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function reversarComprobanteEgreso(Store $store, ComprobanteEgreso $comprobanteEgreso)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.reversar');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        try {
            $this->comprobanteEgresoService->reversar($store, $comprobanteEgreso->id, Auth::id());
            return redirect()->route('stores.comprobantes-egreso.index', $store)->with('success', 'Comprobante revertido correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function anularComprobanteEgreso(Store $store, ComprobanteEgreso $comprobanteEgreso, Request $request)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.anular');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }
        if ($comprobanteEgreso->isReversed()) {
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('error', 'Este comprobante ya fue anulado.');
        }

        $request->validate([
            'origenes' => ['required', 'array', 'min:1'],
            'origenes.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'origenes.*.amount' => ['required', 'numeric', 'min:0.01'],
            'origenes.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->comprobanteEgresoService->anularComprobante($store, $comprobanteEgreso->id, Auth::id(), $request->input('origenes'));
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('success', 'Comprobante anulado correctamente. El dinero fue devuelto a los bolsillos indicados y las cuentas por pagar fueron restauradas.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
