<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\InventarioService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreInventoryController extends Controller
{
    public function index(Store $store, Request $request, InventarioService $inventarioService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }

        $permission->authorize($store, 'inventario.view');

        session(['current_store_id' => $store->id]);

        $filtros = [
            'product_id' => $request->get('product_id'),
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'per_page' => 15,
        ];

        $productosInventario = $inventarioService->productosConInventario($store);
        $movimientos = $inventarioService->listarMovimientos($store, $filtros);

        return view('stores.inventario', compact('store', 'productosInventario', 'movimientos'));
    }
}
