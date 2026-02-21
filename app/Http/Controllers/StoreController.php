<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Category;
use App\Services\CategoryService;
use App\Services\AttributeService;
use App\Services\StorePermissionService;
use App\Services\CotizacionService;
use App\Services\PurchaseService;
use App\Models\Purchase;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class StoreController extends Controller
{
    public function show(Store $store)
    {
        // 1. SEGURIDAD: Verificar si el usuario autenticado pertenece a esta tienda
        // Si no está en la lista de trabajadores, lanzamos un error 403 (Prohibido)
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        // 2. (Opcional) Guardamos en sesión en qué tienda estamos trabajando
        // Esto es útil para no tener que preguntar el ID a cada rato
        session(['current_store_id' => $store->id]);

        // 3. Retornamos la vista del panel de la tienda
        return view('stores.dashboard', compact('store'));
    }


    public function categories(Store $store, CategoryService $categoryService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'categories.view');

        session(['current_store_id' => $store->id]);

        // Obtenemos el árbol de categorías (raíces con hijos)
        $categoryTree = $categoryService->getCategoryTree($store);
        
        // También obtenemos lista plana para dropdowns
        $categoriesFlat = $categoryService->getFlatList($store);

        return view('stores.categorias', compact('store', 'categoryTree', 'categoriesFlat'));
    }

    public function destroyCategory(Store $store, \App\Models\Category $category, CategoryService $categoryService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'categories.destroy');

        try {
            $categoryService->deleteCategory($store, $category->id);
            return redirect()->route('stores.categories', $store)
                ->with('success', 'Categoría eliminada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.categories', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function showCategory(Store $store, Category $category, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'categories.view');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        session(['current_store_id' => $store->id]);

        $category->load(['attributes' => ['options']]);
        $products = $category->products()->orderBy('name')->get();

        return view('stores.category-show', compact('store', 'category', 'products'));
    }

    public function attributeGroups(Store $store, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'attribute-groups.view');

        session(['current_store_id' => $store->id]);

        $groups = $attributeService->getStoreAttributeGroups($store);

        return view('stores.attribute-groups', compact('store', 'groups'));
    }

    public function categoryAttributes(Store $store, Category $category, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'category-attributes.assign');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        session(['current_store_id' => $store->id]);

        $storeAttributeGroups = $attributeService->getStoreAttributeGroups($store);
        $categoryAttributes = $category->attributes()->with(['options', 'groups'])->get();

        return view('stores.category-attributes', compact('store', 'category', 'storeAttributeGroups', 'categoryAttributes'));
    }

    public function assignAttributes(Store $store, Category $category, Request $request, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'category-attributes.assign');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'attribute_group_ids' => 'nullable|array',
            'attribute_group_ids.*' => 'exists:attribute_groups,id',
        ]);

        try {
            $attributeGroupIds = $request->input('attribute_group_ids', []) ?: [];
            // Solo grupos de esta tienda
            $attributeGroupIds = array_values(array_filter(array_map('intval', $attributeGroupIds)));
            $validGroupIds = \App\Models\AttributeGroup::where('store_id', $store->id)
                ->whereIn('id', $attributeGroupIds)
                ->pluck('id')
                ->all();
            $attributeGroupIds = array_values(array_intersect($attributeGroupIds, $validGroupIds));

            $attributeService->assignGroupsToCategory($category, $attributeGroupIds);

            return redirect()->route('stores.category.attributes', [$store, $category])
                ->with('success', 'Grupos de atributos asignados correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.category.attributes', [$store, $category])
                ->with('error', $e->getMessage());
        }
    }

    public function destroyAttributeGroup(Store $store, \App\Models\AttributeGroup $attributeGroup, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'attribute-groups.destroy');
        if ($attributeGroup->store_id !== $store->id) {
            abort(404);
        }

        try {
            $attributeService->deleteAttributeGroup($store, $attributeGroup->id);
            return redirect()->route('stores.attribute-groups', $store)->with('success', 'Grupo eliminado.');
        } catch (\Exception $e) {
            return redirect()->route('stores.attribute-groups', $store)->with('error', $e->getMessage());
        }
    }

    // ==================== VENTAS ====================

    public function carrito(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'ventas.carrito.view');

        session(['current_store_id' => $store->id]);

        return view('stores.ventas.carrito', compact('store'));
    }

    public function cotizaciones(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'cotizaciones.view');

        session(['current_store_id' => $store->id]);

        $cotizaciones = \App\Models\Cotizacion::deTienda($store->id)
            ->with(['user', 'customer', 'items'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('stores.ventas.cotizaciones', compact('store', 'cotizaciones'));
    }

    public function showCotizacion(Store $store, \App\Models\Cotizacion $cotizacion, CotizacionService $cotizacionService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'cotizaciones.view');

        if ($cotizacion->store_id !== $store->id) {
            abort(404);
        }

        $cotizacion->load(['user', 'customer', 'items.product']);
        $itemsConPrecios = $cotizacionService->obtenerItemsConPrecios($store, $cotizacion);

        return view('stores.ventas.cotizacion-detalle', compact('store', 'cotizacion', 'itemsConPrecios'));
    }

    public function destroyCotizacion(Store $store, \App\Models\Cotizacion $cotizacion, CotizacionService $cotizacionService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'cotizaciones.view');

        if ($cotizacion->store_id !== $store->id) {
            abort(404);
        }

        $cotizacionService->eliminarCotizacion($cotizacion);

        return redirect()->route('stores.ventas.cotizaciones', $store)
            ->with('success', 'Cotización eliminada correctamente.');
    }

    // ==================== COMPRAS DE PRODUCTOS ====================

    public function productPurchases(Store $store, Request $request, PurchaseService $purchaseService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.view');

        session(['current_store_id' => $store->id]);

        $filtros = [
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'proveedor_id' => $request->get('proveedor_id'),
            'purchase_type' => Purchase::TYPE_PRODUCTO,
            'per_page' => $request->get('per_page', 15),
        ];

        $purchases = $purchaseService->listarCompras($store, $filtros);
        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compras-productos', compact('store', 'purchases', 'proveedores'));
    }

    public function createProductPurchase(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        session(['current_store_id' => $store->id]);

        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compra-productos-crear', compact('store', 'proveedores'));
    }

    public function storeProductPurchase(Store $store, Request $request, PurchaseService $purchaseService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        $data = $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_type' => ['nullable', 'string'],
            'details.*.product_id' => ['nullable'],
            'details.*.activo_id' => ['nullable'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items' => ['nullable', 'array'],
            'details.*.batch_items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.batch_items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items.*.product_variant_id' => ['required', 'integer', 'min:1'],
            'details.*.batch_items.*.expiration_date' => ['nullable', 'date'],
            'details.*.serial_items' => ['nullable', 'array'],
            'details.*.serial_items.*.serial_number' => ['nullable', 'string'],
            'details.*.serial_items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.serial_items.*.features' => ['nullable', 'array'],
            'details.*.serial_items.*.features.*' => ['nullable'],
        ]);

        $data['purchase_type'] = Purchase::TYPE_PRODUCTO;

        try {
            $purchaseService->crearCompra($store, Auth::id(), $data);

            return redirect()->route('stores.product-purchases', $store)
                ->with('success', 'Compra de productos creada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function editProductPurchase(Store $store, Purchase $purchase, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador() || $purchase->purchase_type !== Purchase::TYPE_PRODUCTO) {
            abort(404);
        }

        $purchase->load([
            'details.product',
            'details.product.category.attributes',
            'details.product.variants',
            'proveedor',
        ]);

        $detailsForEdit = $purchase->details->map(function ($d) {
            $product = $d->product;
            $productType = $product ? ($product->type ?? 'simple') : 'simple';
            $description = $d->description ?? '';

            if ($product && $product->isSerialized() && ! empty($d->serial_items) && is_array($d->serial_items)) {
                $attrNames = [];
                if ($product->category) {
                    $category = $product->category->relationLoaded('attributes') ? $product->category : $product->category->load('attributes');
                    $attrNames = $category->attributes->pluck('name', 'id')->all();
                }
                $parts = [];
                foreach ($d->serial_items as $idx => $row) {
                    $row = is_array($row) ? $row : (array) $row;
                    $sn = trim($row['serial_number'] ?? '');
                    $feats = $row['features'] ?? [];
                    $featParts = [];
                    if (is_array($feats)) {
                        foreach ($feats as $attrId => $val) {
                            if ((string) $val !== '') {
                                $name = $attrNames[(int) $attrId] ?? $attrNames[(string) $attrId] ?? "Atributo {$attrId}";
                                $featParts[] = "{$name}: {$val}";
                            }
                        }
                    }
                    $unitLabel = $sn !== '' ? "Serial: {$sn}" : 'Unidad ' . ($idx + 1);
                    if (! empty($featParts)) {
                        $unitLabel .= ' (' . implode(', ', $featParts) . ')';
                    }
                    $parts[] = $unitLabel;
                }
                $description = $product->name . (empty($parts) ? '' : ' — ' . implode('; ', $parts));
            } elseif ($product && $product->isBatch() && ! empty($d->batch_items) && is_array($d->batch_items)) {
                $bi = $d->batch_items[0] ?? null;
                $variantId = $bi && isset($bi['product_variant_id']) ? (int) $bi['product_variant_id'] : 0;
                if ($variantId > 0) {
                    $variant = \App\Models\ProductVariant::where('id', $variantId)->where('product_id', $product->id)->first();
                    if ($variant) {
                        $description = $product->name . ' — ' . $variant->display_name;
                    }
                }
            }

            return [
                'item_type' => $d->item_type,
                'product_id' => $d->product_id,
                'activo_id' => $d->activo_id,
                'description' => $description,
                'quantity' => $d->quantity,
                'unit_cost' => (float) $d->unit_cost,
                'subtotal' => (float) $d->subtotal,
                'serial_items' => $d->serial_items ?? [],
                'batch_items' => $d->batch_items ?? [],
                'product_type' => $productType,
            ];
        })->values()->all();

        session(['current_store_id' => $store->id]);
        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compra-productos-crear', compact('store', 'purchase', 'proveedores', 'detailsForEdit'));
    }

    public function updateProductPurchase(Store $store, Purchase $purchase, Request $request, PurchaseService $purchaseService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador() || $purchase->purchase_type !== Purchase::TYPE_PRODUCTO) {
            abort(404);
        }

        $data = $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_type' => ['nullable', 'string'],
            'details.*.product_id' => ['nullable'],
            'details.*.activo_id' => ['nullable'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items' => ['nullable', 'array'],
            'details.*.batch_items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.batch_items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items.*.product_variant_id' => ['required', 'integer', 'min:1'],
            'details.*.batch_items.*.expiration_date' => ['nullable', 'date'],
            'details.*.serial_items' => ['nullable', 'array'],
            'details.*.serial_items.*.serial_number' => ['nullable', 'string'],
            'details.*.serial_items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.serial_items.*.features' => ['nullable', 'array'],
            'details.*.serial_items.*.features.*' => ['nullable'],
        ]);

        $data['purchase_type'] = Purchase::TYPE_PRODUCTO;

        try {
            $purchaseService->actualizarCompra($store, $purchase->id, $data);

            return redirect()->route('stores.purchases.show', [$store, $purchase])
                ->with('success', 'Compra actualizada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ==================== PROVEEDORES ====================

    // ==================== CAJA (suma de bolsillos) Y BOLSILLOS ====================








    // ==================== COMPROBANTES DE INGRESO ====================


    // ==================== COMPROBANTES DE EGRESO ====================

}