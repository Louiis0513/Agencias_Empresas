<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCuentaHijoRequest;
use App\Models\CuentaContable;
use App\Models\Store;
use App\Services\CuentaContableService;
use App\Services\ImportacionPucService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreCuentaContableController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected CuentaContableService $cuentaContableService,
        protected ImportacionPucService $importacionPucService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.cuentas.view');

        $filtros = [
            'search' => $request->get('search'),
            'clase' => $request->get('clase'),
            'es_auxiliar' => $request->get('es_auxiliar'),
            'activo' => $request->get('activo'),
        ];
        $modoBusqueda = $request->anyFilled(['search', 'clase', 'es_auxiliar']);

        $stats = $this->cuentaContableService->contarPorStore($store);
        $clases = array_values(CuentaContable::CLASES_POR_DIGITO);
        $categoriasSugeridas = CuentaContable::CATEGORIAS_SUGERIDAS;

        $cuentas = null;
        $usosPorCuenta = [];
        $metaHijos = [];
        $nodosRaiz = [];

        if ($modoBusqueda) {
            $cuentas = $this->cuentaContableService->listar($store, $filtros);
            $usosPorCuenta = $this->cuentaContableService->usosCatalogoPorCuentaIds(
                $store,
                $cuentas->getCollection()->pluck('id')->all()
            );
            foreach ($cuentas->getCollection() as $cuenta) {
                $metaHijos[$cuenta->id] = $this->cuentaContableService->metaCrearHijo($store, $cuenta);
            }
        } else {
            $nodosRaiz = $this->cuentaContableService->nodosRaizArbol($store);
        }

        return view('stores.contabilidad.cuentas', compact(
            'store',
            'cuentas',
            'stats',
            'clases',
            'categoriasSugeridas',
            'usosPorCuenta',
            'metaHijos',
            'nodosRaiz',
            'modoBusqueda'
        ));
    }

    public function hijosJson(Store $store, CuentaContable $cuentaContable)
    {
        $this->permissionService->authorize($store, 'contabilidad.cuentas.view');

        if ($cuentaContable->store_id !== $store->id) {
            abort(404);
        }

        try {
            return response()->json(
                $this->cuentaContableService->hijosDirectosArbol($store, $cuentaContable)
            );
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function importarPuc(Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.cuentas.import');

        try {
            $stats = $this->importacionPucService->importarDesdeExcel($store, null, true);

            return redirect()
                ->route('stores.contabilidad.cuentas', $store)
                ->with('success', sprintf(
                    'PUC base importado. Nuevas: %d. Actualizadas: %d. Auxiliares omitidos: %d.',
                    $stats['importadas'],
                    $stats['actualizadas'],
                    $stats['omitidas_auxiliar']
                ));
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.cuentas', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function storeHijo(StoreCuentaHijoRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.cuentas.create');

        try {
            $resultado = $this->cuentaContableService->crearHijo($store, $request->validated());
            $cuenta = $resultado['cuenta'];
            $accion = CuentaContable::labelNivelPorLongitud(CuentaContable::longitudCodigo($cuenta->codigo));
            $msg = $accion.' '.$cuenta->codigo.' creada correctamente.';
            if ($resultado['traslado_realizado']) {
                $msg .= ' Se trasladaron movimientos y vínculos del padre al nuevo código.';
            }
            if ($cuenta->bolsillo) {
                $msg .= ' También se creó el bolsillo «'.$cuenta->bolsillo->name.'» en Caja.';
            }

            return redirect()
                ->route('stores.contabilidad.cuentas', $store)
                ->with('success', $msg);
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.cuentas', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(Request $request, Store $store, CuentaContable $cuentaContable)
    {
        $this->permissionService->authorize($store, 'contabilidad.cuentas.edit');

        if ($cuentaContable->store_id !== $store->id) {
            abort(404);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = $request->boolean('activo');

        try {
            $this->cuentaContableService->actualizar($store, $cuentaContable, $data);

            return redirect()
                ->route('stores.contabilidad.cuentas', $store)
                ->with('success', 'Cuenta actualizada.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.cuentas', $store)
                ->with('error', $e->getMessage());
        }
    }
}
