<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function show(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'dashboard.view');

        session(['current_store_id' => $store->id]);

        return view('stores.dashboard', compact('store'));
    }

    /**
     * Informes: solo facturación activa; tab productos retirado.
     */
    public function reportsIndex(Request $request, Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $permission->authorize($store, 'reports.billing.view');

        session(['current_store_id' => $store->id]);

        $tab = 'facturacion';
        $topMasVendidos = collect();
        $topMayorMargen = collect();
        $ventasRange = '7d';

        return view('stores.informes.index', compact('store', 'tab', 'topMasVendidos', 'topMayorMargen', 'ventasRange'));
    }

    /**
     * Shell de ventas (flujo incompleto).
     */
    public function carrito(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'ventas.carrito.view');

        session(['current_store_id' => $store->id]);

        return view('stores.ventas.carrito', compact('store'));
    }
}
