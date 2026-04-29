<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\InventoryExcelExportService;
use App\Services\InventoryMovementsExcelExportService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreInventoryController extends Controller
{
    public function exportExcel(Store $store, InventoryExcelExportService $exportService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }

        $permission->authorize($store, 'inventario.view');

        session(['current_store_id' => $store->id]);

        return $exportService->download($store);
    }

    public function exportMovementsExcel(Request $request, Store $store, InventoryMovementsExcelExportService $exportService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }

        $permission->authorize($store, 'inventario.view');

        session(['current_store_id' => $store->id]);

        $filtros = [
            'product_id' => $request->get('product_id'),
            'product_variant_id' => $request->get('product_variant_id'),
            'product_item_id' => $request->get('product_item_id'),
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'search' => $request->get('search'),
        ];

        return $exportService->download($store, $filtros);
    }
}
