<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Bodega;
use App\Models\CentroCosto;
use App\Models\Impuesto;
use App\Models\ListaPrecio;
use App\Models\Product;
use App\Models\Store;
use App\Models\UnidadMedidaFe;
use App\Services\CategoriaContableService;
use App\Services\ListaPrecioService;
use App\Services\ProductService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class StoreProductController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected ProductService $productService,
        protected CategoriaContableService $categoriaContableService,
        protected ListaPrecioService $listaPrecioService,
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

    /**
     * Formulario de creación estilo Siigo.
     */
    public function create(Store $store)
    {
        $this->permissionService->authorize($store, 'products.create');

        return view('stores.productos.crear', $this->formCatalogos($store));
    }

    public function store(StoreProductRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'products.create');

        try {
            $product = $this->productService->crear(
                $store,
                $request->validated(),
                $this->imagenesValidas($request)
            );
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->boolean('guardar_y_nuevo')) {
            return redirect()
                ->route('stores.products.create', $store)
                ->with('success', 'Se creó «'.$product->nombre.'». Puedes crear otro.');
        }

        return redirect()
            ->route('stores.products', $store)
            ->with('success', 'Se creó «'.$product->nombre.'» ('.$product->codigo.').');
    }

    /**
     * Ficha de detalle de producto o servicio.
     */
    public function show(Store $store, Product $product)
    {
        $this->permissionService->authorize($store, 'products.view');
        $this->assertStoreProduct($store, $product);

        $product = $this->productService->obtenerParaDetalle($store, $product->id);

        $listasActivas = ListaPrecio::query()
            ->deStore($store)
            ->activas()
            ->orderBy('numero')
            ->get(['id', 'numero', 'nombre']);

        return view('stores.productos.detalle', compact('store', 'product', 'listasActivas'));
    }

    public function edit(Store $store, Product $product)
    {
        $this->permissionService->authorize($store, 'products.edit');
        $this->assertStoreProduct($store, $product);

        $product->load(['precios', 'images']);

        return view('stores.productos.editar', array_merge(
            $this->formCatalogos($store),
            ['product' => $product]
        ));
    }

    public function update(StoreProductRequest $request, Store $store, Product $product)
    {
        $this->permissionService->authorize($store, 'products.edit');
        $this->assertStoreProduct($store, $product);

        try {
            $product = $this->productService->actualizar(
                $store,
                $product,
                $request->validated(),
                $this->imagenesValidas($request)
            );
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('stores.products.show', [$store, $product])
            ->with('success', 'Se actualizó «'.$product->nombre.'».');
    }

    public function toggle(Store $store, Product $product)
    {
        $this->permissionService->authorize($store, 'products.edit');
        $this->assertStoreProduct($store, $product);

        try {
            $nuevo = ! $product->is_active;
            $product = $this->productService->cambiarEstado($store, $product, $nuevo);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $product->is_active
            ? 'Se activó «'.$product->nombre.'».'
            : 'Se inactivó «'.$product->nombre.'».';

        return back()->with('success', $msg);
    }

    public function destroy(Store $store, Product $product)
    {
        $this->permissionService->authorize($store, 'products.destroy');
        $this->assertStoreProduct($store, $product);

        $nombre = $product->nombre;

        try {
            $this->productService->eliminar($store, $product);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('stores.products', $store)
            ->with('success', 'Se eliminó «'.$nombre.'». No se puede recuperar.');
    }

    private function assertStoreProduct(Store $store, Product $product): void
    {
        if ($product->store_id !== $store->id) {
            abort(404);
        }
    }

    /**
     * Formulario shell: saldos iniciales de inventario (solo UI, sin persistencia).
     */
    public function createSaldosIniciales(Store $store)
    {
        $this->permissionService->authorize($store, 'products.view');

        $productosInventariables = Product::query()
            ->where('store_id', $store->id)
            ->where('es_inventariable', true)
            ->activos()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $bodegas = $store->maneja_bodegas
            ? Bodega::query()
                ->deStore($store)
                ->activos()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
            : collect();

        $centrosCosto = CentroCosto::query()
            ->deStore($store)
            ->subcentros()
            ->activos()
            ->with('padre:id,codigo,nombre')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'parent_id']);

        return view('stores.productos.documentos.saldos-iniciales-crear', [
            'store' => $store,
            'productosInventariables' => $productosInventariables,
            'bodegas' => $bodegas,
            'centrosCosto' => $centrosCosto,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formCatalogos(Store $store): array
    {
        $this->categoriaContableService->asegurarCategoriasPorDefecto($store);
        $this->listaPrecioService->asegurarListasPorDefecto($store);

        $categorias = $this->categoriaContableService->listarActivasParaProducto($store);
        $categoriaProductoDefault = $categorias->firstWhere('tipo', Product::TIPO_PRODUCTO)?->id;
        $categoriaServicioDefault = $categorias->firstWhere('tipo', Product::TIPO_SERVICIO)?->id;

        $impuestosBase = Impuesto::query()
            ->deStore($store)
            ->enUso()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'tipo', 'tarifa', 'por_valor']);

        $impuestosCargo = $impuestosBase->whereIn('tipo', [
            Impuesto::TIPO_IVA,
            Impuesto::TIPO_IMPOCONSUMO,
        ])->values();

        $impuestosRetencion = $impuestosBase->where('tipo', Impuesto::TIPO_RETEFUENTE)->values();

        $listasActivas = ListaPrecio::query()
            ->deStore($store)
            ->activas()
            ->orderBy('numero')
            ->get(['id', 'numero', 'nombre']);

        $unidadesDian = UnidadMedidaFe::query()
            ->ordenadas()
            ->get(['codigo', 'nombre']);

        return [
            'store' => $store,
            'categorias' => $categorias,
            'categoriaProductoDefault' => $categoriaProductoDefault,
            'categoriaServicioDefault' => $categoriaServicioDefault,
            'impuestosCargo' => $impuestosCargo,
            'impuestosRetencion' => $impuestosRetencion,
            'listasActivas' => $listasActivas,
            'unidadesDian' => $unidadesDian,
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    private function imagenesValidas(Request $request): array
    {
        return array_values(array_filter(
            is_array($request->file('imagenes')) ? $request->file('imagenes') : [],
            fn ($f) => $f instanceof UploadedFile && $f->isValid()
        ));
    }
}
