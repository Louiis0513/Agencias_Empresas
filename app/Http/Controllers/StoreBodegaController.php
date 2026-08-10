<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBodegaRequest;
use App\Models\Bodega;
use App\Models\Store;
use App\Services\BodegaService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreBodegaController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected BodegaService $bodegaService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'products.bodegas.view');

        return view('stores.productos.bodegas', [
            'store' => $store,
            'items' => $this->bodegaService->listar($store, [
                'search' => $request->get('search'),
                'activo' => $request->get('activo'),
            ]),
        ]);
    }

    public function store(StoreBodegaRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'products.bodegas.create');

        try {
            $item = $this->bodegaService->crear($store, $request->validated());

            return redirect()
                ->route('stores.products.bodegas', $store)
                ->with('success', 'Bodega «'.$item->codigo.' — '.$item->nombre.'» creada.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.products.bodegas', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(StoreBodegaRequest $request, Store $store, Bodega $bodega)
    {
        $this->permissionService->authorize($store, 'products.bodegas.edit');

        if ($bodega->store_id !== $store->id) {
            abort(404);
        }

        try {
            $this->bodegaService->actualizar($store, $bodega, $request->validated());

            return redirect()
                ->route('stores.products.bodegas', $store)
                ->with('success', 'Bodega actualizada.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.products.bodegas', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function updateManejo(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'products.bodegas.edit');

        $request->merge([
            'maneja_bodegas' => $request->boolean('maneja_bodegas'),
        ]);

        $request->validate([
            'maneja_bodegas' => ['required', 'boolean'],
        ]);

        try {
            $this->bodegaService->actualizarManejoBodegas(
                $store,
                (bool) $request->input('maneja_bodegas')
            );

            $msg = $request->boolean('maneja_bodegas')
                ? 'Manejo de bodegas activado.'
                : 'Manejo de bodegas desactivado.';

            return redirect()
                ->route('stores.products.bodegas', $store)
                ->with('success', $msg);
        } catch (Exception $e) {
            return redirect()
                ->route('stores.products.bodegas', $store)
                ->with('error', $e->getMessage());
        }
    }
}
