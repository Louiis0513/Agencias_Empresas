<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaContableRequest;
use App\Models\CategoriaContable;
use App\Models\Store;
use App\Services\CategoriaContableService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreCategoriaContableController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected CategoriaContableService $categoriaContableService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.categorias.view');

        $this->categoriaContableService->asegurarCategoriasPorDefecto($store);

        $categorias = $this->categoriaContableService->listar($store, [
            'search' => $request->get('search'),
            'tipo' => $request->get('tipo'),
            'activo' => $request->get('activo'),
        ]);

        $cuentas = $this->categoriaContableService->cuentasParaSelects($store);
        $codigoSugerido = $this->categoriaContableService->sugerirCodigo($store);

        return view('stores.contabilidad.categorias', compact(
            'store',
            'categorias',
            'cuentas',
            'codigoSugerido'
        ));
    }

    public function store(StoreCategoriaContableRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.categorias.create');

        try {
            $categoria = $this->categoriaContableService->crear($store, $request->validated());

            return redirect()
                ->route('stores.contabilidad.categorias', $store)
                ->with('success', 'Categoría contable «'.$categoria->nombre.'» creada (código '.$categoria->codigo.').');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.categorias', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(StoreCategoriaContableRequest $request, Store $store, CategoriaContable $categoriaContable)
    {
        $this->permissionService->authorize($store, 'contabilidad.categorias.edit');

        if ($categoriaContable->store_id !== $store->id) {
            abort(404);
        }

        try {
            $this->categoriaContableService->actualizar($store, $categoriaContable, $request->validated());

            return redirect()
                ->route('stores.contabilidad.categorias', $store)
                ->with('success', 'Categoría contable actualizada.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.categorias', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
