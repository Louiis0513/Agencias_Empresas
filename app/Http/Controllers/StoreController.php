<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Category;
use App\Services\CategoryService;
use App\Services\AttributeService;
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
            ->with(['category', 'attributeValues.attribute'])
            ->orderBy('name')
            ->get();

        return view('stores.productos', compact('store', 'products'));
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
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'exists:attributes,id',
        ]);

        try {
            $attributeIds = $request->input('attribute_ids', []) ?: [];
            $positions = $request->input('positions', []);
            $requiredFlags = $request->input('required', []);

            $attributeService->assignAttributesToCategory($category, $attributeIds, $positions, $requiredFlags ?? []);

            return redirect()->route('stores.category.attributes', [$store, $category])
                ->with('success', 'Atributos asignados correctamente.');
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
}