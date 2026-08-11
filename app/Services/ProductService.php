<?php

namespace App\Services;

use App\Models\CategoriaContable;
use App\Models\CotizacionItem;
use App\Models\Impuesto;
use App\Models\ListaPrecio;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrecio;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        protected ListaPrecioService $listaPrecioService,
        protected ConvertidorImgService $convertidorImgService,
        protected InventarioService $inventarioService,
    ) {}

    /**
     * Listado de productos/servicios con búsqueda y filtros (estilo Siigo).
     *
     * Filtros soportados:
     * - search: nombre, codigo, codigo_barras
     * - tipo: producto|servicio
     * - categoria_contable_id
     * - estado / is_active: 1|0|activo|inactivo
     * - es_inventariable: 1|0
     * - stock: con_saldos|sin_saldos|bajo_minimo|negativos
     *
     * @param  array{
     *     search?: ?string,
     *     tipo?: ?string,
     *     categoria_contable_id?: int|string|null,
     *     estado?: int|string|null,
     *     is_active?: int|string|null,
     *     es_inventariable?: int|string|null,
     *     stock?: ?string
     * }  $filtros
     */
    public function listar(Store $store, array $filtros = [], int $perPage = 10): LengthAwarePaginator
    {
        $q = Product::query()
            ->deStore($store)
            ->with([
                'impuestoCargo:id,nombre,tarifa,tipo',
                'precios' => fn ($pq) => $pq->with(['listaPrecio:id,numero,nombre,activo,store_id']),
            ])
            ->orderBy('nombre');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('nombre', 'like', '%'.$search.'%')
                    ->orWhere('codigo', 'like', '%'.$search.'%')
                    ->orWhere('codigo_barras', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filtros['tipo']) && in_array($filtros['tipo'], Product::TIPOS, true)) {
            $q->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['categoria_contable_id'])) {
            $q->where('categoria_contable_id', (int) $filtros['categoria_contable_id']);
        }

        $estado = $filtros['estado'] ?? $filtros['is_active'] ?? null;
        if ($estado !== null && $estado !== '') {
            $activo = match (true) {
                in_array((string) $estado, ['1', 'activo', 'activos', 'true'], true) => true,
                in_array((string) $estado, ['0', 'inactivo', 'inactivos', 'false'], true) => false,
                default => filter_var($estado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            };
            if ($activo !== null) {
                $q->where('is_active', $activo);
            }
        }

        if (isset($filtros['es_inventariable']) && $filtros['es_inventariable'] !== '' && $filtros['es_inventariable'] !== null) {
            $q->where(
                'es_inventariable',
                filter_var($filtros['es_inventariable'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        $stockFiltro = trim((string) ($filtros['stock'] ?? ''));
        if ($stockFiltro !== '') {
            $this->aplicarFiltroStock($q, $store, $stockFiltro);
        }

        $paginator = $q->paginate($perPage)->withQueryString();

        $ids = $paginator->getCollection()
            ->filter(fn (Product $p) => $p->es_inventariable)
            ->pluck('id')
            ->all();
        $stocks = $this->inventarioService->stockTotalPorProductos($store, $ids);

        return $paginator->through(function (Product $product) use ($stocks) {
            $product->setAttribute(
                'stock_actual',
                $product->es_inventariable ? ($stocks[$product->id] ?? 0.0) : null
            );

            return $product;
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Product>  $q
     */
    protected function aplicarFiltroStock($q, Store $store, string $filtro): void
    {
        $stockSql = '(SELECT COALESCE(SUM(CASE WHEN direccion = ? THEN cantidad ELSE -cantidad END), 0)
            FROM movimientos_inventario
            WHERE movimientos_inventario.product_id = products.id
              AND movimientos_inventario.store_id = ?)';

        $bindings = [MovimientoInventario::DIRECCION_ENTRADA, $store->id];

        $q->where('es_inventariable', true);

        match ($filtro) {
            'con_saldos' => $q->whereRaw($stockSql.' <> 0', $bindings),
            'sin_saldos' => $q->whereRaw($stockSql.' = 0', $bindings),
            'bajo_minimo' => $q->whereNotNull('stock_minimo')
                ->whereRaw($stockSql.' < products.stock_minimo', $bindings),
            'negativos' => $q->whereRaw($stockSql.' < 0', $bindings),
            default => null,
        };
    }

    /**
     * Obtiene un producto/servicio de la tienda con relaciones para la ficha de detalle.
     */
    public function obtenerParaDetalle(Store $store, int $productId): Product
    {
        return Product::query()
            ->deStore($store)
            ->with([
                'categoriaContable:id,nombre,tipo',
                'impuestoCargo:id,nombre,tarifa,tipo,por_valor',
                'impuestoRetencion:id,nombre,tarifa,tipo',
                'unidadMedidaFe:codigo,nombre',
                'images',
                'precios' => fn ($pq) => $pq->with(['listaPrecio:id,numero,nombre,activo,store_id']),
            ])
            ->findOrFail($productId);
    }

    /**
     * Crea producto o servicio estilo Siigo + precios de listas activas + imágenes opcionales.
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>|null  $imagenes
     */
    public function crear(Store $store, array $data, ?array $imagenes = null): Product
    {
        $tipo = (string) ($data['tipo'] ?? Product::TIPO_PRODUCTO);
        if (! in_array($tipo, Product::TIPOS, true)) {
            throw new Exception('Tipo de ítem no válido.');
        }

        $categoriaId = (int) ($data['categoria_contable_id'] ?? 0);
        $categoria = CategoriaContable::query()
            ->deStore($store)
            ->activas()
            ->whereKey($categoriaId)
            ->first();

        if (! $categoria) {
            throw new Exception('La categoría contable no existe o no está activa.');
        }

        if ($categoria->tipo !== $tipo) {
            throw new Exception('La categoría no corresponde al tipo seleccionado (producto/servicio).');
        }

        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($codigo === '' || mb_strlen($codigo) > 30) {
            throw new Exception('El código es obligatorio y admite máximo 30 caracteres.');
        }

        $existe = Product::query()
            ->deStore($store)
            ->where('codigo', $codigo)
            ->exists();
        if ($existe) {
            throw new Exception('Ya existe un producto o servicio con ese código.');
        }

        $esServicio = $tipo === Product::TIPO_SERVICIO;

        $impuestoCargoId = isset($data['impuesto_cargo_id']) ? (int) $data['impuesto_cargo_id'] : null;
        $impuestoCargo = $impuestoCargoId
            ? Impuesto::query()->deStore($store)->whereKey($impuestoCargoId)->first()
            : null;
        $cargoPorValor = $impuestoCargo?->por_valor === true;

        return DB::transaction(function () use ($store, $data, $imagenes, $tipo, $categoria, $codigo, $esServicio, $impuestoCargoId, $cargoPorValor) {
            $product = Product::create([
                'store_id' => $store->id,
                'categoria_contable_id' => $categoria->id,
                'tipo' => $tipo,
                'codigo' => $codigo,
                'nombre' => trim((string) $data['nombre']),
                'codigo_barras' => $esServicio ? null : ($data['codigo_barras'] ?? null),
                'unidad_medida_dian' => (string) ($data['unidad_medida_dian'] ?? Product::UNIDAD_MEDIDA_DIAN_DEFAULT),
                'es_inventariable' => $esServicio ? false : (bool) ($data['es_inventariable'] ?? true),
                'visible_en_ventas' => (bool) ($data['visible_en_ventas'] ?? true),
                'impuesto_cargo_id' => $impuestoCargoId ?: null,
                'impuesto_retencion_id' => $data['impuesto_retencion_id'] ?? null,
                'valor_impuesto_cargo' => $cargoPorValor ? ($data['valor_impuesto_cargo'] ?? null) : null,
                'aplica_impuesto_bolsas' => $cargoPorValor ? (bool) ($data['aplica_impuesto_bolsas'] ?? false) : false,
                'referencia' => $data['referencia'] ?? null,
                'unidad_medida_factura' => (string) ($data['unidad_medida_factura'] ?? 'unidad'),
                'stock_minimo' => $esServicio ? null : ($data['stock_minimo'] ?? null),
                'descripcion' => $data['descripcion'] ?? null,
                'marca' => $esServicio ? null : ($data['marca'] ?? null),
                'modelo' => $esServicio ? null : ($data['modelo'] ?? null),
                'codigo_arancelario' => $esServicio ? null : ($data['codigo_arancelario'] ?? null),
                'precio_incluye_iva' => (bool) ($data['precio_incluye_iva'] ?? false),
                'is_active' => true,
            ]);

            $this->sincronizarPrecios($store, $product, $data['precios'] ?? []);

            if (! empty($imagenes)) {
                $this->guardarImagenes($store, $product, $imagenes);
            }

            return $product->fresh(['precios', 'images', 'impuestoCargo']);
        });
    }

    /**
     * Actualiza producto/servicio + precios + imágenes nuevas (append hasta el máximo).
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>|null  $imagenes
     */
    public function actualizar(Store $store, Product $product, array $data, ?array $imagenes = null): Product
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        $tipo = (string) ($data['tipo'] ?? $product->tipo);
        if (! in_array($tipo, Product::TIPOS, true)) {
            throw new Exception('Tipo de ítem no válido.');
        }

        $categoriaId = (int) ($data['categoria_contable_id'] ?? 0);
        $categoria = CategoriaContable::query()
            ->deStore($store)
            ->activas()
            ->whereKey($categoriaId)
            ->first();

        if (! $categoria) {
            throw new Exception('La categoría contable no existe o no está activa.');
        }

        if ($categoria->tipo !== $tipo) {
            throw new Exception('La categoría no corresponde al tipo seleccionado (producto/servicio).');
        }

        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($codigo === '' || mb_strlen($codigo) > 30) {
            throw new Exception('El código es obligatorio y admite máximo 30 caracteres.');
        }

        $existe = Product::query()
            ->deStore($store)
            ->where('codigo', $codigo)
            ->where('id', '!=', $product->id)
            ->exists();
        if ($existe) {
            throw new Exception('Ya existe un producto o servicio con ese código.');
        }

        $esServicio = $tipo === Product::TIPO_SERVICIO;

        $impuestoCargoId = isset($data['impuesto_cargo_id']) ? (int) $data['impuesto_cargo_id'] : null;
        $impuestoCargo = $impuestoCargoId
            ? Impuesto::query()->deStore($store)->whereKey($impuestoCargoId)->first()
            : null;
        $cargoPorValor = $impuestoCargo?->por_valor === true;

        return DB::transaction(function () use ($store, $product, $data, $imagenes, $tipo, $categoria, $codigo, $esServicio, $impuestoCargoId, $cargoPorValor) {
            $product->update([
                'categoria_contable_id' => $categoria->id,
                'tipo' => $tipo,
                'codigo' => $codigo,
                'nombre' => trim((string) $data['nombre']),
                'codigo_barras' => $esServicio ? null : ($data['codigo_barras'] ?? null),
                'unidad_medida_dian' => (string) ($data['unidad_medida_dian'] ?? Product::UNIDAD_MEDIDA_DIAN_DEFAULT),
                'es_inventariable' => $esServicio ? false : (bool) ($data['es_inventariable'] ?? true),
                'visible_en_ventas' => $esServicio ? true : (bool) ($data['visible_en_ventas'] ?? true),
                'impuesto_cargo_id' => $impuestoCargoId ?: null,
                'impuesto_retencion_id' => $data['impuesto_retencion_id'] ?? null,
                'valor_impuesto_cargo' => $cargoPorValor ? ($data['valor_impuesto_cargo'] ?? null) : null,
                'aplica_impuesto_bolsas' => $cargoPorValor ? (bool) ($data['aplica_impuesto_bolsas'] ?? false) : false,
                'referencia' => $data['referencia'] ?? null,
                'unidad_medida_factura' => (string) ($data['unidad_medida_factura'] ?? 'unidad'),
                'stock_minimo' => $esServicio ? null : ($data['stock_minimo'] ?? null),
                'descripcion' => $data['descripcion'] ?? null,
                'marca' => $esServicio ? null : ($data['marca'] ?? null),
                'modelo' => $esServicio ? null : ($data['modelo'] ?? null),
                'codigo_arancelario' => $esServicio ? null : ($data['codigo_arancelario'] ?? null),
                'precio_incluye_iva' => (bool) ($data['precio_incluye_iva'] ?? false),
            ]);

            $this->sincronizarPrecios($store, $product, $data['precios'] ?? []);

            if (! empty($imagenes)) {
                $this->guardarImagenes($store, $product, $imagenes);
            }

            return $product->fresh(['precios', 'images', 'impuestoCargo']);
        });
    }

    public function cambiarEstado(Store $store, Product $product, bool $activo): Product
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        $product->is_active = $activo;
        $product->save();

        return $product;
    }

    /**
     * Copia ficha + precios sin stock ni imágenes. Redirigir a editar tras crear.
     */
    public function duplicar(Store $store, Product $origen): Product
    {
        if ($origen->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        $origen->loadMissing('precios');

        $precios = [];
        foreach ($origen->precios as $pp) {
            $precios[(string) $pp->lista_precio_id] = $pp->precio;
        }

        $data = [
            'tipo' => $origen->tipo,
            'categoria_contable_id' => $origen->categoria_contable_id,
            'codigo' => $this->generarCodigoCopia($store, (string) $origen->codigo),
            'nombre' => 'Copia de '.$origen->nombre,
            'unidad_medida_dian' => $origen->unidad_medida_dian,
            'es_inventariable' => $origen->es_inventariable,
            'visible_en_ventas' => $origen->visible_en_ventas,
            'impuesto_cargo_id' => $origen->impuesto_cargo_id,
            'impuesto_retencion_id' => $origen->impuesto_retencion_id,
            'valor_impuesto_cargo' => $origen->valor_impuesto_cargo,
            'aplica_impuesto_bolsas' => $origen->aplica_impuesto_bolsas,
            'referencia' => $origen->referencia,
            'unidad_medida_factura' => $origen->unidad_medida_factura,
            'stock_minimo' => $origen->stock_minimo,
            'descripcion' => $origen->descripcion,
            'marca' => $origen->marca,
            'modelo' => $origen->modelo,
            'codigo_arancelario' => $origen->codigo_arancelario,
            'precio_incluye_iva' => $origen->precio_incluye_iva,
            'precios' => $precios,
        ];

        return $this->crear($store, $data, null);
    }

    /**
     * Código único: {base}-C, {base}-C2, … (máx. 30 caracteres).
     */
    protected function generarCodigoCopia(Store $store, string $codigoOrigen): string
    {
        $base = mb_substr(trim($codigoOrigen), 0, 27);
        if ($base === '') {
            $base = 'COPIA';
        }

        $candidato = mb_substr($base.'-C', 0, 30);
        $n = 1;
        while (Product::query()->deStore($store)->where('codigo', $candidato)->exists()) {
            $n++;
            $sufijo = '-C'.$n;
            $candidato = mb_substr($base, 0, 30 - mb_strlen($sufijo)).$sufijo;
        }

        return $candidato;
    }

    /**
     * Eliminación definitiva. Bloqueada si hay facturas o cotizaciones asociadas.
     */
    public function eliminar(Store $store, Product $product): void
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        if ($product->invoiceDetails()->exists()) {
            throw new Exception(
                'No se puede eliminar «'.$product->nombre.'» porque está en facturas. Inactívalo en su lugar.'
            );
        }

        if (CotizacionItem::query()->where('product_id', $product->id)->exists()) {
            throw new Exception(
                'No se puede eliminar «'.$product->nombre.'» porque está en cotizaciones. Inactívalo en su lugar.'
            );
        }

        DB::transaction(function () use ($product) {
            $product->loadMissing('images');
            foreach ($product->images as $image) {
                if ($image->path && Storage::disk('public')->exists($image->path)) {
                    Storage::disk('public')->delete($image->path);
                }
            }

            $product->delete();
        });
    }

    /**
     * @param  array<int|string, mixed>  $precios  mapa lista_precio_id => valor
     */
    private function sincronizarPrecios(Store $store, Product $product, array $precios): void
    {
        if ($precios === []) {
            return;
        }

        $listaIds = ListaPrecio::query()
            ->deStore($store)
            ->whereIn('id', array_map('intval', array_keys($precios)))
            ->pluck('id')
            ->all();

        foreach ($precios as $listaId => $valor) {
            $listaId = (int) $listaId;
            if (! in_array($listaId, $listaIds, true)) {
                continue;
            }
            if ($valor === null || $valor === '') {
                continue;
            }

            ProductPrecio::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'lista_precio_id' => $listaId,
                ],
                [
                    'precio' => (float) $valor,
                ]
            );
        }
    }

    /**
     * Agrega una imagen a un producto/servicio existente (máx. 5, máx. 1 MB c/u).
     */
    public function agregarImagen(Store $store, Product $product, UploadedFile $imagen): ProductImage
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }

        $existentes = (int) $product->images()->count();
        if ($existentes >= Product::MAX_IMAGENES) {
            throw new Exception('Ya alcanzaste el máximo de '.Product::MAX_IMAGENES.' imágenes.');
        }

        $maxBytes = Product::MAX_IMAGEN_KB * 1024;
        if (! $imagen->isValid()) {
            throw new Exception('La imagen no es válida.');
        }
        if ($imagen->getSize() > $maxBytes) {
            throw new Exception('Cada imagen debe pesar máximo 1 MB.');
        }

        $mime = (string) $imagen->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            throw new Exception('Las imágenes deben ser PNG o JPG.');
        }

        return DB::transaction(function () use ($store, $product, $imagen, $existentes) {
            $orden = (int) ($product->images()->max('orden') ?? 0) + 1;
            $dir = 'stores/'.$store->id.'/products/'.$product->id;
            $path = $imagen->store($dir, 'public');
            try {
                $path = $this->convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable) {
                // Se conserva el archivo original si falla la conversión.
            }

            return ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'orden' => $orden,
            ]);
        });
    }

    /**
     * Elimina una imagen del producto (archivo + registro).
     */
    public function eliminarImagen(Store $store, Product $product, ProductImage $image): void
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }
        if ($image->product_id !== $product->id) {
            throw new Exception('La imagen no pertenece a este producto.');
        }

        DB::transaction(function () use ($image) {
            if ($image->path && Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
            $image->delete();
        });
    }

    /**
     * Reemplaza el archivo de una imagen existente (conserva el orden).
     */
    public function reemplazarImagen(Store $store, Product $product, ProductImage $image, UploadedFile $nueva): ProductImage
    {
        if ($product->store_id !== $store->id) {
            throw new Exception('El producto no pertenece a esta tienda.');
        }
        if ($image->product_id !== $product->id) {
            throw new Exception('La imagen no pertenece a este producto.');
        }

        $maxBytes = Product::MAX_IMAGEN_KB * 1024;
        if (! $nueva->isValid()) {
            throw new Exception('La imagen no es válida.');
        }
        if ($nueva->getSize() > $maxBytes) {
            throw new Exception('Cada imagen debe pesar máximo 1 MB.');
        }
        $mime = (string) $nueva->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            throw new Exception('Las imágenes deben ser PNG o JPG.');
        }

        return DB::transaction(function () use ($store, $product, $image, $nueva) {
            $oldPath = $image->path;

            $dir = 'stores/'.$store->id.'/products/'.$product->id;
            $path = $nueva->store($dir, 'public');
            try {
                $path = $this->convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable) {
                // Se conserva el archivo original si falla la conversión.
            }

            $image->update(['path' => $path]);

            if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            return $image->fresh();
        });
    }

    /**
     * @param  list<UploadedFile>  $imagenes
     */
    private function guardarImagenes(Store $store, Product $product, array $imagenes): void
    {
        foreach ($imagenes as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            if ((int) $product->images()->count() >= Product::MAX_IMAGENES) {
                break;
            }
            try {
                $this->agregarImagen($store, $product, $file);
            } catch (Exception $e) {
                throw $e;
            }
        }
    }
}
