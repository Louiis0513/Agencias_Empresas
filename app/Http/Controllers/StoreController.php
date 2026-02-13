<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\AttributeService;
use App\Services\ProductService;
use App\Services\CotizacionService;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\ProveedorService;
use App\Services\CajaService;
use App\Services\ActivoService;
use App\Services\InventarioService;
use App\Services\PurchaseService;
use App\Services\AccountPayableService;
use App\Services\StorePermissionService;
use App\Services\WorkerService;
use App\Models\Worker;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StoreProveedorRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

    public function workers(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'workers.view');

        session(['current_store_id' => $store->id]);

        $roles = \App\Models\Role::where('store_id', $store->id)->get()->keyBy('id');

        $owner = $store->owner;
        $workersList = collect();

        if ($owner) {
            $workersList->push([
                'id' => 'owner-' . $owner->id,
                'worker_id' => null,
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'Dueño',
                'role_id' => null,
                'vinculado' => true,
            ]);
        }

        foreach ($store->workerRecords()->with('role')->get() as $w) {
            $workersList->push([
                'id' => $w->id,
                'worker_id' => $w->id,
                'name' => $w->name,
                'email' => $w->email,
                'role' => $w->role->name ?? '-',
                'role_id' => $w->role_id,
                'vinculado' => $w->estaVinculado(),
            ]);
        }

        $rolesList = \App\Models\Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.workers', compact('store', 'workersList', 'rolesList'));
    }

    public function createWorker(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'workers.create');

        session(['current_store_id' => $store->id]);

        $rolesList = \App\Models\Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.worker-create', compact('store', 'rolesList'));
    }

    public function storeWorker(Store $store, Request $request, StorePermissionService $permission, WorkerService $workerService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para añadir trabajadores en esta tienda.');
        }
        $permission->authorize($store, 'workers.create');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'role_id' => ['required', 'exists:roles,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        if (! \App\Models\Role::where('id', $request->role_id)->where('store_id', $store->id)->exists()) {
            return redirect()->back()->withInput()->with('error', 'El rol seleccionado no pertenece a esta tienda.');
        }

        try {
            $workerService->createWorker($store, $request->only(['name', 'email', 'role_id', 'phone', 'document_number', 'address']));
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador añadido correctamente.');
    }

    public function editWorker(Store $store, Worker $worker, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'workers.edit');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        $rolesList = \App\Models\Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.worker-edit', compact('store', 'worker', 'rolesList'));
    }

    public function updateWorker(Store $store, Worker $worker, Request $request, StorePermissionService $permission, WorkerService $workerService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar trabajadores en esta tienda.');
        }
        $permission->authorize($store, 'workers.edit');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'role_id' => ['required', 'exists:roles,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);

        if (! \App\Models\Role::where('id', $request->role_id)->where('store_id', $store->id)->exists()) {
            return redirect()->back()->withInput()->with('error', 'El rol no pertenece a esta tienda.');
        }

        try {
            $workerService->updateWorker($worker, $request->only(['name', 'email', 'role_id', 'phone', 'document_number', 'address']));
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador actualizado correctamente.');
    }

    public function destroyWorker(Store $store, Worker $worker, StorePermissionService $permission, WorkerService $workerService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para eliminar trabajadores en esta tienda.');
        }
        $permission->authorize($store, 'workers.destroy');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        $workerService->deleteWorker($worker);

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador eliminado de la tienda.');
    }

    public function roles(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'roles.view');

        session(['current_store_id' => $store->id]);

        // 3. Obtenemos los roles de la tienda con sus permisos
        $roles = $store->roles()
            ->with('permissions')
            ->get();

        // 4. Obtenemos todos los permisos disponibles (para poder asignarlos a roles)
        $allPermissions = \App\Models\Permission::orderBy('name')->get();

        // 5. Retornamos la vista con los roles y permisos
        return view('stores.roles', compact('store', 'roles', 'allPermissions'));
    }

    public function storeRole(Store $store, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear roles en esta tienda.');
        }
        $permission->authorize($store, 'roles.create');

        $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        \App\Models\Role::create([
            'store_id' => $store->id,
            'name' => $request->input('name'),
        ]);

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol creado correctamente.');
    }

    public function updateRole(Store $store, \App\Models\Role $role, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar roles en esta tienda.');
        }
        $permission->authorize($store, 'roles.edit');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $role->update(['name' => $request->input('name')]);

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function destroyRole(Store $store, \App\Models\Role $role, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para eliminar roles en esta tienda.');
        }
        $permission->authorize($store, 'roles.destroy');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        $workersConRol = $store->workerRecords()->where('role_id', $role->id)->count();
        if ($workersConRol > 0) {
            return redirect()->back()->with('error', "Hay {$workersConRol} trabajador(es) con este rol. Reasígnalos a otro rol antes de eliminarlo.");
        }

        \DB::table('store_user')->where('store_id', $store->id)->where('role_id', $role->id)->update(['role_id' => null]);

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol eliminado correctamente.');
    }

    public function rolePermissions(Store $store, \App\Models\Role $role, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'roles.permissions');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        session(['current_store_id' => $store->id]);

        $role->load('permissions');
        $allPermissions = \App\Models\Permission::orderBy('name')->get();
        $workersWithRole = $store->workerRecords()->where('role_id', $role->id)->get();

        return view('stores.role-permissions', compact('store', 'role', 'allPermissions', 'workersWithRole'));
    }

    public function updateRolePermissions(Store $store, \App\Models\Role $role, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para gestionar permisos en esta tienda.');
        }
        $permission->authorize($store, 'roles.permissions');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $permissionIds = $request->input('permission_ids', []) ?: [];
        $role->permissions()->sync($permissionIds);

        return redirect()->route('stores.roles.permissions', [$store, $role])
            ->with('success', 'Permisos actualizados correctamente.');
    }

    public function products(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'products.view');

        session(['current_store_id' => $store->id]);

        $products = $store->products()
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('stores.productos', compact('store', 'products'));
    }

    public function showProduct(Store $store, \App\Models\Product $product, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'products.view');
        if ($product->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $product->load(['category.attributes.options', 'productItems', 'batches.batchItems', 'allowedVariantOptions', 'attributeValues.attribute']);

        return view('stores.producto-detalle', compact('store', 'product'));
    }

    /**
     * Actualiza las opciones de atributos permitidas para variantes de este producto (lista blanca).
     * Solo se aceptan option_ids que pertenezcan a atributos de la categoría del producto.
     */
    public function updateProductVariantOptions(Store $store, \App\Models\Product $product, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    /**
     * Actualiza una variante del producto (atributos y/o precio al público) usando ProductService::updateVariantFeatures.
     */
    public function updateVariant(Store $store, \App\Models\Product $product, Request $request, ProductService $productService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load('category.attributes');
        $category = $product->category;
        if (! $category || $category->attributes->isEmpty()) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', 'El producto debe tener una categoría con atributos.');
        }

        $oldFeatures = array_filter(
            $request->input('old_attribute_values', []),
            fn ($v) => $v !== null && $v !== ''
        );
        $rawNew = $request->input('attribute_values', []);
        $newFeatures = [];
        foreach ($category->attributes as $attr) {
            $v = $rawNew[$attr->id] ?? ($attr->type === 'boolean' ? '0' : null);
            if ($v === null || $v === '') {
                continue;
            }
            $newFeatures[$attr->id] = $v;
        }

        $price = $request->input('price');
        $priceValue = null;
        if ($price !== null && $price !== '') {
            $priceValue = (float) $price;
        }

        $isActive = $request->boolean('is_active');

        try {
            $productService->updateVariantFeatures($store, $product, $oldFeatures, $newFeatures, $priceValue, $isActive);
        } catch (\Exception $e) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('stores.products.show', [$store, $product])
            ->with('success', 'Variante actualizada correctamente.');
    }

    /**
     * Crea una o más variantes nuevas para el producto por lote (ProductService::addVariantsToProduct).
     */
    public function storeVariants(Store $store, \App\Models\Product $product, Request $request, ProductService $productService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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
            'price'            => $request->input('price') !== '' && $request->input('price') !== null ? (float) $request->input('price') : null,
            'has_stock'        => $request->boolean('has_stock'),
            'stock_initial'    => $request->input('stock_initial'),
            'cost'             => $request->input('cost'),
            'batch_number'     => $request->input('batch_number'),
            'expiration_date'  => $request->input('expiration_date') ?: null,
        ];

        try {
            $productService->addVariantsToProduct($store, $product, [$variant], Auth::id());
        } catch (\Exception $e) {
            return redirect()->route('stores.products.show', [$store, $product])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('stores.products.show', [$store, $product])
            ->with('success', 'Variante creada correctamente.');
    }

    public function destroyProduct(Store $store, \App\Models\Product $product, ProductService $productService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function carrito(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        return view('stores.ventas.carrito', compact('store'));
    }

    public function cotizaciones(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'invoices.view');

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
        $permission->authorize($store, 'invoices.view');

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
        $permission->authorize($store, 'invoices.view');

        if ($cotizacion->store_id !== $store->id) {
            abort(404);
        }

        $cotizacionService->eliminarCotizacion($cotizacion);

        return redirect()->route('stores.ventas.cotizaciones', $store)
            ->with('success', 'Cotización eliminada correctamente.');
    }

    // ==================== FACTURAS ====================

    public function invoices(Store $store, InvoiceService $invoiceService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'invoices.view');

        session(['current_store_id' => $store->id]);

        // Obtener rango de fechas por defecto (últimos 31 días)
        $rangoFechas = $invoiceService->getRangoFechasPorDefecto();

        $filtros = [
            'status' => $request->get('status'),
            'customer_id' => $request->get('customer_id'),
            'payment_method' => $request->get('payment_method'),
            'search' => $request->get('search'),
            'fecha_desde' => $request->get('fecha_desde', $rangoFechas['fecha_desde']->format('Y-m-d')),
            'fecha_hasta' => $request->get('fecha_hasta', $rangoFechas['fecha_hasta']->format('Y-m-d')),
            'per_page' => $request->get('per_page', 10),
        ];

        $invoices = $invoiceService->listarFacturas($store, $filtros);
        $customers = \App\Models\Customer::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.facturas', compact('store', 'invoices', 'customers', 'rangoFechas'));
    }

    public function showInvoice(Store $store, \App\Models\Invoice $invoice, InvoiceService $invoiceService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'invoices.view');

        if ($invoice->store_id !== $store->id) {
            abort(404);
        }

        $invoice = $invoiceService->obtenerFactura($store, $invoice->id);

        return view('stores.factura-detalle', compact('store', 'invoice'));
    }

    public function storeInvoice(Store $store, StoreInvoiceRequest $request, InvoiceService $invoiceService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'invoices.create');

        try {
            $invoice = $invoiceService->crearFactura($store, Auth::id(), $request->validated());
            return redirect()->route('stores.invoices', $store)
                ->with('success', 'Factura creada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.invoices', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function voidInvoice(Store $store, \App\Models\Invoice $invoice, InvoiceService $invoiceService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'invoices.void');

        if ($invoice->store_id !== $store->id) {
            abort(404);
        }

        try {
            $invoiceService->anularFactura($store, $invoice);
            return redirect()->route('stores.invoices', $store)
                ->with('success', 'Factura anulada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.invoices', $store)
                ->with('error', $e->getMessage());
        }
    }

    // ==================== PROVEEDORES ====================

    public function proveedores(Store $store, ProveedorService $proveedorService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'proveedores.view');

        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
        ];

        $proveedores = $proveedorService->listarProveedores($store, $filtros);

        return view('stores.proveedores', compact('store', 'proveedores'));
    }

    public function storeProveedor(Store $store, StoreProveedorRequest $request, ProveedorService $proveedorService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'proveedores.create');

        try {
            $data = $request->validated();
            $data['estado'] = $request->boolean('estado', true);
            $proveedorService->crearProveedor($store, $data);
            return redirect()->route('stores.proveedores', $store)
                ->with('success', 'Proveedor creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.proveedores', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function updateProveedor(Store $store, \App\Models\Proveedor $proveedor, StoreProveedorRequest $request, ProveedorService $proveedorService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'proveedores.edit');

        if ($proveedor->store_id !== $store->id) {
            abort(404);
        }

        try {
            $data = $request->validated();
            $data['estado'] = $request->boolean('estado', true);
            $proveedorService->actualizarProveedor($store, $proveedor->id, $data);
            return redirect()->route('stores.proveedores', $store)
                ->with('success', 'Proveedor actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.proveedores', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function destroyProveedor(Store $store, \App\Models\Proveedor $proveedor, ProveedorService $proveedorService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'proveedores.destroy');

        if ($proveedor->store_id !== $store->id) {
            abort(404);
        }

        try {
            $proveedorService->eliminarProveedor($store, $proveedor->id);
            return redirect()->route('stores.proveedores', $store)
                ->with('success', 'Proveedor eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.proveedores', $store)
                ->with('error', $e->getMessage());
        }
    }

    // ==================== CLIENTES ====================

    public function customers(Store $store, CustomerService $customerService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'customers.view');

        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
        ];

        $customers = $customerService->getStoreCustomers($store, $filtros);

        return view('stores.clientes', compact('store', 'customers'));
    }

    public function storeCustomer(Store $store, StoreCustomerRequest $request, CustomerService $customerService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'customers.create');

        try {
            $customer = $customerService->createCustomer($store, $request->validated());
            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.customers', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function updateCustomer(Store $store, \App\Models\Customer $customer, StoreCustomerRequest $request, CustomerService $customerService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'customers.edit');

        if ($customer->store_id !== $store->id) {
            abort(404);
        }

        try {
            $customerService->updateCustomer($store, $customer->id, $request->validated());
            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.customers', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function destroyCustomer(Store $store, \App\Models\Customer $customer, CustomerService $customerService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'customers.destroy');

        if ($customer->store_id !== $store->id) {
            abort(404);
        }

        try {
            $customerService->deleteCustomer($store, $customer->id);
            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.customers', $store)
                ->with('error', $e->getMessage());
        }
    }

    // ==================== CAJA (suma de bolsillos) Y BOLSILLOS ====================

    public function caja(Store $store, CajaService $cajaService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'caja.view');
        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
            'is_active' => $request->has('is_active') ? (bool) $request->get('is_active') : null,
            'per_page' => $request->get('per_page', 15),
        ];
        $bolsillos = $cajaService->listarBolsillos($store, $filtros);
        $totalCaja = $cajaService->totalCaja($store);
        return view('stores.caja', compact('store', 'bolsillos', 'totalCaja'));
    }

    public function showBolsillo(Store $store, \App\Models\Bolsillo $bolsillo, CajaService $cajaService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'caja.view');
        if ($bolsillo->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'bolsillo_id' => $bolsillo->id,
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'per_page' => $request->get('per_page', 15),
        ];
        $movimientos = $cajaService->listarMovimientos($store, $filtros);
        $bolsillosActivos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        return view('stores.bolsillo-detalle', compact('store', 'bolsillo', 'movimientos', 'bolsillosActivos'));
    }

    public function storeBolsillo(Store $store, Request $request, CajaService $cajaService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'caja.bolsillos.create');
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'detalles' => ['nullable', 'string', 'max:1000'],
            'saldo' => ['nullable', 'numeric', 'min:0'],
            'is_bank_account' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        try {
            $cajaService->crearBolsillo($store, [
                'name' => $request->input('name'),
                'detalles' => $request->input('detalles'),
                'saldo' => (float) ($request->input('saldo') ?? 0),
                'is_bank_account' => (bool) $request->input('is_bank_account', false),
                'is_active' => (bool) $request->input('is_active', true),
            ]);
            return redirect()->route('stores.cajas', $store)->with('success', 'Bolsillo creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.cajas', $store)->with('error', $e->getMessage());
        }
    }

    public function updateBolsillo(Store $store, \App\Models\Bolsillo $bolsillo, Request $request, CajaService $cajaService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'caja.bolsillos.edit');
        if ($bolsillo->store_id !== $store->id) {
            abort(404);
        }
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'detalles' => ['nullable', 'string', 'max:1000'],
            'is_bank_account' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        try {
            $cajaService->actualizarBolsillo($bolsillo, [
                'name' => $request->input('name'),
                'detalles' => $request->input('detalles'),
                'is_bank_account' => (bool) $request->input('is_bank_account', false),
                'is_active' => (bool) $request->input('is_active', true),
            ]);
            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with('success', 'Bolsillo actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with('error', $e->getMessage());
        }
    }

    public function destroyBolsillo(Store $store, \App\Models\Bolsillo $bolsillo, CajaService $cajaService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'caja.bolsillos.destroy');
        if ($bolsillo->store_id !== $store->id) {
            abort(404);
        }
        try {
            $cajaService->eliminarBolsillo($bolsillo);
            return redirect()->route('stores.cajas', $store)->with('success', 'Bolsillo eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.cajas', $store)->with('error', $e->getMessage());
        }
    }

    // ==================== INVENTARIO (movimientos entrada/salida productos) ====================

    public function inventario(Store $store, InventarioService $inventarioService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'inventario.view');
        session(['current_store_id' => $store->id]);

        $filtros = [
            'product_id'   => $request->get('product_id'),
            'type'         => $request->get('type'),
            'fecha_desde'  => $request->get('fecha_desde'),
            'fecha_hasta'  => $request->get('fecha_hasta'),
            'per_page'     => $request->get('per_page', 15),
        ];
        $movimientos = $inventarioService->listarMovimientos($store, $filtros);
        $productosInventario = $inventarioService->productosConInventario($store);

        return view('stores.inventario', compact('store', 'movimientos', 'productosInventario'));
    }

    public function storeMovimientoInventario(Store $store, Request $request, InventarioService $inventarioService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'inventario.movimientos.create');
        $request->validate([
            'product_id'  => ['required', 'exists:products,id'],
            'type'        => ['required', 'in:ENTRADA,SALIDA'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
            'unit_cost'   => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $inventarioService->registrarMovimiento($store, Auth::id(), [
                'product_id'  => (int) $request->input('product_id'),
                'type'        => $request->input('type'),
                'quantity'    => (int) $request->input('quantity'),
                'description' => $request->input('description') ?: null,
                'unit_cost'   => $request->input('unit_cost') !== '' && $request->input('unit_cost') !== null
                    ? (float) $request->input('unit_cost')
                    : null,
            ]);
            return redirect()->route('stores.inventario', $store)->with('success', 'Movimiento de inventario registrado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.inventario', $store)->with('error', $e->getMessage());
        }
    }

    // ==================== ACTIVOS (espejo de products: computadores, muebles, etc.) ====================

    public function activos(Store $store, ActivoService $activoService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
            'is_active' => $request->get('is_active'),
            'per_page' => $request->get('per_page', 15),
        ];
        $activos = $activoService->listarActivos($store, $filtros);

        return view('stores.activos', compact('store', 'activos'));
    }

    public function activosMovimientos(Store $store, ActivoService $activoService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'activo_id'   => $request->get('activo_id'),
            'type'        => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'per_page'    => $request->get('per_page', 15),
        ];
        $movimientos = $activoService->listarMovimientos($store, $filtros);
        $activosParaMovimientos = $activoService->activosParaMovimientos($store);

        return view('stores.activo-movimientos', compact('store', 'movimientos', 'activosParaMovimientos'));
    }

    public function createActivo(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $workers = $store->workers()->select('users.id', 'users.name')->orderBy('users.name')->get();
        return view('stores.activo-crear', compact('store', 'workers'));
    }

    public function storeActivo(Store $store, Request $request, ActivoService $activoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $rules = [
            'control_type' => ['required', 'in:LOTE,SERIALIZADO'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'condition' => ['nullable', 'string', 'in:NUEVO,BUENO,REGULAR,MALO'],
            'status' => ['nullable', 'string', 'in:ACTIVO,EN_MANTENIMIENTO,BAJA,PRESTADO'],
            'is_active' => ['nullable', 'boolean'],
        ];
        if ($request->input('control_type') === 'SERIALIZADO' && (int) $request->input('quantity') === 1) {
            $rules['serial_number'] = ['required', 'string', 'max:100'];
        }
        if ($request->input('assigned_to_user_id') === '') {
            $request->merge(['assigned_to_user_id' => null]);
        }
        $request->validate($rules);

        try {
            $activoService->crearActivo($store, $request->all(), Auth::id());
            return redirect()->route('stores.activos', $store)->with('success', 'Activo creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.activos.create', $store)->with('error', $e->getMessage());
        }
    }

    public function editActivo(Store $store, \App\Models\Activo $activo)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($activo->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $workers = $store->workers()->select('users.id', 'users.name')->orderBy('users.name')->get();
        return view('stores.activo-editar', compact('store', 'activo', 'workers'));
    }

    public function updateActivo(Store $store, \App\Models\Activo $activo, Request $request, ActivoService $activoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($activo->store_id !== $store->id) {
            abort(404);
        }

        if ($request->input('assigned_to_user_id') === '') {
            $request->merge(['assigned_to_user_id' => null]);
        }
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'condition' => ['nullable', 'string', 'in:NUEVO,BUENO,REGULAR,MALO'],
            'status' => ['nullable', 'string', 'in:ACTIVO,EN_MANTENIMIENTO,BAJA,PRESTADO'],
            'warranty_expiry' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $activoService->actualizarActivo($store, $activo->id, $request->only(['name', 'code', 'serial_number', 'brand', 'model', 'description', 'location', 'purchase_date', 'assigned_to_user_id', 'condition', 'status', 'warranty_expiry', 'is_active']));
            return redirect()->route('stores.activos', $store)->with('success', 'Activo actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.activos.edit', [$store, $activo])->with('error', $e->getMessage());
        }
    }

    public function destroyActivo(Store $store, \App\Models\Activo $activo, ActivoService $activoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($activo->store_id !== $store->id) {
            abort(404);
        }

        try {
            $activoService->eliminarActivo($store, $activo->id);
            return redirect()->route('stores.activos', $store)->with('success', 'Activo eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.activos', $store)->with('error', $e->getMessage());
        }
    }

    public function buscarActivos(Store $store, Request $request, ActivoService $activoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $term = $request->get('q', '');
        $activos = $activoService->buscarActivos($store, $term, 15);

        return response()->json($activos);
    }

    public function buscarProductosInventario(Store $store, Request $request, InventarioService $inventarioService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $term = $request->get('q', '');
        $productos = $inventarioService->buscarProductosInventario($store, $term, 15);

        return response()->json($productos);
    }

    /**
     * Atributos de la categoría del producto (para compra de productos serializados: cantidad, atributos, costo).
     */
    /**
     * Atributos de la categoría del producto con sus opciones (para variantes).
     * Si el producto tiene "variantes permitidas" configuradas, solo se devuelven esas opciones;
     * si no, todas las opciones del atributo (para no bloquear productos aún no configurados).
     */
    public function productCategoryAttributes(Store $store, \App\Models\Product $product)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($product->store_id !== $store->id) {
            abort(404);
        }

        $product->load([
            'category.attributes' => fn ($q) => $q->orderByPivot('position'),
            'allowedVariantOptions',
        ]);

        $categoryAttributes = $product->category?->attributes ?? collect();
        $allowedOptionIds = $product->allowedVariantOptions->pluck('id')->all();

        $attributes = $categoryAttributes->map(function ($attr) use ($product) {
            $attr->load('options');
            $options = $attr->options;
            // Si el producto tiene variantes permitidas para este atributo, solo esas opciones
            $allowedForThisAttr = $product->allowedVariantOptions->where('attribute_id', $attr->id)->pluck('id');
            if ($allowedForThisAttr->isNotEmpty()) {
                $options = $options->whereIn('id', $allowedForThisAttr->all());
            }
            return [
                'id' => $attr->id,
                'name' => $attr->name,
                'options' => $options->map(fn ($o) => ['id' => $o->id, 'value' => $o->value])->values()->all(),
            ];
        })->values()->all();

        return response()->json(['attributes' => $attributes]);
    }

    // ==================== COMPRAS ====================

    public function purchases(Store $store, PurchaseService $purchaseService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'status' => $request->get('status'),
            'purchase_type' => \App\Models\Purchase::TYPE_ACTIVO,
            'payment_status' => $request->get('payment_status'),
            'proveedor_id' => $request->get('proveedor_id'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];
        $purchases = $purchaseService->listarCompras($store, $filtros);
        $proveedores = \App\Models\Proveedor::deTienda($store->id)->orderBy('nombre')->get();

        return view('stores.compras', compact('store', 'purchases', 'proveedores'));
    }

    public function createPurchase(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $proveedores = \App\Models\Proveedor::deTienda($store->id)->orderBy('nombre')->get();

        return view('stores.compra-crear', compact('store', 'proveedores'));
    }

    /**
     * Listado de compras de productos (inventario). Solo tipo PRODUCTO.
     */
    public function productPurchases(Store $store, PurchaseService $purchaseService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.view');
        session(['current_store_id' => $store->id]);

        $filtros = [
            'status' => $request->get('status'),
            'purchase_type' => \App\Models\Purchase::TYPE_PRODUCTO,
            'payment_status' => $request->get('payment_status'),
            'proveedor_id' => $request->get('proveedor_id'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];
        $purchases = $purchaseService->listarCompras($store, $filtros);
        $proveedores = \App\Models\Proveedor::deTienda($store->id)->orderBy('nombre')->get();

        return view('stores.compras-productos', compact('store', 'purchases', 'proveedores'));
    }

    /**
     * Vista para crear compra de productos (inventario).
     */
    public function createProductPurchase(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');
        session(['current_store_id' => $store->id]);

        $proveedores = \App\Models\Proveedor::deTienda($store->id)->orderBy('nombre')->get();

        return view('stores.compra-productos-crear', compact('store', 'proveedores'));
    }

    /**
     * Guarda la compra de productos como borrador.
     * Normaliza filas con serial_items (cantidad = nº unidades, costo = promedio por unidad).
     */
    public function storeProductPurchase(Store $store, Request $request, PurchaseService $purchaseService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => [
                $request->input('payment_status') === \App\Models\Purchase::PAYMENT_PENDIENTE ? 'required' : 'nullable',
                'date',
            ],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
        ], [
            'invoice_date.required' => 'La fecha de la factura es obligatoria cuando la compra es a crédito (para la fecha de vencimiento).',
        ]);

        $rawDetails = $request->input('details', []);
        $normalized = $this->normalizeProductPurchaseDetails($rawDetails, $store);

        \Illuminate\Support\Facades\Validator::make(
            ['details' => $normalized] + $request->only(['due_date', 'invoice_date']),
            [
                'details' => ['required', 'array', 'min:1'],
                'details.*.product_id' => ['nullable', 'exists:products,id'],
                'details.*.description' => ['required', 'string', 'max:255'],
                'details.*.quantity' => ['required', 'integer', 'min:0'],
                'details.*.unit_cost' => ['required', 'numeric', 'min:0'],
                'due_date' => array_merge(
                    $request->input('payment_status') === \App\Models\Purchase::PAYMENT_PENDIENTE ? ['required', 'date'] : ['nullable', 'date'],
                    $request->filled('invoice_date') ? ['after_or_equal:invoice_date'] : []
                ),
            ],
            [
                'details.*.description.required' => 'Debes seleccionar al menos un producto en el detalle. Haz clic en "Seleccionar" en cada línea.',
                'due_date.required' => 'La fecha de vencimiento de la factura es obligatoria cuando la compra es a crédito.',
                'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de la factura.',
            ]
        )->validate();

        foreach ($normalized as $i => $detail) {
            if (! empty($detail['serial_items'] ?? [])) {
                foreach ($detail['serial_items'] as $j => $unit) {
                    if (trim($unit['serial_number'] ?? '') === '') {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'details' => ['Cada unidad de producto serializado debe tener número de serie. Revisa la línea ' . ($i + 1) . ', unidad ' . ($j + 1) . '.'],
                        ]);
                    }
                }
            }
        }

        $request->merge(['details' => $normalized, 'purchase_type' => \App\Models\Purchase::TYPE_PRODUCTO]);

        try {
            $purchaseService->crearCompra($store, Auth::id(), $request->all());
            return redirect()->route('stores.product-purchases', $store)->with('success', 'Compra guardada como borrador.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Convierte detalles de compra de productos al formato esperado por PurchaseService.
     * Serial_items: una fila por unidad (Opción A) con descripción Producto — Serial — Atributos.
     * Batch_items: quantity = suma de cantidades, unit_cost = promedio ponderado, se guarda batch_items.
     */
    protected function normalizeProductPurchaseDetails(array $rawDetails, \App\Models\Store $store): array
    {
        $normalized = [];
        foreach ($rawDetails as $d) {
            $serialItems = $d['serial_items'] ?? null;
            if (is_array($serialItems) && count($serialItems) > 0) {
                $productId = $d['product_id'] ?? null;
                $product = $productId
                    ? \App\Models\Product::where('id', $productId)->where('store_id', $store->id)->with('category.attributes')->first()
                    : null;
                $productName = $product?->name ?? ($d['description'] ?? 'Producto');
                $attrById = $product?->category?->attributes?->keyBy('id') ?? collect();

                foreach ($serialItems as $unit) {
                    $serial = trim($unit['serial_number'] ?? '');
                    if ($serial === '') {
                        continue;
                    }
                    $cost = (float) ($unit['cost'] ?? 0);
                    $features = $unit['features'] ?? [];
                    if (is_array($features)) {
                        $features = array_filter($features, fn ($v) => $v !== '' && $v !== null);
                    } else {
                        $features = [];
                    }
                    $parts = [];
                    foreach ($features as $attrId => $val) {
                        $attr = $attrById->get((int) $attrId) ?? $attrById->get((string) $attrId);
                        $attrName = $attr?->name ?? "Atributo {$attrId}";
                        $parts[] = "{$attrName}: {$val}";
                    }
                    $attrsStr = implode(', ', $parts);
                    $description = $attrsStr !== '' ? "{$productName} — {$serial} — {$attrsStr}" : "{$productName} — {$serial}";

                    $unitForStorage = [
                        'serial_number' => $serial,
                        'cost' => $cost,
                        'features' => ! empty($features) ? $features : null,
                    ];
                    if (isset($unit['price']) && $unit['price'] !== '' && $unit['price'] !== null) {
                        $unitForStorage['price'] = (float) $unit['price'];
                    }

                    $normalized[] = [
                        'item_type' => 'INVENTARIO',
                        'product_id' => $productId,
                        'activo_id' => null,
                        'description' => $description,
                        'quantity' => 1,
                        'unit_cost' => $cost,
                        'serial_items' => [$unitForStorage],
                    ];
                }
            } elseif (! empty($d['batch_items']) && is_array($d['batch_items'])) {
                $batchItems = $d['batch_items'];
                $totalQty = 0;
                $subtotal = 0;
                $itemsForStorage = [];
                foreach ($batchItems as $item) {
                    $qty = (int) ($item['quantity'] ?? 0);
                    if ($qty < 1) {
                        continue;
                    }
                    $unitCost = (float) ($item['unit_cost'] ?? 0);
                    $totalQty += $qty;
                    $subtotal += $qty * $unitCost;
                    $itemForStorage = [
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'price' => isset($item['price']) && $item['price'] !== '' ? (float) $item['price'] : null,
                        'expiration_date' => isset($item['expiration_date']) && $item['expiration_date'] !== '' ? $item['expiration_date'] : null,
                    ];
                    // Preferir batch_item_id: el backend obtendrá features desde el BatchItem
                    if (! empty($item['batch_item_id'])) {
                        $itemForStorage['batch_item_id'] = (int) $item['batch_item_id'];
                    } else {
                        // Retrocompatibilidad: borradores antiguos con features
                        $features = $item['features'] ?? null;
                        if (is_array($features)) {
                            $features = array_filter($features, fn ($v) => $v !== '' && $v !== null);
                        }
                        $itemForStorage['features'] = ! empty($features) ? $features : null;
                    }
                    $itemsForStorage[] = $itemForStorage;
                }
                $normalized[] = [
                    'item_type' => 'INVENTARIO',
                    'product_id' => $d['product_id'] ?? null,
                    'activo_id' => null,
                    'description' => $d['description'] ?? '',
                    'quantity' => $totalQty,
                    'unit_cost' => $totalQty > 0 ? round($subtotal / $totalQty, 2) : 0,
                    'batch_items' => $itemsForStorage,
                ];
            } else {
                $normalized[] = [
                    'item_type' => $d['item_type'] ?? 'INVENTARIO',
                    'product_id' => $d['product_id'] ?? null,
                    'activo_id' => $d['activo_id'] ?? null,
                    'description' => $d['description'] ?? '',
                    'quantity' => (int) ($d['quantity'] ?? 0),
                    'unit_cost' => (float) ($d['unit_cost'] ?? 0),
                ];
            }
        }
        return $normalized;
    }

    /**
     * Editar compra (borrador): reutiliza el formulario de crear (productos o activos según tipo).
     * Muestra "Editar compra" y los datos ya guardados para corregir lo que haga falta.
     */
    public function editPurchase(Store $store, \App\Models\Purchase $purchase)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($purchase->store_id !== $store->id) {
            abort(404);
        }
        if (! $purchase->isBorrador()) {
            return redirect()->route('stores.purchases.show', [$store, $purchase])
                ->with('error', 'Solo se pueden editar compras en estado BORRADOR.');
        }
        session(['current_store_id' => $store->id]);

        $purchase->load(['details.product.category.attributes.options', 'details.activo', 'proveedor']);
        $proveedores = \App\Models\Proveedor::deTienda($store->id)->orderBy('nombre')->get();

        // Reutilizar el formulario de crear: productos o activos según el tipo de compra
        if ($purchase->purchase_type === \App\Models\Purchase::TYPE_PRODUCTO) {
            $detailsForEdit = $purchase->details->map(function ($d) {
                $product = $d->product;
                $productType = $product ? ($product->type ?: 'simple') : 'simple';
                $item = [
                    'item_type' => 'INVENTARIO',
                    'product_id' => $d->product_id,
                    'description' => $d->description,
                    'quantity' => (int) $d->quantity,
                    'unit_cost' => (float) $d->unit_cost,
                    'product_type' => $productType,
                ];
                if (! empty($d->serial_items) && is_array($d->serial_items)) {
                    $item['serial_items'] = $d->serial_items;
                }
                if (! empty($d->batch_items) && is_array($d->batch_items)) {
                    $item['batch_items'] = $d->batch_items;
                }
                return $item;
            })->values()->all();

            return view('stores.compra-productos-crear', compact('store', 'proveedores', 'purchase', 'detailsForEdit'));
        }

        // Compra de activos: reutilizar compra-crear con datos de la compra
        return view('stores.compra-crear', compact('store', 'proveedores', 'purchase'));
    }

    public function showPurchase(Store $store, \App\Models\Purchase $purchase, PurchaseService $purchaseService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($purchase->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $purchase = $purchaseService->obtenerCompra($store, $purchase->id);

        $bolsillos = null;
        if ($purchase->isBorrador() && $purchase->payment_status === \App\Models\Purchase::PAYMENT_PAGADO) {
            $bolsillos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        }

        return view('stores.compra-detalle', compact('store', 'purchase', 'bolsillos'));
    }

    /**
     * Valida que activos serializados tengan cantidad 0 o 1, y el resto cantidad >= 1.
     */
    protected function validarCantidadActivosSerializados(Store $store, array $details): void
    {
        $activos = \App\Models\Activo::where('store_id', $store->id)
            ->whereIn('id', collect($details)->pluck('activo_id')->filter()->values())
            ->get()
            ->keyBy('id');

        foreach ($details as $i => $d) {
            $qty = (int) ($d['quantity'] ?? 0);
            if ($d['item_type'] === 'INVENTARIO') {
                if ($qty < 1) {
                    throw ValidationException::withMessages([
                        "details.{$i}.quantity" => ['La cantidad debe ser al menos 1 para productos de inventario.'],
                    ]);
                }
                continue;
            }
            if (empty($d['activo_id'])) {
                continue;
            }
            $activo = $activos->get($d['activo_id']);
            if (! $activo) {
                continue;
            }
            if ($activo->control_type === \App\Models\Activo::CONTROL_SERIALIZADO) {
                if ($qty < 0 || $qty > 1) {
                    throw ValidationException::withMessages([
                        "details.{$i}.quantity" => ["El activo «{$activo->name}» es serializado (único). La cantidad debe ser 0 o 1."],
                    ]);
                }
            } else {
                if ($qty < 1) {
                    throw ValidationException::withMessages([
                        "details.{$i}.quantity" => ["La cantidad debe ser al menos 1 para el activo «{$activo->name}»."],
                    ]);
                }
            }
        }
    }

    public function storePurchase(Store $store, Request $request, PurchaseService $purchaseService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.product_id' => ['nullable', 'exists:products,id'],
            'details.*.activo_id' => ['nullable', 'exists:activos,id'],
            'details.*.item_type' => ['required', 'in:INVENTARIO,ACTIVO_FIJO'],
            'details.*.description' => ['required', 'string', 'max:255'],
            'details.*.quantity' => ['required', 'integer', 'min:0'],
            'details.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ], [
            'details.*.description.required' => 'Debes seleccionar al menos un producto o bien en el detalle de la compra. Haz clic en "Seleccionar" en cada línea.',
        ]);

        $this->validarCantidadActivosSerializados($store, $request->input('details', []));

        $request->merge(['purchase_type' => \App\Models\Purchase::TYPE_ACTIVO]);

        try {
            $purchaseService->crearCompra($store, Auth::id(), $request->all());
            return redirect()->route('stores.purchases', $store)->with('success', 'Compra creada correctamente.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function updatePurchase(Store $store, \App\Models\Purchase $purchase, Request $request, PurchaseService $purchaseService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($purchase->store_id !== $store->id) {
            abort(404);
        }
        if (! $purchase->isBorrador()) {
            $route = $purchase->isProducto() ? 'stores.purchases.show' : 'stores.purchases.show';
            return redirect()->route($route, [$store, $purchase])
                ->with('error', 'Solo se pueden editar compras en estado BORRADOR.');
        }

        // Compra de productos: mismo flujo que crear (normalizar detalles y validar)
        if ($purchase->purchase_type === \App\Models\Purchase::TYPE_PRODUCTO) {
            $request->validate([
                'proveedor_id' => ['nullable', 'exists:proveedores,id'],
                'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
                'invoice_number' => ['nullable', 'string', 'max:100'],
                'invoice_date' => [
                    $request->input('payment_status') === \App\Models\Purchase::PAYMENT_PENDIENTE ? 'required' : 'nullable',
                    'date',
                ],
                'due_date' => ['nullable', 'date'],
                'details' => ['required', 'array', 'min:1'],
            ], [
                'invoice_date.required' => 'La fecha de la factura es obligatoria cuando la compra es a crédito.',
            ]);

            $rawDetails = $request->input('details', []);
            $normalized = $this->normalizeProductPurchaseDetails($rawDetails, $store);

            \Illuminate\Support\Facades\Validator::make(
                ['details' => $normalized] + $request->only(['due_date', 'invoice_date']),
                [
                    'details' => ['required', 'array', 'min:1'],
                    'details.*.product_id' => ['nullable', 'exists:products,id'],
                    'details.*.description' => ['required', 'string', 'max:255'],
                    'details.*.quantity' => ['required', 'integer', 'min:0'],
                    'details.*.unit_cost' => ['required', 'numeric', 'min:0'],
                    'due_date' => array_merge(
                        $request->input('payment_status') === \App\Models\Purchase::PAYMENT_PENDIENTE ? ['required', 'date'] : ['nullable', 'date'],
                        $request->filled('invoice_date') ? ['after_or_equal:invoice_date'] : []
                    ),
                ],
                [
                    'details.*.description.required' => 'Debes seleccionar al menos un producto en el detalle. Haz clic en "Seleccionar" en cada línea.',
                    'due_date.required' => 'La fecha de vencimiento es obligatoria cuando la compra es a crédito.',
                    'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de la factura.',
                ]
            )->validate();

            foreach ($normalized as $i => $detail) {
                if (! empty($detail['serial_items'] ?? [])) {
                    foreach ($detail['serial_items'] as $j => $unit) {
                        if (trim($unit['serial_number'] ?? '') === '') {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'details' => ['Cada unidad de producto serializado debe tener número de serie. Revisa la línea ' . ($i + 1) . ', unidad ' . ($j + 1) . '.'],
                            ]);
                        }
                    }
                }
            }

            $request->merge(['details' => $normalized]);

            try {
                $purchaseService->actualizarCompra($store, $purchase->id, $request->all());
                $route = $purchase->isProducto() ? 'stores.purchases.show' : 'stores.purchases.show';
                return redirect()->route($route, [$store, $purchase])->with('success', 'Compra actualizada correctamente.');
            } catch (\Exception $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        }

        // Compra de activos: validación original
        $request->validate([
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.product_id' => ['nullable', 'exists:products,id'],
            'details.*.activo_id' => ['nullable', 'exists:activos,id'],
            'details.*.item_type' => ['required', 'in:INVENTARIO,ACTIVO_FIJO'],
            'details.*.description' => ['required', 'string', 'max:255'],
            'details.*.quantity' => ['required', 'integer', 'min:0'],
            'details.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ], [
            'details.*.description.required' => 'Debes seleccionar al menos un producto o bien en el detalle de la compra. Haz clic en "Seleccionar" en cada línea.',
        ]);

        $this->validarCantidadActivosSerializados($store, $request->input('details', []));

        try {
            $purchaseService->actualizarCompra($store, $purchase->id, $request->all());
            // Recargar la compra para obtener el tipo actualizado
            $purchase->refresh();
            $route = $purchase->isProducto() ? 'stores.purchases.show' : 'stores.purchases.show';
            return redirect()->route($route, [$store, $purchase])->with('success', 'Compra actualizada correctamente.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approvePurchase(Store $store, \App\Models\Purchase $purchase, Request $request, PurchaseService $purchaseService, AccountPayableService $accountPayableService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($purchase->store_id !== $store->id) {
            abort(404);
        }

        $paymentData = null;
        if ($purchase->payment_status === \App\Models\Purchase::PAYMENT_PAGADO) {
            $request->validate([
                'payment_date' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:500'],
                'parts' => ['required', 'array', 'min:1'],
                'parts.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
                'parts.*.amount' => ['required', 'numeric', 'min:0.01'],
            ]);
            $sumaPartes = collect($request->input('parts'))->sum(fn ($p) => (float) ($p['amount'] ?? 0));
            if (abs($sumaPartes - (float) $purchase->total) > 0.01) {
                return redirect()->route('stores.purchases.show', [$store, $purchase])
                    ->with('error', "La suma de los montos ({$sumaPartes}) debe coincidir con el total de la compra ({$purchase->total}).");
            }
            $paymentData = [
                'payment_date' => $request->input('payment_date'),
                'notes' => $request->input('notes'),
                'parts' => $request->input('parts'),
            ];
        }

        $serialsByDetailId = null;
        $serials = $request->input('serials');
        if (is_array($serials)) {
            $serialsByDetailId = [];
            foreach ($serials as $detailId => $arr) {
                $serialsByDetailId[(int) $detailId] = array_values(array_filter(array_map('trim', (array) $arr)));
            }
        }

        try {
            $purchaseService->aprobarCompra($store, $purchase->id, Auth::id(), $accountPayableService, $paymentData, $serialsByDetailId);
            // Recargar la compra para obtener el tipo actualizado
            $purchase->refresh();
            $route = $purchase->isProducto() ? 'stores.purchases.show' : 'stores.purchases.show';
            return redirect()->route($route, [$store, $purchase])->with('success', 'Compra aprobada. Inventario actualizado.');
        } catch (\Exception $e) {
            // Recargar la compra para obtener el tipo actualizado
            $purchase->refresh();
            $route = $purchase->isProducto() ? 'stores.purchases.show' : 'stores.purchases.show';
            return redirect()->route($route, [$store, $purchase])->with('error', $e->getMessage());
        }
    }

    public function voidPurchase(Store $store, \App\Models\Purchase $purchase, PurchaseService $purchaseService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($purchase->store_id !== $store->id) {
            abort(404);
        }

        try {
            $purchaseService->anularCompra($store, $purchase->id);
            // Determinar la ruta correcta según el tipo de compra
            $route = $purchase->isProducto() ? 'stores.product-purchases' : 'stores.purchases';
            return redirect()->route($route, $store)->with('success', 'Compra anulada.');
        } catch (\Exception $e) {
            // Determinar la ruta correcta según el tipo de compra
            $route = $purchase->isProducto() ? 'stores.product-purchases' : 'stores.purchases';
            return redirect()->route($route, $store)->with('error', $e->getMessage());
        }
    }

    // ==================== CUENTAS POR PAGAR ====================

    public function accountsPayables(Store $store, AccountPayableService $accountPayableService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'status' => $request->get('status'),
        ];
        $accountsPayables = $accountPayableService->listarCuentasPorPagar($store, $filtros);
        $deudaTotal = $accountPayableService->deudaTotal($store);

        return view('stores.cuentas-por-pagar', compact('store', 'accountsPayables', 'deudaTotal'));
    }

    public function showAccountPayable(Store $store, \App\Models\AccountPayable $accountPayable, AccountPayableService $accountPayableService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($accountPayable->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $accountPayable = $accountPayableService->obtenerCuentaPorPagar($store, $accountPayable->id);
        $bolsillos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.cuenta-por-pagar-detalle', compact('store', 'accountPayable', 'bolsillos'));
    }

    public function payAccountPayable(Store $store, \App\Models\AccountPayable $accountPayable, Request $request, AccountPayableService $accountPayableService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($accountPayable->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'parts.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $accountPayableService->registrarPago($store, $accountPayable->id, Auth::id(), $request->all());
            return redirect()->route('stores.accounts-payables.show', [$store, $accountPayable])->with('success', 'Pago registrado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.accounts-payables.show', [$store, $accountPayable])->with('error', $e->getMessage());
        }
    }

    public function reversarPagoAccountPayable(Store $store, \App\Models\AccountPayable $accountPayable, \App\Models\ComprobanteEgreso $comprobanteEgreso, AccountPayableService $accountPayableService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($accountPayable->store_id !== $store->id || $comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }
        if (! $accountPayable->comprobanteDestinos()->where('comprobante_egreso_id', $comprobanteEgreso->id)->exists()) {
            abort(404, 'Este comprobante no aplica a esta cuenta por pagar.');
        }

        try {
            $accountPayableService->reversarPago($store, $accountPayable->id, $comprobanteEgreso->id, Auth::id());
            return redirect()->route('stores.accounts-payables.show', [$store, $accountPayable])->with('success', 'Pago revertido correctamente. El saldo de la cuenta y los bolsillos han sido restaurados.');
        } catch (\Exception $e) {
            return redirect()->route('stores.accounts-payables.show', [$store, $accountPayable])->with('error', $e->getMessage());
        }
    }

    // ==================== CUENTAS POR COBRAR ====================

    public function accountsReceivables(Store $store, \App\Services\AccountReceivableService $accountReceivableService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'status' => $request->get('status'),
            'customer_id' => $request->get('customer_id'),
        ];
        $cuentas = $accountReceivableService->listar($store, $filtros);
        $saldoPendiente = $accountReceivableService->saldoPendienteTotal($store);
        $customers = \App\Models\Customer::deTienda($store->id)->orderBy('name')->get(['id', 'name']);

        return view('stores.cuentas-por-cobrar', compact('store', 'cuentas', 'saldoPendiente', 'customers'));
    }

    public function showAccountReceivable(Store $store, \App\Models\AccountReceivable $accountReceivable, \App\Services\AccountReceivableService $accountReceivableService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($accountReceivable->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $accountReceivable = $accountReceivableService->obtener($store, $accountReceivable->id);
        $bolsillos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.cuenta-por-cobrar-detalle', compact('store', 'accountReceivable', 'bolsillos'));
    }

    public function cobrarAccountReceivable(Store $store, \App\Models\AccountReceivable $accountReceivable, Request $request, \App\Services\ComprobanteIngresoService $comprobanteIngresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($accountReceivable->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'lte:' . (float) $accountReceivable->balance],
            'notes' => ['nullable', 'string', 'max:500'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'parts.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $sumaPartes = collect($request->input('parts'))->sum(fn ($p) => (float) ($p['amount'] ?? 0));
        $amount = (float) $request->input('amount');
        if (abs($sumaPartes - $amount) > 0.01) {
            return redirect()->route('stores.accounts-receivables.show', [$store, $accountReceivable])
                ->with('error', "La suma de los montos por bolsillo ({$sumaPartes}) debe coincidir con el monto a cobrar ({$amount}).")->withInput();
        }

        $data = [
            'date' => $request->input('date'),
            'notes' => $request->input('notes'),
            'aplicaciones' => [
                ['account_receivable_id' => $accountReceivable->id, 'amount' => $amount],
            ],
            'destinos' => collect($request->input('parts'))->map(fn ($p) => [
                'bolsillo_id' => $p['bolsillo_id'],
                'amount' => (float) $p['amount'],
                'reference' => $p['reference'] ?? null,
            ])->filter(fn ($d) => $d['amount'] > 0)->values()->all(),
        ];

        try {
            $comprobanteIngresoService->crearComprobante($store, Auth::id(), $data);
            return redirect()->route('stores.accounts-receivables.show', [$store, $accountReceivable])->with('success', 'Cobro registrado correctamente. El dinero se ha ingresado a caja y el saldo de la cuenta se ha actualizado.');
        } catch (\Exception $e) {
            return redirect()->route('stores.accounts-receivables.show', [$store, $accountReceivable])->with('error', $e->getMessage())->withInput();
        }
    }

    // ==================== COMPROBANTES DE INGRESO ====================

    public function comprobantesIngreso(Store $store, \App\Services\ComprobanteIngresoService $comprobanteIngresoService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'type' => $request->get('type'),
            'customer_id' => $request->get('customer_id'),
        ];
        $comprobantes = $comprobanteIngresoService->listar($store, $filtros);

        return view('stores.comprobantes-ingreso', compact('store', 'comprobantes'));
    }

    public function createComprobanteIngreso(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $bolsillos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        $cuentasPendientes = \App\Models\AccountReceivable::deTienda($store->id)
            ->whereIn('status', [\App\Models\AccountReceivable::STATUS_PENDIENTE, \App\Models\AccountReceivable::STATUS_PARCIAL])
            ->with(['invoice', 'customer'])
            ->orderBy('created_at')
            ->get();

        return view('stores.comprobante-ingreso-crear', compact('store', 'bolsillos', 'cuentasPendientes'));
    }

    public function storeComprobanteIngreso(Store $store, Request $request, \App\Services\ComprobanteIngresoService $comprobanteIngresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $request->validate([
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:INGRESO_MANUAL,COBRO_CUENTA'],
            'account_receivable_id' => ['nullable', 'required_if:type,COBRO_CUENTA', 'exists:accounts_receivable,id'],
            'amount' => ['nullable', 'required_if:type,COBRO_CUENTA', 'numeric', 'min:0.01'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'parts.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = (float) ($request->input('amount') ?? 0);
        $parts = $request->input('parts', []);
        $sumaPartes = collect($parts)->sum(fn ($p) => (float) ($p['amount'] ?? 0));

        if ($request->input('type') === 'COBRO_CUENTA') {
            if (abs($sumaPartes - $amount) > 0.01) {
                return redirect()->back()->withInput()->with('error', 'La suma de los bolsillos debe coincidir con el monto a cobrar.');
            }
            $ar = \App\Models\AccountReceivable::where('id', $request->account_receivable_id)->where('store_id', $store->id)->firstOrFail();
            if ($amount > (float) $ar->balance) {
                return redirect()->back()->withInput()->with('error', 'El monto no puede ser mayor al saldo pendiente de la cuenta.');
            }
            $data = [
                'date' => $request->date,
                'notes' => $request->notes,
                'type' => 'COBRO_CUENTA',
                'aplicaciones' => [['account_receivable_id' => $ar->id, 'amount' => $amount]],
                'destinos' => collect($parts)->map(fn ($p) => ['bolsillo_id' => $p['bolsillo_id'], 'amount' => (float) $p['amount'], 'reference' => $p['reference'] ?? null])->filter(fn ($d) => $d['amount'] > 0)->values()->all(),
            ];
        } else {
            if ($sumaPartes <= 0) {
                return redirect()->back()->withInput()->with('error', 'Indique al menos un bolsillo con monto mayor a cero.');
            }
            $data = [
                'date' => $request->date,
                'notes' => $request->notes,
                'type' => 'INGRESO_MANUAL',
                'destinos' => collect($parts)->map(fn ($p) => ['bolsillo_id' => $p['bolsillo_id'], 'amount' => (float) $p['amount'], 'reference' => $p['reference'] ?? null])->filter(fn ($d) => $d['amount'] > 0)->values()->all(),
            ];
        }

        try {
            $comprobante = $comprobanteIngresoService->crearComprobante($store, Auth::id(), $data);
            return redirect()->route('stores.comprobantes-ingreso.show', [$store, $comprobante])->with('success', 'Comprobante de ingreso creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showComprobanteIngreso(Store $store, \App\Models\ComprobanteIngreso $comprobanteIngreso, \App\Services\ComprobanteIngresoService $comprobanteIngresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($comprobanteIngreso->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $comprobanteIngreso = $comprobanteIngresoService->obtener($store, $comprobanteIngreso->id);

        return view('stores.comprobante-ingreso-detalle', compact('store', 'comprobanteIngreso'));
    }

    // ==================== COMPROBANTES DE EGRESO ====================

    public function comprobantesEgreso(Store $store, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, Request $request, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.view');
        session(['current_store_id' => $store->id]);

        $filtros = [
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];
        $comprobantes = $comprobanteEgresoService->listar($store, $filtros);

        return view('stores.comprobantes-egreso', compact('store', 'comprobantes'));
    }

    public function createComprobanteEgreso(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.create');
        session(['current_store_id' => $store->id]);

        $bolsillos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();
        $proveedores = \App\Models\Proveedor::deTienda($store->id)->activos()->orderBy('nombre')->get(['id', 'nombre']);

        // Reconstruir cuentas seleccionadas cuando hay error de validación (old input)
        $cuentasSeleccionadasInit = [];
        $proveedorIdInit = null;
        $oldDestinos = old('destinos', []);
        $destinosConFactura = array_values(array_filter($oldDestinos, fn ($d) => ! empty($d['account_payable_id'] ?? null)));
        if (! empty($destinosConFactura)) {
            $ids = array_column($destinosConFactura, 'account_payable_id');
            $cuentas = \App\Models\AccountPayable::deTienda($store->id)
                ->whereIn('id', $ids)
                ->with(['purchase.proveedor'])
                ->get()
                ->keyBy('id');
            foreach ($destinosConFactura as $d) {
                $ap = $cuentas->get($d['account_payable_id']);
                if ($ap) {
                    $cuentasSeleccionadasInit[] = [
                        'id' => $ap->id,
                        'purchase_id' => $ap->purchase_id,
                        'balance' => (float) $ap->balance,
                        'due_date' => $ap->due_date?->format('Y-m-d'),
                        'amount' => (float) ($d['amount'] ?? $ap->balance),
                    ];
                }
            }
            // Usar proveedor de la primera factura (puede ser null para "Sin proveedor")
            $primera = $cuentas->first();
            $proveedorIdInit = $primera?->purchase?->proveedor_id;
        } else {
            $proveedorIdInit = old('proveedor_id');
        }

        return view('stores.comprobante-egreso-crear', compact('store', 'bolsillos', 'proveedores', 'cuentasSeleccionadasInit', 'proveedorIdInit'));
    }

    public function cuentasPorPagarProveedor(Request $request, Store $store, AccountPayableService $accountPayableService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.create');

        $proveedorId = $request->get('proveedor_id');
        if (! $proveedorId) {
            return response()->json([]);
        }

        $cuentas = $accountPayableService->listarCuentasPorPagar($store, [
            'proveedor_id' => (int) $proveedorId,
            'status' => 'pendientes',
            'per_page' => 100,
        ]);

        $data = collect($cuentas->items())->map(fn ($ap) => [
            'id' => $ap->id,
            'purchase_id' => $ap->purchase->id ?? null,
            'proveedor_nombre' => $ap->purchase->proveedor->nombre ?? '—',
            'total_amount' => (float) $ap->total_amount,
            'balance' => (float) $ap->balance,
            'due_date' => $ap->due_date?->format('Y-m-d'),
            'status' => $ap->status,
        ])->values()->all();

        return response()->json($data);
    }

    public function storeComprobanteEgreso(Store $store, Request $request, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.create');

        // Normalizar proveedor_id vacío a null (facturas con proveedor null)
        $input = $request->all();
        if (isset($input['proveedor_id']) && $input['proveedor_id'] === '') {
            $input['proveedor_id'] = null;
        }
        $request->merge($input);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'destinos' => ['required', 'array', 'min:1'],
            'destinos.*.amount' => ['required', 'numeric', 'min:0.01'],
            'destinos.*.account_payable_id' => ['nullable', 'exists:accounts_payables,id'],
            'destinos.*.concepto' => ['nullable', 'string', 'max:255'],
            'destinos.*.beneficiario' => ['nullable', 'string', 'max:255'],
            'origenes' => ['required', 'array', 'min:1'],
            'origenes.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'origenes.*.amount' => ['required', 'numeric', 'min:0.01'],
            'origenes.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $destinos = $request->input('destinos', []);
            foreach ($destinos as $i => $d) {
                $hasAccountPayable = ! empty($d['account_payable_id'] ?? null);
                $hasConcepto = ! empty(trim($d['concepto'] ?? ''));
                if (! $hasAccountPayable && ! $hasConcepto) {
                    $validator->errors()->add("destinos.{$i}.concepto", 'El concepto es requerido cuando no hay cuenta por pagar.');
                }
            }
        });

        $validator->validate();

        try {
            $comprobante = $comprobanteEgresoService->crearComprobante($store, Auth::id(), $request->all());
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobante])->with('success', 'Comprobante de egreso registrado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.view');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        $comprobante = $comprobanteEgresoService->obtener($store, $comprobanteEgreso->id);
        $bolsillos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.comprobante-egreso-detalle', compact('store', 'comprobante', 'bolsillos'));
    }

    public function editComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.edit');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }
        if ($comprobanteEgreso->isReversed()) {
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('error', 'No se puede editar un comprobante revertido.');
        }

        $comprobante = $comprobanteEgresoService->obtener($store, $comprobanteEgreso->id);

        return view('stores.comprobante-egreso-editar', compact('store', 'comprobante'));
    }

    public function updateComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, Request $request, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.edit');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $comprobanteEgresoService->actualizarComprobante($store, $comprobanteEgreso->id, $request->only(['payment_date', 'notes']));
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('success', 'Comprobante actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function reversarComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.reversar');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        try {
            $comprobanteEgresoService->reversar($store, $comprobanteEgreso->id, Auth::id());
            return redirect()->route('stores.comprobantes-egreso.index', $store)->with('success', 'Comprobante revertido correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function anularComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, Request $request, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'comprobantes-egreso.anular');
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }
        if ($comprobanteEgreso->isReversed()) {
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('error', 'Este comprobante ya fue anulado.');
        }

        $request->validate([
            'origenes' => ['required', 'array', 'min:1'],
            'origenes.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'origenes.*.amount' => ['required', 'numeric', 'min:0.01'],
            'origenes.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $comprobanteEgresoService->anularComprobante($store, $comprobanteEgreso->id, Auth::id(), $request->input('origenes'));
            return redirect()->route('stores.comprobantes-egreso.show', [$store, $comprobanteEgreso])
                ->with('success', 'Comprobante anulado correctamente. El dinero fue devuelto a los bolsillos indicados y las cuentas por pagar fueron restauradas.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}