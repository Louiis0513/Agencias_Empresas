<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReciboCajaRequest;
use App\Models\Bolsillo;
use App\Models\Store;
use App\Models\Tercero;
use App\Models\TipoComprobante;
use App\Services\CentroCostoService;
use App\Services\ComprobanteIngresoService;
use App\Services\StorePermissionService;
use App\Services\StoreTimezoneService;
use App\Services\TipoComprobanteService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreReciboCajaController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected ComprobanteIngresoService $comprobanteIngresoService,
        protected TipoComprobanteService $tipoComprobanteService,
        protected CentroCostoService $centroCostoService,
        protected StoreTimezoneService $storeTimezoneService,
    ) {}

    public function create(Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.create');

        $this->tipoComprobanteService->asegurarTiposPorDefecto($store);

        $tiposRc = TipoComprobante::query()
            ->deStore($store)
            ->activas()
            ->where('familia', TipoComprobante::FAMILIA_RC)
            ->orderByRaw('CAST(codigo AS UNSIGNED) asc')
            ->orderBy('codigo')
            ->get();

        // En RC, "Forma de pago" = bolsillos activos con cuenta 11… (estilo Siigo).
        $formasPago = Bolsillo::deTienda($store->id)
            ->activos()
            ->whereNotNull('cuenta_contable_id')
            ->with(['cuentaContable:id,codigo,nombre'])
            ->orderBy('name')
            ->get()
            ->map(fn (Bolsillo $b) => [
                'id' => (string) $b->id,
                'nombre' => $b->name,
                'cuenta_codigo' => $b->cuentaContable?->codigo,
                'cuenta_nombre' => $b->cuentaContable?->nombre,
                'label' => trim(
                    $b->name
                    .($b->cuentaContable
                        ? ' — '.$b->cuentaContable->codigo.' '.$b->cuentaContable->nombre
                        : '')
                ),
            ])
            ->values();

        $centros = $this->centroCostoService->opcionesParaAsiento($store);
        $clientes = Tercero::query()
            ->deStore($store)
            ->activos()
            ->conRol(Tercero::ROL_CLIENTE)
            ->orderBy('nombre')
            ->limit(300)
            ->get(['id', 'nombre', 'numero_identificacion']);

        $fechaHoy = $this->storeTimezoneService->nowForStore($store)->toDateString();
        $tipoDefault = $tiposRc->first();

        return view('stores.recibos-caja.crear', compact(
            'store',
            'tiposRc',
            'formasPago',
            'centros',
            'clientes',
            'fechaHoy',
            'tipoDefault'
        ));
    }

    public function store(StoreReciboCajaRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.create');

        try {
            $comprobante = $this->comprobanteIngresoService->crearComprobante(
                $store,
                Auth::id(),
                $request->validated()
            );

            return redirect()
                ->route('stores.comprobantes-ingreso.show', [$store, $comprobante])
                ->with('success', 'Recibo de caja '.$comprobante->number.' guardado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.recibos-caja.create', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function cuentasPendientes(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.create');

        $terceroId = (int) $request->query('tercero_id', 0);
        if ($terceroId <= 0) {
            return response()->json(['data' => [], 'saldo_actual' => 0]);
        }

        $cuotas = $this->comprobanteIngresoService->cuotasPendientesDelTercero($store, $terceroId);
        $saldoActual = round(array_sum(array_map(fn ($c) => (float) ($c['pending'] ?? 0), $cuotas)), 2);

        return response()->json([
            'data' => $cuotas,
            'saldo_actual' => $saldoActual,
        ]);
    }
}
