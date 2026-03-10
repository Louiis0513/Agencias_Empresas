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
            'product_variant_id' => $request->get('product_variant_id'),
            'product_item_id' => $request->get('product_item_id'),
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'search' => $request->get('search'),
            'per_page' => 10,
        ];

        $movimientos = $inventarioService->listarMovimientos($store, $filtros);

        $productoSeleccionado = null;
        $productoSeleccionadoDisplay = '';
        if (! empty($filtros['product_id'])) {
            $productoSeleccionado = \App\Models\Product::where('store_id', $store->id)
                ->where('id', $filtros['product_id'])
                ->first(['id', 'name', 'sku', 'type']);
            if ($productoSeleccionado) {
                $productoSeleccionadoDisplay = $productoSeleccionado->name;
                if (! empty($filtros['product_variant_id'])) {
                    $variant = \App\Models\ProductVariant::where('id', $filtros['product_variant_id'])
                        ->where('product_id', $productoSeleccionado->id)
                        ->first();
                    if ($variant && $variant->display_name !== '—') {
                        $productoSeleccionadoDisplay .= ' (' . $variant->display_name . ')';
                    }
                } elseif (! empty($filtros['product_item_id'])) {
                    $item = \App\Models\ProductItem::where('id', $filtros['product_item_id'])
                        ->where('product_id', $productoSeleccionado->id)
                        ->first();
                    if ($item) {
                        $serial = $item->serial_number ?? '';
                        $productoSeleccionadoDisplay .= $serial !== '' ? ' (Serial: ' . $serial . ')' : '';
                    }
                }
            }
        }

        return view('stores.productos.inventario', compact('store', 'movimientos', 'productoSeleccionado', 'productoSeleccionadoDisplay'));
    }
}
