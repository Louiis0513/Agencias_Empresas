<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImpuestoRequest;
use App\Models\CuentaContable;
use App\Models\Impuesto;
use App\Models\Store;
use App\Services\ImpuestoService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreImpuestoController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected ImpuestoService $impuestoService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.impuestos.view');

        $defaults = $this->impuestoService->asegurarDefaults($store);

        if ($defaults['creadas'] > 0) {
            session()->flash(
                'success',
                'Se crearon '.$defaults['creadas'].' impuesto(s) por defecto con sus cuentas contables.'
            );
        }

        if ($defaults['errores'] !== []) {
            session()->flash(
                'warning',
                'Algunos impuestos no se pudieron crear: '.implode(' | ', $defaults['errores'])
            );
        }

        $cuentaFiltroId = $request->integer('cuenta_contable_id') ?: null;
        $cuentaFiltro = null;
        if ($cuentaFiltroId) {
            $cuentaFiltro = CuentaContable::query()
                ->deStore($store)
                ->whereKey($cuentaFiltroId)
                ->first(['id', 'codigo', 'nombre']);
        }

        return view('stores.contabilidad.impuestos', [
            'store' => $store,
            'impuestos' => $this->impuestoService->listar($store, [
                'search' => $request->get('search'),
                'tipo' => $request->get('tipo'),
                'en_uso' => $request->get('en_uso'),
                'cuenta_contable_id' => $cuentaFiltroId,
            ]),
            'tipos' => Impuesto::TIPOS,
            'cuentas' => $this->impuestoService->cuentasDisponibles($store),
            'codigoSugerido' => $this->impuestoService->siguienteCodigo($store),
            'cuentaFiltro' => $cuentaFiltro,
        ]);
    }

    public function store(StoreImpuestoRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.impuestos.create');

        try {
            $impuesto = $this->impuestoService->crear($store, $request->validated());

            return redirect()
                ->route('stores.contabilidad.impuestos', $store)
                ->with('success', 'Impuesto «'.$impuesto->codigo.' — '.$impuesto->nombre.'» creado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.impuestos', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(StoreImpuestoRequest $request, Store $store, Impuesto $impuesto)
    {
        $this->permissionService->authorize($store, 'contabilidad.impuestos.edit');

        if ($impuesto->store_id !== $store->id) {
            abort(404);
        }

        try {
            $this->impuestoService->actualizar($store, $impuesto, $request->validated());

            return redirect()
                ->route('stores.contabilidad.impuestos', $store)
                ->with('success', 'Impuesto actualizado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.impuestos', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
