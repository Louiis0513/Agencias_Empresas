<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\CategoriaContableService;
use App\Services\ProductService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;

class StoreProductController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected ProductService $productService,
        protected CategoriaContableService $categoriaContableService,
    ) {}

    /**
     * Listado estilo Siigo: productos y servicios.
     */
    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'products.view');

        $filtros = [
            'search' => $request->get('search'),
            'tipo' => $request->get('tipo'),
            'categoria_contable_id' => $request->get('categoria_contable_id'),
            'estado' => $request->get('estado'),
            'es_inventariable' => $request->get('es_inventariable'),
            'stock' => $request->get('stock'),
        ];

        return view('stores.productos.productos', [
            'store' => $store,
            'products' => $this->productService->listar($store, $filtros, 10),
            'categorias' => $this->categoriaContableService->listarActivasParaProducto($store),
            'filtros' => $filtros,
        ]);
    }
}
