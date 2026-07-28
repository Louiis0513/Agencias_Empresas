<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCuentaAuxiliarRequest;
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

        $cuentas = $this->cuentaContableService->listar($store, [
            'search' => $request->get('search'),
            'clase' => $request->get('clase'),
            'es_auxiliar' => $request->get('es_auxiliar'),
            'activo' => $request->get('activo'),
        ]);

        $stats = $this->cuentaContableService->contarPorStore($store);
        $padres = $this->cuentaContableService->padresParaAuxiliar($store);
        $clases = array_values(CuentaContable::CLASES_POR_DIGITO);

        return view('stores.contabilidad.cuentas', compact('store', 'cuentas', 'stats', 'padres', 'clases'));
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

    public function storeAuxiliar(StoreCuentaAuxiliarRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.cuentas.create');

        try {
            $cuenta = $this->cuentaContableService->crearAuxiliar($store, $request->validated());
            $msg = 'Cuenta auxiliar '.$cuenta->codigo.' creada correctamente.';
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
