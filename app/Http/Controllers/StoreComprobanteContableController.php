<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComprobanteContableRequest;
use App\Models\ComprobanteContable;
use App\Models\Store;
use App\Services\AsientoContableService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreComprobanteContableController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected AsientoContableService $asientoService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.view');

        $comprobantes = $this->asientoService->listar($store, [
            'search' => $request->get('search'),
            'estado' => $request->get('estado'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ]);

        return view('stores.contabilidad.comprobantes-index', [
            'store' => $store,
            'comprobantes' => $comprobantes,
            'estados' => ComprobanteContable::ESTADOS,
        ]);
    }

    public function create(Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.create');

        return view('stores.contabilidad.comprobantes-form', [
            'store' => $store,
            'comprobante' => null,
            ...$this->opcionesFormulario($store),
        ]);
    }

    public function diario(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.view');

        return view('stores.contabilidad.libro-diario', [
            'store' => $store,
            'movimientos' => $this->asientoService->libroDiario($store, [
                'search' => $request->get('search'),
                'fecha_desde' => $request->get('fecha_desde'),
                'fecha_hasta' => $request->get('fecha_hasta'),
                'cuenta_contable_id' => $request->get('cuenta_contable_id'),
            ]),
            'cuentas' => $this->asientoService->cuentasDisponibles($store),
        ]);
    }

    public function store(StoreComprobanteContableRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.create');

        try {
            $comprobante = $this->asientoService->crearBorrador(
                $store,
                (int) $request->user()->id,
                $request->validated()
            );

            return redirect()
                ->route('stores.contabilidad.comprobantes.show', [$store, $comprobante])
                ->with('success', 'Comprobante guardado en borrador.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Store $store, ComprobanteContable $comprobanteContable)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.view');
        $comprobante = $this->asientoService->obtener($store, $comprobanteContable->id);

        return view('stores.contabilidad.comprobantes-show', compact('store', 'comprobante'));
    }

    public function edit(Store $store, ComprobanteContable $comprobanteContable)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.edit');
        $comprobante = $this->asientoService->obtener($store, $comprobanteContable->id);
        if (! $comprobante->esBorrador()) {
            return redirect()
                ->route('stores.contabilidad.comprobantes.show', [$store, $comprobante])
                ->with('error', 'Solo se pueden editar comprobantes en borrador.');
        }

        return view('stores.contabilidad.comprobantes-form', [
            'store' => $store,
            'comprobante' => $comprobante,
            ...$this->opcionesFormulario($store),
        ]);
    }

    public function update(
        StoreComprobanteContableRequest $request,
        Store $store,
        ComprobanteContable $comprobanteContable
    ) {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.edit');

        try {
            $comprobante = $this->asientoService->actualizarBorrador(
                $store,
                $comprobanteContable,
                $request->validated()
            );

            return redirect()
                ->route('stores.contabilidad.comprobantes.show', [$store, $comprobante])
                ->with('success', 'Borrador actualizado.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function contabilizar(Store $store, ComprobanteContable $comprobanteContable)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.post');

        try {
            $comprobante = $this->asientoService->contabilizar(
                $store,
                $comprobanteContable,
                (int) auth()->id()
            );

            return redirect()
                ->route('stores.contabilidad.comprobantes.show', [$store, $comprobante])
                ->with('success', 'Comprobante '.$comprobante->numero.' contabilizado.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reversar(Store $store, ComprobanteContable $comprobanteContable)
    {
        $this->permissionService->authorize($store, 'contabilidad.comprobantes.reverse');

        try {
            $reverso = $this->asientoService->reversar(
                $store,
                $comprobanteContable,
                (int) auth()->id()
            );

            return redirect()
                ->route('stores.contabilidad.comprobantes.show', [$store, $reverso])
                ->with('success', 'Reversión '.$reverso->numero.' creada y contabilizada.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function opcionesFormulario(Store $store): array
    {
        return [
            'tipos' => $this->asientoService->tiposCcDisponibles($store),
            'cuentas' => $this->asientoService->cuentasDisponibles($store),
            'terceros' => $this->asientoService->tercerosDisponibles($store),
        ];
    }
}
