<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\ActivoService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreActivoController extends Controller
{
    public function index(Store $store, Request $request, ActivoService $activoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }

        $permission->authorize($store, 'activos.view');

        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'control_type' => $request->get('control_type'),
        ];

        $activos = $activoService->listarActivos($store, $filtros);

        return view('stores.activos', compact('store', 'activos'));
    }

    public function movimientos(Store $store, Request $request, ActivoService $activoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }

        $permission->authorize($store, 'activos.view');

        $filtros = [
            'activo_id' => $request->get('activo_id'),
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];

        $activosParaSelect = $activoService->activosParaMovimientos($store);
        $movimientos = $activoService->listarMovimientos($store, $filtros);

        return view('stores.activo-movimientos', compact('store', 'movimientos', 'activosParaSelect'));
    }
}
