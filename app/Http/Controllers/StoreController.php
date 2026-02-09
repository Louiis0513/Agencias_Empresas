<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Category;
use App\Services\CategoryService;
use App\Services\AttributeService;
use App\Services\ProductService;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\ProveedorService;
use App\Services\CajaService;
use App\Services\ActivoService;
use App\Services\InventarioService;
use App\Services\PurchaseService;
use App\Services\AccountPayableService;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StoreProveedorRequest;
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

    public function workers(Store $store)
    {
        // 1. SEGURIDAD: Verificar si el usuario autenticado pertenece a esta tienda
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        // 2. Guardamos en sesión la tienda actual
        session(['current_store_id' => $store->id]);

        // 3. Obtenemos todos los roles de la tienda para evitar consultas N+1
        $roles = \App\Models\Role::where('store_id', $store->id)
            ->get()
            ->keyBy('id');

        // 4. Obtenemos los trabajadores de la tienda con sus roles
        $workers = $store->workers()
            ->get()
            ->map(function ($worker) use ($roles) {
                // Obtenemos el rol del pivot
                $roleId = $worker->pivot->role_id;
                $role = $roleId && isset($roles[$roleId]) ? $roles[$roleId] : null;
                
                return [
                    'id' => $worker->id,
                    'name' => $worker->name,
                    'email' => $worker->email,
                    'role' => $role ? $role->name : 'Dueño',
                    'role_id' => $roleId,
                ];
            });

        // 5. Retornamos la vista con los trabajadores
        return view('stores.workers', compact('store', 'workers'));
    }

    public function roles(Store $store)
    {
        // 1. SEGURIDAD: Verificar si el usuario autenticado pertenece a esta tienda
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        // 2. Guardamos en sesión la tienda actual
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

    public function products(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        $products = $store->products()
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('stores.productos', compact('store', 'products'));
    }

    public function showProduct(Store $store, \App\Models\Product $product)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($product->store_id !== $store->id) {
            abort(404);
        }
        session(['current_store_id' => $store->id]);

        $product->load(['category.attributes.options', 'productItems', 'batches.batchItems', 'allowedVariantOptions']);

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

    public function destroyProduct(Store $store, \App\Models\Product $product, ProductService $productService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function categories(Store $store, CategoryService $categoryService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        // Obtenemos el árbol de categorías (raíces con hijos)
        $categoryTree = $categoryService->getCategoryTree($store);
        
        // También obtenemos lista plana para dropdowns
        $categoriesFlat = $categoryService->getFlatList($store);

        return view('stores.categorias', compact('store', 'categoryTree', 'categoriesFlat'));
    }

    public function destroyCategory(Store $store, \App\Models\Category $category, CategoryService $categoryService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        try {
            $categoryService->deleteCategory($store, $category->id);
            return redirect()->route('stores.categories', $store)
                ->with('success', 'Categoría eliminada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.categories', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function attributeGroups(Store $store, AttributeService $attributeService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        $groups = $attributeService->getStoreAttributeGroups($store);

        return view('stores.attribute-groups', compact('store', 'groups'));
    }

    public function categoryAttributes(Store $store, Category $category, AttributeService $attributeService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        session(['current_store_id' => $store->id]);

        $storeAttributeGroups = $attributeService->getStoreAttributeGroups($store);
        $categoryAttributes = $category->attributes()->with(['options', 'groups'])->get();

        return view('stores.category-attributes', compact('store', 'category', 'storeAttributeGroups', 'categoryAttributes'));
    }

    public function assignAttributes(Store $store, Category $category, Request $request, AttributeService $attributeService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function destroyAttributeGroup(Store $store, \App\Models\AttributeGroup $attributeGroup, AttributeService $attributeService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    // ==================== FACTURAS ====================

    public function invoices(Store $store, InvoiceService $invoiceService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function showInvoice(Store $store, \App\Models\Invoice $invoice, InvoiceService $invoiceService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        if ($invoice->store_id !== $store->id) {
            abort(404);
        }

        $invoice = $invoiceService->obtenerFactura($store, $invoice->id);

        return view('stores.factura-detalle', compact('store', 'invoice'));
    }

    public function storeInvoice(Store $store, StoreInvoiceRequest $request, InvoiceService $invoiceService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        try {
            $invoice = $invoiceService->crearFactura($store, Auth::id(), $request->validated());
            return redirect()->route('stores.invoices', $store)
                ->with('success', 'Factura creada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.invoices', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function voidInvoice(Store $store, \App\Models\Invoice $invoice, InvoiceService $invoiceService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function proveedores(Store $store, ProveedorService $proveedorService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
        ];

        $proveedores = $proveedorService->listarProveedores($store, $filtros);

        return view('stores.proveedores', compact('store', 'proveedores'));
    }

    public function storeProveedor(Store $store, StoreProveedorRequest $request, ProveedorService $proveedorService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function updateProveedor(Store $store, \App\Models\Proveedor $proveedor, StoreProveedorRequest $request, ProveedorService $proveedorService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function destroyProveedor(Store $store, \App\Models\Proveedor $proveedor, ProveedorService $proveedorService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function customers(Store $store, CustomerService $customerService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
        ];

        $customers = $customerService->getStoreCustomers($store, $filtros);

        return view('stores.clientes', compact('store', 'customers'));
    }

    public function storeCustomer(Store $store, StoreCustomerRequest $request, CustomerService $customerService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        try {
            $customer = $customerService->createCustomer($store, $request->validated());
            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.customers', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function updateCustomer(Store $store, \App\Models\Customer $customer, StoreCustomerRequest $request, CustomerService $customerService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function destroyCustomer(Store $store, \App\Models\Customer $customer, CustomerService $customerService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function caja(Store $store, CajaService $cajaService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function showBolsillo(Store $store, \App\Models\Bolsillo $bolsillo, CajaService $cajaService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function storeBolsillo(Store $store, Request $request, CajaService $cajaService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function updateBolsillo(Store $store, \App\Models\Bolsillo $bolsillo, Request $request, CajaService $cajaService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function destroyBolsillo(Store $store, \App\Models\Bolsillo $bolsillo, CajaService $cajaService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function storeMovimiento(Store $store, Request $request, CajaService $cajaService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $request->validate([
            'bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'type' => ['required', 'in:INCOME,EXPENSE'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $bolsillo = $cajaService->obtenerBolsillo($store, (int) $request->input('bolsillo_id'));
            $cajaService->registrarMovimiento($store, Auth::id(), [
                'bolsillo_id' => $bolsillo->id,
                'type' => $request->input('type'),
                'amount' => (float) $request->input('amount'),
                'description' => $request->input('description'),
            ]);
            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with('success', 'Movimiento registrado correctamente.');
        } catch (\Exception $e) {
            $bolsillo = \App\Models\Bolsillo::find($request->input('bolsillo_id'));
            $redirect = $bolsillo && $bolsillo->store_id === $store->id
                ? route('stores.cajas.bolsillos.show', [$store, $bolsillo])
                : route('stores.cajas', $store);
            return redirect()->to($redirect)->with('error', $e->getMessage());
        }
    }

    public function destroyMovimiento(Store $store, \App\Models\MovimientoBolsillo $movimiento, CajaService $cajaService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($movimiento->store_id !== $store->id) {
            abort(404);
        }
        $bolsillo = $movimiento->bolsillo;
        try {
            $cajaService->eliminarMovimiento($movimiento);
            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with('success', 'Movimiento eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.cajas.bolsillos.show', [$store, $bolsillo])->with('error', $e->getMessage());
        }
    }

    // ==================== INVENTARIO (movimientos entrada/salida productos) ====================

    public function inventario(Store $store, InventarioService $inventarioService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function storeMovimientoInventario(Store $store, Request $request, InventarioService $inventarioService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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
    public function productPurchases(Store $store, PurchaseService $purchaseService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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
    public function createProductPurchase(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $proveedores = \App\Models\Proveedor::deTienda($store->id)->orderBy('nombre')->get();

        return view('stores.compra-productos-crear', compact('store', 'proveedores'));
    }

    /**
     * Guarda la compra de productos como borrador.
     * Normaliza filas con serial_items (cantidad = nº unidades, costo = promedio por unidad).
     */
    public function storeProductPurchase(Store $store, Request $request, PurchaseService $purchaseService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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
        $normalized = $this->normalizeProductPurchaseDetails($rawDetails);

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
     * Filas con serial_items: quantity = número de unidades, unit_cost = subtotal/quantity.
     * Filas con batch_items: quantity = suma de cantidades, unit_cost = promedio ponderado, se guarda batch_items.
     */
    protected function normalizeProductPurchaseDetails(array $rawDetails): array
    {
        $normalized = [];
        foreach ($rawDetails as $d) {
            $serialItems = $d['serial_items'] ?? null;
            if (is_array($serialItems) && count($serialItems) > 0) {
                $subtotal = 0;
                $itemsForStorage = [];
                foreach ($serialItems as $unit) {
                    $cost = (float) ($unit['cost'] ?? 0);
                    $subtotal += $cost;
                    $itemsForStorage[] = [
                        'serial_number' => trim($unit['serial_number'] ?? ''),
                        'cost' => $cost,
                        'features' => $unit['features'] ?? null,
                    ];
                }
                $qty = count($serialItems);
                $normalized[] = [
                    'item_type' => 'INVENTARIO',
                    'product_id' => $d['product_id'] ?? null,
                    'activo_id' => null,
                    'description' => $d['description'] ?? '',
                    'quantity' => $qty,
                    'unit_cost' => $qty > 0 ? round($subtotal / $qty, 2) : 0,
                    'serial_items' => $itemsForStorage,
                ];
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
                    $features = $item['features'] ?? null;
                    if (is_array($features)) {
                        $features = array_filter($features, fn ($v) => $v !== '' && $v !== null);
                    }
                    $itemsForStorage[] = [
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'price' => isset($item['price']) && $item['price'] !== '' ? (float) $item['price'] : null,
                        'features' => ! empty($features) ? $features : null,
                        'expiration_date' => isset($item['expiration_date']) && $item['expiration_date'] !== '' ? $item['expiration_date'] : null,
                    ];
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
            return redirect()->route('stores.purchases.show', [$store, $purchase])
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
            $normalized = $this->normalizeProductPurchaseDetails($rawDetails);

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
                return redirect()->route('stores.purchases.show', [$store, $purchase])->with('success', 'Compra actualizada correctamente.');
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
            return redirect()->route('stores.purchases.show', [$store, $purchase])->with('success', 'Compra actualizada correctamente.');
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
            return redirect()->route('stores.purchases.show', [$store, $purchase])->with('success', 'Compra aprobada. Inventario actualizado.');
        } catch (\Exception $e) {
            return redirect()->route('stores.purchases.show', [$store, $purchase])->with('error', $e->getMessage());
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
            return redirect()->route('stores.purchases', $store)->with('success', 'Compra anulada.');
        } catch (\Exception $e) {
            return redirect()->route('stores.purchases', $store)->with('error', $e->getMessage());
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

    public function comprobantesEgreso(Store $store, \App\Services\ComprobanteEgresoService $comprobanteEgresoService, Request $request)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        session(['current_store_id' => $store->id]);

        $filtros = [
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];
        $comprobantes = $comprobanteEgresoService->listar($store, $filtros);

        return view('stores.comprobantes-egreso', compact('store', 'comprobantes'));
    }

    public function createComprobanteEgreso(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function cuentasPorPagarProveedor(Request $request, Store $store, AccountPayableService $accountPayableService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function storeComprobanteEgreso(Store $store, Request $request, \App\Services\ComprobanteEgresoService $comprobanteEgresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

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

    public function showComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, \App\Services\ComprobanteEgresoService $comprobanteEgresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        if ($comprobanteEgreso->store_id !== $store->id) {
            abort(404);
        }

        $comprobante = $comprobanteEgresoService->obtener($store, $comprobanteEgreso->id);
        $bolsillos = \App\Models\Bolsillo::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.comprobante-egreso-detalle', compact('store', 'comprobante', 'bolsillos'));
    }

    public function editComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, \App\Services\ComprobanteEgresoService $comprobanteEgresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function updateComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, Request $request, \App\Services\ComprobanteEgresoService $comprobanteEgresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function reversarComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, \App\Services\ComprobanteEgresoService $comprobanteEgresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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

    public function anularComprobanteEgreso(Store $store, \App\Models\ComprobanteEgreso $comprobanteEgreso, Request $request, \App\Services\ComprobanteEgresoService $comprobanteEgresoService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
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