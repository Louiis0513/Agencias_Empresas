<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBolsilloRequest;
use App\Http\Requests\StoreComprobanteEgresoRequest;
use App\Http\Requests\StoreComprobanteIngresoRequest;
use App\Models\Bolsillo;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteIngreso;
use App\Models\Customer;
use App\Models\Proveedor;
use App\Models\SesionCaja;
use App\Models\Store;
use App\Models\Worker;
use App\Services\AccountPayableService;
use App\Services\AccountReceivableService;
use App\Services\CajaService;
use App\Services\ComprobanteEgresoService;
use App\Services\ComprobanteIngresoService;
use App\Services\CustomerService;
use App\Services\MovimientosExcelExportService;
use App\Services\SesionCajaService;
use App\Services\StorePermissionService;
use App\Services\StoreTimezoneService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreCajaController extends Controller
{
    /** Tamaño de página en listados de Movimientos; subir cuando definan producción. */
    private const MOVIMIENTOS_LIST_PER_PAGE = 10;

    protected CajaService $cajaService;

    protected ComprobanteIngresoService $comprobanteIngresoService;

    protected ComprobanteEgresoService $comprobanteEgresoService;

    protected AccountPayableService $accountPayableService;

    protected AccountReceivableService $accountReceivableService;

    protected CustomerService $customerService;

    protected StorePermissionService $permissionService;

    protected SesionCajaService $sesionCajaService;

    protected StoreTimezoneService $storeTimezoneService;

    public function __construct(
        CajaService $cajaService,
        ComprobanteIngresoService $comprobanteIngresoService,
        ComprobanteEgresoService $comprobanteEgresoService,
        AccountPayableService $accountPayableService,
        AccountReceivableService $accountReceivableService,
        CustomerService $customerService,
        StorePermissionService $permissionService,
        SesionCajaService $sesionCajaService,
        StoreTimezoneService $storeTimezoneService
    ) {
        $this->cajaService = $cajaService;
        $this->comprobanteIngresoService = $comprobanteIngresoService;
        $this->comprobanteEgresoService = $comprobanteEgresoService;
        $this->accountPayableService = $accountPayableService;
        $this->accountReceivableService = $accountReceivableService;
        $this->customerService = $customerService;
        $this->permissionService = $permissionService;
        $this->sesionCajaService = $sesionCajaService;
        $this->storeTimezoneService = $storeTimezoneService;
    }

    public function index(Store $store)
    {
        $this->permissionService->authorize($store, 'caja.view');

        return redirect()->route('stores.cajas.movimientos', $store);
    }

    /**
     * Vista Movimientos / transacciones (lectura). Ingresos: líneas por destino; egresos: por origen (bolsillo).
     */
    public function movimientos(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'caja.view');

        $sesionAbierta = $this->sesionCajaService->obtenerSesionAbierta($store);

        $tab = $request->get('tab', 'ingresos');

        $mf = $this->normalizeMovimientosFiltros($request, $store);

        $movimientosBolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get(['id', 'name']);
        $movimientosEmpleados = $this->movimientosEmpleadosOpciones($store);

        $movCustomerLabel = null;
        if ($mf['customer_id']) {
            $movCustomerLabel = Customer::where('store_id', $store->id)->where('id', $mf['customer_id'])->value('nombre');
        }
        $movProveedorNombre = null;
        if ($mf['proveedor_id']) {
            $movProveedorNombre = Proveedor::where('store_id', $store->id)->where('id', $mf['proveedor_id'])->value('nombre');
        }

        $filtrosMovimientosBase = [
            'fecha_desde' => $mf['fecha_desde'],
            'fecha_hasta' => $mf['fecha_hasta'],
            'search' => $mf['search'],
            'bolsillo_ids' => $mf['bolsillo_ids'],
            'empleado_user_ids' => $mf['empleado_user_ids'],
            'timezone' => $this->storeTimezoneService->getTimezoneForStore($store),
        ];

        $totalIngresosMov = $this->comprobanteIngresoService->sumarMontosDestinosMovimientos($store, array_merge($filtrosMovimientosBase, [
            'customer_id' => $mf['customer_id'],
        ]));
        $totalEgresosMov = $this->comprobanteEgresoService->sumarMontosOrigenesMovimientos($store, array_merge($filtrosMovimientosBase, [
            'proveedor_id' => $mf['proveedor_id'],
        ]));
        $balanceMov = $totalIngresosMov - $totalEgresosMov;

        $movimientosResumen = [
            'ingresos' => $totalIngresosMov,
            'egresos' => $totalEgresosMov,
            'balance' => $balanceMov,
        ];

        if ($mf['fecha_dia']) {
            $tz = $this->storeTimezoneService->getTimezoneForStore($store);
            $fechaRef = Carbon::parse($mf['fecha_dia'], $tz)->startOfDay();
            $movimientosResumenEtiqueta = __('Según filtros · :fecha', [
                'fecha' => $this->storeTimezoneService->formatForStore($fechaRef, $store, false),
            ]);
        } else {
            $movimientosResumenEtiqueta = __('Según filtros · sin día (todo el historial)');
        }

        $ingresosLineas = null;
        if ($tab === 'ingresos') {
            $ingresosLineas = $this->comprobanteIngresoService->listarDestinosPaginadosParaMovimientos($store, array_merge($filtrosMovimientosBase, [
                'customer_id' => $mf['customer_id'],
                'per_page' => self::MOVIMIENTOS_LIST_PER_PAGE,
            ]))->withQueryString();
        }

        $egresosLineas = null;
        if ($tab === 'egresos') {
            $egresosLineas = $this->comprobanteEgresoService->listarOrigenesPaginadosParaMovimientos($store, array_merge($filtrosMovimientosBase, [
                'proveedor_id' => $mf['proveedor_id'],
                'per_page' => self::MOVIMIENTOS_LIST_PER_PAGE,
            ]))->withQueryString();
        }

        $cuentasPorCobrar = null;
        $saldoPendienteCobrar = null;
        $customersParaCobrar = null;
        if ($tab === 'por-cobrar') {
            $this->permissionService->authorize($store, 'accounts-receivables.view');
            $cuentasPorCobrar = $this->accountReceivableService->listar($store, [
                'status' => $request->get('pc_status'),
                'customer_id' => $mf['customer_id'],
                'invoice_user_ids' => $mf['empleado_user_ids'],
                'per_page' => self::MOVIMIENTOS_LIST_PER_PAGE,
            ])->withQueryString();
            $saldoPendienteCobrar = $this->accountReceivableService->saldoPendienteTotal($store);
            $customersParaCobrar = $this->customerService->getAllStoreCustomers($store);
        }

        $cuentasPorPagar = null;
        $deudaTotalPagar = null;
        if ($tab === 'por-pagar') {
            $this->permissionService->authorize($store, 'accounts-payables.view');
            $cuentasPorPagarFiltros = [
                'status' => $request->get('pp_status'),
                'per_page' => self::MOVIMIENTOS_LIST_PER_PAGE,
            ];
            if ($mf['proveedor_id']) {
                $cuentasPorPagarFiltros['proveedor_id'] = $mf['proveedor_id'];
            }
            $cuentasPorPagar = $this->accountPayableService->listarCuentasPorPagar($store, $cuentasPorPagarFiltros)->withQueryString();
            $deudaTotalPagar = $this->accountPayableService->deudaTotal($store);
        }

        $filtrosBolsillosListado = [
            'search' => $request->get('bolsillo_search'),
            'is_active' => $request->has('bolsillo_is_active') ? (bool) $request->get('bolsillo_is_active') : null,
            'per_page' => $request->get('bolsillo_per_page', 15),
            'page_name' => 'bolsillo_page',
        ];
        $bolsillosListado = $this->cajaService->listarBolsillos($store, $filtrosBolsillosListado)->withQueryString();
        $totalCaja = $this->cajaService->totalCaja($store);
        $canAccessStoreConfig = $this->permissionService->can($store, 'store-config.view');
        $movExportMesDefault = $this->storeTimezoneService->nowForStore($store)->format('Y-m');

        return view('stores.caja.movimientos', compact(
            'store',
            'tab',
            'mf',
            'movimientosBolsillos',
            'movimientosEmpleados',
            'movCustomerLabel',
            'movProveedorNombre',
            'movimientosResumen',
            'movimientosResumenEtiqueta',
            'ingresosLineas',
            'egresosLineas',
            'cuentasPorCobrar',
            'saldoPendienteCobrar',
            'customersParaCobrar',
            'cuentasPorPagar',
            'deudaTotalPagar',
            'sesionAbierta',
            'bolsillosListado',
            'totalCaja',
            'canAccessStoreConfig',
            'movExportMesDefault'
        ));
    }

    /**
     * Excel (Resumen, Ingresos, Egresos; opcional Por cobrar / Por pagar según permisos) con filtros GET de Movimientos y opcional export_mes (YYYY-MM).
     */
    public function exportMovimientosExcel(Store $store, Request $request, MovimientosExcelExportService $excelExport)
    {
        $this->permissionService->authorize($store, 'caja.view');

        $mf = $this->normalizeMovimientosFiltros($request, $store);

        $exportMesRaw = $request->query('export_mes');
        if (is_string($exportMesRaw) && preg_match('/^\d{4}-\d{2}$/', trim($exportMesRaw))) {
            $exportMes = trim($exportMesRaw);
            $tz = $this->storeTimezoneService->getTimezoneForStore($store);
            $startMonth = Carbon::parse($exportMes.'-01', $tz)->startOfMonth();
            $endMonth = Carbon::parse($exportMes.'-01', $tz)->endOfMonth();
            $mf['fecha_desde'] = $startMonth->toDateString();
            $mf['fecha_hasta'] = $endMonth->toDateString();
            $mf['fecha_dia'] = null;
            $mf['export_mes'] = $exportMes;
        }

        $filtrosMovimientosBase = [
            'fecha_desde' => $mf['fecha_desde'],
            'fecha_hasta' => $mf['fecha_hasta'],
            'search' => $mf['search'],
            'bolsillo_ids' => $mf['bolsillo_ids'],
            'empleado_user_ids' => $mf['empleado_user_ids'],
            'timezone' => $this->storeTimezoneService->getTimezoneForStore($store),
        ];

        $tz = $this->storeTimezoneService->getTimezoneForStore($store);
        if (! empty($mf['export_mes'])) {
            $refMes = Carbon::parse($mf['export_mes'].'-01', $tz)->startOfMonth();
            $movimientosResumenEtiqueta = __('Exportación por mes · :mes', [
                'mes' => $refMes->locale(app()->getLocale())->translatedFormat('F Y'),
            ]);
        } elseif ($mf['fecha_dia']) {
            $fechaRef = Carbon::parse($mf['fecha_dia'], $tz)->startOfDay();
            $movimientosResumenEtiqueta = __('Según filtros · :fecha', [
                'fecha' => $this->storeTimezoneService->formatForStore($fechaRef, $store, false),
            ]);
        } else {
            $movimientosResumenEtiqueta = __('Según filtros · sin día (todo el historial)');
        }

        $cuentasPorCobrarExport = null;
        $saldoPendienteCobrarExport = null;
        if ($this->permissionService->can($store, 'accounts-receivables.view')) {
            $cuentasPorCobrarExport = $this->accountReceivableService->coleccionParaExportacion($store, [
                'status' => $request->get('pc_status'),
                'customer_id' => $mf['customer_id'],
                'invoice_user_ids' => $mf['empleado_user_ids'],
                'fecha_desde' => $mf['fecha_desde'],
                'fecha_hasta' => $mf['fecha_hasta'],
                'timezone' => $filtrosMovimientosBase['timezone'],
            ]);
            $saldoPendienteCobrarExport = $this->accountReceivableService->saldoPendienteTotal($store);
        }

        $cuentasPorPagarExport = null;
        $deudaTotalPagarExport = null;
        if ($this->permissionService->can($store, 'accounts-payables.view')) {
            $filtrosCxp = [
                'status' => $request->get('pp_status'),
                'fecha_desde' => $mf['fecha_desde'],
                'fecha_hasta' => $mf['fecha_hasta'],
                'timezone' => $filtrosMovimientosBase['timezone'],
                'search' => $request->get('pp_search'),
            ];
            if ($mf['proveedor_id']) {
                $filtrosCxp['proveedor_id'] = $mf['proveedor_id'];
            }
            $cuentasPorPagarExport = $this->accountPayableService->coleccionParaExportacionCuentasPorPagar($store, $filtrosCxp);
            $deudaTotalPagarExport = $this->accountPayableService->deudaTotal($store);
        }

        return $excelExport->download(
            $store,
            $mf,
            $filtrosMovimientosBase,
            $movimientosResumenEtiqueta,
            $cuentasPorCobrarExport,
            $cuentasPorPagarExport,
            $saldoPendienteCobrarExport,
            $deudaTotalPagarExport,
        );
    }

    /**
     * @return array{fecha_desde: ?string, fecha_hasta: ?string, fecha_dia: ?string, search: ?string, bolsillo_ids: array<int>, empleado_user_ids: array<int>, customer_id: ?int, proveedor_id: ?int}
     */
    protected function normalizeMovimientosFiltros(Request $request, Store $store): array
    {
        $fechaDesde = null;
        $fechaHasta = null;
        $fechaDia = null;

        if ($request->query->has('mov_fecha')) {
            $raw = $request->query('mov_fecha');
            if ($raw === null || trim((string) $raw) === '') {
                $fechaDesde = null;
                $fechaHasta = null;
                $fechaDia = null;
            } else {
                $d = trim((string) $raw);
                $fechaDesde = $d;
                $fechaHasta = $d;
                $fechaDia = $d;
            }
        } else {
            $fechaDesde = $request->get('mov_fecha_desde');
            $fechaHasta = $request->get('mov_fecha_hasta');
            if ($fechaDesde === null || $fechaDesde === '') {
                $fechaDesde = $request->get('fecha_desde') ?: $request->get('egreso_fecha_desde');
            }
            if ($fechaHasta === null || $fechaHasta === '') {
                $fechaHasta = $request->get('fecha_hasta') ?: $request->get('egreso_fecha_hasta');
            }
            $fechaDesde = $fechaDesde !== null && $fechaDesde !== '' ? (string) $fechaDesde : null;
            $fechaHasta = $fechaHasta !== null && $fechaHasta !== '' ? (string) $fechaHasta : null;
            if ($fechaDesde !== null && $fechaHasta !== null && $fechaDesde === $fechaHasta) {
                $fechaDia = $fechaDesde;
            }
            if ($fechaDesde === null && $fechaHasta === null) {
                $today = $this->storeTimezoneService->nowForStore($store)->toDateString();
                $fechaDesde = $today;
                $fechaHasta = $today;
                $fechaDia = $today;
            }
        }

        $search = $request->get('mov_search');
        if ($search === null || $search === '') {
            $search = $request->get('search') ?: $request->get('egreso_search');
        }
        $search = $search !== null && trim((string) $search) !== '' ? trim((string) $search) : null;

        $bolsilloIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->get('bolsillo_ids', [])))));
        $empleadoUserIds = array_values(array_unique(array_filter(array_map('intval', (array) $request->get('empleado_user_ids', [])))));

        $customerId = $request->get('mov_customer_id');
        if ($customerId === null || $customerId === '') {
            $customerId = $request->get('pc_customer_id');
        }
        $customerId = $customerId !== null && $customerId !== '' ? (int) $customerId : null;

        $proveedorId = $request->get('mov_proveedor_id');
        $proveedorId = $proveedorId !== null && $proveedorId !== '' ? (int) $proveedorId : null;

        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_dia' => $fechaDia,
            'search' => $search,
            'bolsillo_ids' => $bolsilloIds,
            'empleado_user_ids' => $empleadoUserIds,
            'customer_id' => $customerId,
            'proveedor_id' => $proveedorId,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{user_id: int, name: string, subtitle: string}>
     */
    protected function movimientosEmpleadosOpciones(Store $store): \Illuminate\Support\Collection
    {
        $rows = collect();
        $owner = $store->owner;
        if ($owner) {
            $rows->push([
                'user_id' => $owner->id,
                'name' => $owner->name,
                'subtitle' => __('Dueño'),
            ]);
        }
        foreach (Worker::deTienda($store->id)->with('role')->orderBy('nombre')->get() as $w) {
            if ($w->user_id) {
                $rows->push([
                    'user_id' => (int) $w->user_id,
                    'name' => $w->name,
                    'subtitle' => $w->role->name ?? __('Trabajador'),
                ]);
            }
        }

        return $rows->unique('user_id')->values();
    }

    public function aperturaCaja(Store $store)
    {
        $this->permissionService->authorize($store, 'caja.sesiones.abrir');

        if ($this->sesionCajaService->obtenerSesionAbierta($store)) {
            return redirect()->route('stores.cajas.movimientos', $store)->with('error', 'Ya hay una sesión de caja abierta.');
        }

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        $saldosEsperados = $this->sesionCajaService->obtenerSaldosEsperadosParaApertura($store);

        return view('stores.caja.caja-apertura', compact('store', 'bolsillos', 'saldosEsperados'));
    }

    public function storeAperturaCaja(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'caja.sesiones.abrir');

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        $saldosFisicos = [];
        foreach ($bolsillos as $b) {
            $saldosFisicos[$b->id] = (float) $request->input('saldo_fisico.'.$b->id, 0);
        }

        try {
            $this->sesionCajaService->abrirSesion($store, (int) Auth::id(), $saldosFisicos, $request->input('nota_apertura'));

            return redirect()->route('stores.cajas.movimientos', $store)->with('success', 'Sesión de caja abierta correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cerrarCaja(Store $store)
    {
        $this->permissionService->authorize($store, 'caja.sesiones.cerrar');

        $sesionAbierta = $this->sesionCajaService->obtenerSesionAbierta($store);
        if (! $sesionAbierta) {
            return redirect()->route('stores.cajas.movimientos', $store)->with('error', 'No hay una sesión de caja abierta para cerrar.');
        }

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.caja.caja-cerrar-wizard', compact('store', 'sesionAbierta', 'bolsillos'));
    }

    public function storeCierreCaja(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'caja.sesiones.cerrar');

        $request->validate([
            'saldo_fisico' => ['required', 'array'],
            'saldo_fisico.*' => ['nullable', 'numeric', 'min:0'],
            'nota_cierre' => ['nullable', 'string', 'max:500'],
        ]);

        $saldosFisicos = [];
        foreach ($request->input('saldo_fisico', []) as $bolsilloId => $valor) {
            $saldosFisicos[(int) $bolsilloId] = (float) $valor;
        }

        try {
            $this->sesionCajaService->cerrarSesion($store, (int) Auth::id(), $saldosFisicos, $request->input('nota_cierre'));

            return redirect()->route('stores.cajas.movimientos', $store)->with('success', 'Sesión de caja cerrada correctamente.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function sesiones(Store $store, Request $request)
    {
        $this->permissionService->authorize($store, 'caja.sesiones.view');

        $filtros = [
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'per_page' => $request->get('per_page', 15),
        ];
        $sesiones = $this->sesionCajaService->listarSesiones($store, $filtros);

        return view('stores.caja.caja-sesiones', compact('store', 'sesiones'));
    }

    public function showSesion(Store $store, SesionCaja $sesionCaja)
    {
        $this->permissionService->authorize($store, 'caja.sesiones.view');
        if ($sesionCaja->store_id !== $store->id) {
            abort(404);
        }

        $sesionCaja = $this->sesionCajaService->obtenerSesion($store, $sesionCaja->id);

        return view('stores.caja.caja-sesion-detalle', compact('store', 'sesionCaja'));
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
        $bolsillo->loadMissing('cuentaContable');
        $movimientos = $this->cajaService->listarMovimientos($store, $filtros);
        $bolsillosActivos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.caja.bolsillo-detalle', compact('store', 'bolsillo', 'movimientos', 'bolsillosActivos'));
    }

    public function storeBolsillo(Store $store, StoreBolsilloRequest $request)
    {
        $this->permissionService->authorize($store, 'caja.bolsillos.create');

        try {
            $payload = [
                'name' => $request->input('name'),
                'detalles' => $request->input('detalles'),
                'is_active' => (bool) $request->input('is_active', true),
            ];
            if ($request->filled('tipo_disponible')) {
                $payload['tipo_disponible'] = $request->input('tipo_disponible');
            } else {
                $payload['is_bank_account'] = (bool) $request->input('is_bank_account', false);
            }
            $bolsillo = $this->cajaService->crearBolsillo($store, $payload);

            $saldoInicial = (float) ($request->input('saldo') ?? 0);
            if ($saldoInicial > 0) {
                $this->comprobanteIngresoService->crearComprobante($store, Auth::id(), [
                    'date' => now()->toDateString(),
                    'notes' => 'Saldo inicial desde creación del bolsillo "'.$bolsillo->name.'"',
                    'destinos' => [
                        ['bolsillo_id' => $bolsillo->id, 'amount' => $saldoInicial],
                    ],
                ]);
            }

            return $this->redirectBolsilloListAfterMutation($store, 'success', $saldoInicial > 0
                ? 'Bolsillo creado correctamente. Se registró un comprobante de ingreso por el saldo inicial.'
                : 'Bolsillo creado correctamente.');
        } catch (Exception $e) {
            return $this->redirectBolsilloListAfterMutation($store, 'error', $e->getMessage());
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
                'is_active' => (bool) $request->input('is_active', true),
            ]);

            return $this->redirectAfterBolsilloUpdated($store, $bolsillo, 'success', 'Bolsillo actualizado correctamente.');
        } catch (Exception $e) {
            return $this->redirectAfterBolsilloUpdated($store, $bolsillo, 'error', $e->getMessage());
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

            return $this->redirectBolsilloListAfterMutation($store, 'success', 'Bolsillo eliminado correctamente.');
        } catch (Exception $e) {
            return $this->redirectBolsilloListAfterMutation($store, 'error', $e->getMessage());
        }
    }

    public function createComprobanteIngreso(Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.create');

        if (! $this->sesionCajaService->obtenerSesionAbierta($store)) {
            return redirect()->route('stores.cajas.movimientos', [$store, 'tab' => 'ingresos'])->with('error', 'No hay una sesión de caja abierta. Abra la caja para registrar comprobantes de ingreso.');
        }

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.comprobantes.comprobante-ingreso-crear', compact('store', 'bolsillos'));
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
        $ciVista = $this->comprobanteIngresoService->datosVistaComprobante($store, $comprobanteIngreso);

        return view('stores.comprobantes.comprobante-ingreso-detalle', array_merge(
            compact('store', 'comprobanteIngreso'),
            $ciVista
        ));
    }

    public function pdfComprobanteIngreso(Store $store, ComprobanteIngreso $comprobanteIngreso)
    {
        $this->permissionService->authorize($store, 'comprobantes-ingreso.view');
        if ($comprobanteIngreso->store_id !== $store->id) {
            abort(404);
        }

        $comprobanteIngreso = $this->comprobanteIngresoService->obtener($store, $comprobanteIngreso->id);
        $ciVista = $this->comprobanteIngresoService->datosVistaComprobante($store, $comprobanteIngreso);

        $pdf = Pdf::loadView('stores.comprobantes.comprobante-ingreso-pdf', array_merge(
            compact('store', 'comprobanteIngreso'),
            $ciVista
        ));
        $pdf->setPaper('a4', 'portrait');

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', $comprobanteIngreso->number);

        return $pdf->stream('comprobante-ingreso-'.$safeNumber.'.pdf');
    }

    public function createComprobanteEgreso(Store $store)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.create');

        if (! $this->sesionCajaService->obtenerSesionAbierta($store)) {
            return redirect()->route('stores.cajas.movimientos', [$store, 'tab' => 'egresos'])->with('error', 'No hay una sesión de caja abierta. Abra la caja para registrar comprobantes de egreso.');
        }

        $bolsillos = Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.comprobantes.comprobante-egreso-crear', compact('store', 'bolsillos'));
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
        $ceVista = $this->comprobanteEgresoService->datosVistaComprobanteEgreso($store, $comprobante);

        return view('stores.comprobantes.comprobante-egreso-detalle', array_merge(
            compact('store', 'comprobante', 'bolsillos'),
            $ceVista
        ));
    }

    public function pdfComprobanteEgreso(Store $store, ComprobanteEgreso $comprobanteEgreso)
    {
        $this->permissionService->authorize($store, 'comprobantes-egreso.view');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        $comprobante = $this->comprobanteEgresoService->obtener($store, $comprobanteEgreso->id);
        $ceVista = $this->comprobanteEgresoService->datosVistaComprobanteEgreso($store, $comprobante);

        $pdf = Pdf::loadView('stores.comprobantes.comprobante-egreso-pdf', array_merge(
            compact('store', 'comprobante'),
            $ceVista
        ));
        $pdf->setPaper('a4', 'portrait');

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', $comprobante->number);

        return $pdf->stream('comprobante-egreso-'.$safeNumber.'.pdf');
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

        return view('stores.comprobantes.comprobante-egreso-editar', compact('store', 'comprobante'));
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

            return redirect()->route('stores.cajas.movimientos', [$store, 'tab' => 'egresos'])->with('success', 'Comprobante revertido correctamente.');
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
                ->with('success', 'Comprobante anulado correctamente. El dinero fue devuelto a los bolsillos indicados y las CxP fueron restauradas.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    protected function redirectBolsilloListAfterMutation(Store $store, string $flashKey, string $message)
    {
        if ($this->permissionService->can($store, 'store-config.view')) {
            return redirect()->to(route('stores.configuracion', $store).'?panel=caja')->with($flashKey, $message);
        }

        return redirect()->route('stores.cajas.movimientos', $store)->with($flashKey, $message);
    }

    protected function redirectAfterBolsilloUpdated(Store $store, Bolsillo $bolsillo, string $flashKey, string $message)
    {
        if ($this->permissionService->can($store, 'store-config.view')) {
            return redirect()->to(route('stores.configuracion', $store).'?panel=caja')->with($flashKey, $message);
        }

        return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with($flashKey, $message);
    }
}
