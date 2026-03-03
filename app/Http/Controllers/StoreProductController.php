<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\ConvertidorImgService;
use App\Services\ProductService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StoreProductController extends Controller
{
    public function index(Store $store, Request $request, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.view');

        $query = $store->products()->with('category');

        // Filtro texto (nombre, sku, barcode, serial en variantes y product_items)
        if ($search = trim((string) $request->get('search'))) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $term)->orWhere('barcode', 'like', $term))
                    ->orWhereHas('productItems', fn ($pi) => $pi->where('serial_number', 'like', $term));
            });
        }

        // Filtro categoría
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->paginate(10)->withQueryString();
        $categories = Category::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.productos', compact('store', 'products', 'categories'));
    }

    public function show(Store $store, Product $product, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.view');

        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load(['category.attributes', 'productItems', 'batches.batchItems', 'variants.batchItems.batch', 'attributeValues.attribute']);

        return view('stores.producto-detalle', compact('store', 'product'));
    }

    public function updateVariant(Store $store, Product $product, StoreProductRequest $request, ProductService $productService, StorePermissionService $permission, ConvertidorImgService $convertidorImg)
    {
        $permission->authorize($store, 'products.edit');

        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $productVariantId = (int) $request->input('product_variant_id');
        if (! $productVariantId) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', 'No se especificó la variante a actualizar.');
        }

        $product->load('category.attributes', 'variants');
        $category = $product->category;
        if (! $category || $category->attributes->isEmpty()) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', 'El producto debe tener una categoría con atributos.');
        }

        $rawNew = $request->input('attribute_values', []);
        $newFeatures = [];
        foreach ($category->attributes as $attr) {
            $v = $rawNew[$attr->id] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $newFeatures[$attr->id] = $v;
        }

        $data = [
            'features' => $newFeatures,
            'is_active' => $request->boolean('is_active'),
        ];

        // Selector de vitrina para la variante
        $data['in_showcase'] = $request->boolean('in_showcase');

        $price = $request->input('price');
        if ($price !== null && $price !== '') {
            $data['price'] = (float) $price;
        }

        $costRef = $request->input('cost_reference');
        if ($costRef !== null && $costRef !== '') {
            $data['cost_reference'] = (float) $costRef;
        }

        if ($request->has('barcode')) {
            $data['barcode'] = $request->input('barcode') ?: null;
        }

        if ($request->has('sku')) {
            $data['sku'] = $request->input('sku') ?: null;
        }

        $variant = $product->variants->firstWhere('id', $productVariantId);
        if (! $variant) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', 'No se encontró la variante a actualizar.');
        }

        if ($request->boolean('remove_variant_image')) {
            if ($variant->image_path) {
                Storage::disk('public')->delete($variant->image_path);
            }
            $data['image_path'] = null;
        }

        if ($request->hasFile('variant_image')) {
            if ($variant->image_path) {
                Storage::disk('public')->delete($variant->image_path);
            }

            $relativeDir = "products/{$store->id}/{$product->id}/variants/{$productVariantId}";
            $uploadedFile = $request->file('variant_image');
            $originalRelativePath = $uploadedFile->store($relativeDir, 'public');

            try {
                $webpPath = $convertidorImg->convertPublicImageToWebp($originalRelativePath);
                $data['image_path'] = $webpPath;
            } catch (\Throwable $e) {
                Log::error('Error al convertir imagen de variante a WebP', [
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'variant_id' => $productVariantId,
                    'exception' => $e,
                ]);

                return redirect()->route('stores.products.show', [$store, $product])
                    ->with('error', 'La imagen se subió pero ocurrió un error al convertirla a WebP. Inténtalo de nuevo más tarde.');
            }
        }

        try {
            $productService->updateVariant($store, $product, $productVariantId, $data);
        } catch (\Exception $e) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('stores.products.show', [$store, $product])
            ->with('success', 'Variante actualizada correctamente.');
    }

    public function storeVariants(Store $store, Product $product, StoreProductRequest $request, ProductService $productService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.edit');

        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load('category.attributes');
        $category = $product->category;
        if (! $category || $category->attributes->isEmpty()) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', 'El producto debe tener una categoría con atributos.');
        }

        $rawAttributeValues = $request->input('attribute_values', []);
        $attributeValues = [];
        foreach ($category->attributes as $attr) {
            $v = $rawAttributeValues[$attr->id] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $attributeValues[$attr->id] = $v;
        }

        $variant = [
            'attribute_values' => $attributeValues,
            'price' => $request->input('price') !== '' && $request->input('price') !== null ? (float) $request->input('price') : null,
            'sku' => $request->input('sku'),
            'barcode' => $request->input('barcode'),
            'has_stock' => $request->boolean('has_stock'),
            'stock_initial' => $request->input('stock_initial'),
            'cost' => $request->input('cost'),
            'batch_number' => $request->input('batch_number'),
            'expiration_date' => $request->input('expiration_date') ?: null,
        ];

        $features = array_filter($attributeValues, fn ($v) => $v !== '' && $v !== null);
        if ($productService->variantExists($store, $product, $features)) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', 'Esa variante ya existe. Elige una combinación de atributos distinta.');
        }

        try {
            $productService->addVariantsToProduct($store, $product, [$variant], Auth::id());
        } catch (\Exception $e) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('stores.products.show', [$store, $product])
            ->with('success', 'Variante creada correctamente.');
    }

    public function destroy(Store $store, Product $product, ProductService $productService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.destroy');

        if ($product->store_id !== $store->id) {
            abort(404);
        }

        try {
            $productService->deleteProduct($store, $product->id);
            return redirect()->route('stores.products', $store)
                ->with('success', 'Producto eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.products', $store)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Devuelve en JSON los atributos de la categoría del producto (para compra de productos, seriales y variantes).
     */
    public function atributosCategoria(Store $store, int $productId, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.view');

        if ($productId <= 0) {
            return response()->json(['attributes' => []]);
        }

        $product = Product::where('store_id', $store->id)->find($productId);
        if (! $product) {
            return response()->json(['attributes' => []]);
        }

        $product->load('category.attributes');
        $category = $product->category;
        $attributes = $category ? $category->attributes->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
        ])->values()->all() : [];

        return response()->json(['attributes' => $attributes]);
    }
}
