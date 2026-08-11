<?php

namespace App\Http\Controllers;

use App\Models\ListaPrecio;
use App\Models\Store;
use App\Services\ListaPrecioService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreListaPrecioController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected ListaPrecioService $listaPrecioService,
    ) {}

    public function index(Store $store)
    {
        $this->permissionService->authorize($store, 'products.listas-precios.view');

        return view('stores.productos.listas-precios', [
            'store' => $store,
            'items' => $this->listaPrecioService->listar($store),
        ]);
    }

    public function update(Request $request, Store $store, ListaPrecio $listaPrecio)
    {
        $this->permissionService->authorize($store, 'products.listas-precios.edit');

        if ((int) $listaPrecio->store_id !== (int) $store->id) {
            abort(404);
        }

        $request->merge([
            'activo' => $request->boolean('activo'),
        ]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'activo' => ['required', 'boolean'],
        ]);

        try {
            $this->listaPrecioService->actualizar($store, $listaPrecio, $data);
        } catch (Exception $e) {
            return redirect()
                ->route('stores.products.listas-precios', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('stores.products.listas-precios', $store)
            ->with('success', 'Lista «'.$listaPrecio->fresh()->nombre.'» actualizada.');
    }
}
