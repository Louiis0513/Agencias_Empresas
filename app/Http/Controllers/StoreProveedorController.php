<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProveedorRequest;
use App\Models\Store;
use App\Models\Proveedor;
use App\Services\ProveedorService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;

class StoreProveedorController extends Controller
{
    public function index(Store $store, ProveedorService $proveedorService, Request $request, StorePermissionService $permission)
    {
        $permission->authorize($store, 'proveedores.view');

        $filtros = [
            'search' => $request->get('search'),
        ];

        $proveedores = $proveedorService->listarProveedores($store, $filtros);

        return view('stores.proveedores', compact('store', 'proveedores'));
    }

    public function create(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'proveedores.create');

        return view('stores.proveedores', compact('store'));
    }

    public function store(Store $store, StoreProveedorRequest $request, ProveedorService $proveedorService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'proveedores.create');

        try {
            $data = $request->validated();
            $data['estado'] = $request->boolean('estado', true);
            $proveedorService->crearProveedor($store, $data);

            return redirect()->route('stores.proveedores', $store)
                ->with('success', 'Proveedor creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.proveedores', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function edit(Store $store, Proveedor $proveedor, StorePermissionService $permission)
    {
        $permission->authorize($store, 'proveedores.edit');

        if ($proveedor->store_id !== $store->id) {
            abort(404);
        }

        return view('stores.proveedores', compact('store', 'proveedor'));
    }

    public function update(Store $store, Proveedor $proveedor, StoreProveedorRequest $request, ProveedorService $proveedorService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'proveedores.edit');

        if ($proveedor->store_id !== $store->id) {
            abort(404);
        }

        try {
            $data = $request->validated();
            $data['estado'] = $request->boolean('estado', true);
            $proveedorService->actualizarProveedor($store, $proveedor->id, $data);

            return redirect()->route('stores.proveedores', $store)
                ->with('success', 'Proveedor actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.proveedores', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(Store $store, Proveedor $proveedor, ProveedorService $proveedorService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'proveedores.destroy');

        if ($proveedor->store_id !== $store->id) {
            abort(404);
        }

        try {
            $proveedorService->eliminarProveedor($store, $proveedor->id);
            return redirect()->route('stores.proveedores', $store)
                ->with('success', 'Proveedor eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.proveedores', $store)
                ->with('error', $e->getMessage());
        }
    }
}
