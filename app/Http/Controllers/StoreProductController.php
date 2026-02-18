<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\Store;
use App\Services\ProductService;
use App\Services\StorePermissionService;
use Illuminate\Support\Facades\Auth;

class StoreProductController extends Controller
{
    public function index(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.view');

        $products = $store->products()
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('stores.productos', compact('store', 'products'));
    }

    public function show(Store $store, Product $product, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.view');

        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load(['category.attributes.options', 'productItems', 'batches.batchItems', 'variants.batchItems.batch', 'allowedVariantOptions', 'attributeValues.attribute']);

        return view('stores.producto-detalle', compact('store', 'product'));
    }

    public function updateProductVariantOptions(Store $store, Product $product, StoreProductRequest $request, StorePermissionService $permission)
    {
        $permission->authorize($store, 'products.edit');

        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load('category.attributes');
        $categoryAttributeIds = $product->category?->attributes?->pluck('id') ?? collect();

        $request->validate([
            'attribute_option_ids' => ['nullable', 'array'],
            'attribute_option_ids.*' => [
                'integer',
                'exists:attribute_options,id',
                function ($attribute, $value, $fail) use ($categoryAttributeIds) {
                    $opt = \App\Models\AttributeOption::find($value);
                    if (! $opt || ! $categoryAttributeIds->contains($opt->attribute_id)) {
                        $fail('La opción no pertenece a un atributo de la categoría del producto.');
                    }
                },
            ],
        ]);

        $ids = array_values(array_unique(array_map('intval', $request->input('attribute_option_ids', []))));
        $product->allowedVariantOptions()->sync($ids);

        return redirect()->route('stores.products.show', [$store, $product])
            ->with('success', 'Variantes permitidas actualizadas. En compras solo se podrán elegir estas opciones.');
    }

    public function updateVariant(Store $store, Product $product, StoreProductRequest $request, ProductService $productService, StorePermissionService $permission)
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

        $product->load('category.attributes');
        $category = $product->category;
        if (! $category || $category->attributes->isEmpty()) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', 'El producto debe tener una categoría con atributos.');
        }

        $rawNew = $request->input('attribute_values', []);
        $newFeatures = [];
        foreach ($category->attributes as $attr) {
            $v = $rawNew[$attr->id] ?? ($attr->type === 'boolean' ? '0' : null);
            if ($v === null || $v === '' || $v === '0') {
                continue;
            }
            $newFeatures[$attr->id] = $v;
        }

        $data = [
            'features' => $newFeatures,
            'is_active' => $request->boolean('is_active'),
        ];

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
            $v = $rawAttributeValues[$attr->id] ?? ($attr->type === 'boolean' ? '0' : null);
            if ($v === null && $attr->type !== 'boolean') {
                continue;
            }
            $attributeValues[$attr->id] = $v ?? '0';
        }

        $variant = [
            'attribute_values' => $attributeValues,
            'price' => $request->input('price') !== '' && $request->input('price') !== null ? (float) $request->input('price') : null,
            'has_stock' => $request->boolean('has_stock'),
            'stock_initial' => $request->input('stock_initial'),
            'cost' => $request->input('cost'),
            'batch_number' => $request->input('batch_number'),
            'expiration_date' => $request->input('expiration_date') ?: null,
        ];

        $features = array_filter($attributeValues, fn ($v) => $v !== '' && $v !== null && $v !== '0');
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
}
