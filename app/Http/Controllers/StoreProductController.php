<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StorePermissionService;

class StoreProductController extends Controller
{
    /**
     * Shell de productos: catálogo en reconstrucción (flujo incompleto).
     */
    public function index(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.view');

        return view('stores.productos.productos', compact('store'));
    }
}
