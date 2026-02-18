<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Models\Store;
use App\Services\AttributeService;
use App\Services\CategoryService;
use App\Services\StorePermissionService;

class StoreCategoryController extends Controller
{
    public function index(Store $store, CategoryService $categoryService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'categories.view');

        $categoryTree = $categoryService->getCategoryTree($store);
        $categoriesFlat = $categoryService->getFlatList($store);

        return view('stores.categorias', compact('store', 'categoryTree', 'categoriesFlat'));
    }

    public function show(Store $store, Category $category, StorePermissionService $permission)
    {
        $permission->authorize($store, 'categories.view');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        $category->load(['attributes' => ['options']]);
        $products = $category->products()->orderBy('name')->get();

        return view('stores.category-show', compact('store', 'category', 'products'));
    }

    public function destroy(Store $store, Category $category, CategoryService $categoryService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'categories.destroy');

        $categoryService->deleteCategory($store, $category->id);

        return redirect()->route('stores.categories', $store)
            ->with('success', 'Categoría eliminada correctamente.');
    }

    public function attributes(Store $store, Category $category, AttributeService $attributeService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'category-attributes.assign');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        $storeAttributeGroups = $attributeService->getStoreAttributeGroups($store);
        $categoryAttributes = $category->attributes()->with(['options', 'groups'])->get();

        return view('stores.category-attributes', compact('store', 'category', 'storeAttributeGroups', 'categoryAttributes'));
    }

    public function assignAttributes(Store $store, Category $category, StoreCategoryRequest $request, AttributeService $attributeService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'category-attributes.assign');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        $attributeGroupIds = $request->input('attribute_group_ids', []) ?: [];
        $attributeGroupIds = array_values(array_filter(array_map('intval', $attributeGroupIds)));
        $validGroupIds = \App\Models\AttributeGroup::where('store_id', $store->id)
            ->whereIn('id', $attributeGroupIds)
            ->pluck('id')
            ->all();
        $attributeGroupIds = array_values(array_intersect($attributeGroupIds, $validGroupIds));

        $attributeService->assignGroupsToCategory($category, $attributeGroupIds);

        return redirect()->route('stores.category.attributes', [$store, $category])
            ->with('success', 'Grupos de atributos asignados correctamente.');
    }
}
