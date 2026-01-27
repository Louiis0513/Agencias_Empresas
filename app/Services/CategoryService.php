<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Exception;

class CategoryService
{
    /**
     * Obtener el árbol de categorías (solo raíces con sus hijos).
     * Útil para mostrar en vista de árbol o menú.
     */
    public function getCategoryTree(Store $store)
    {
        return Category::where('store_id', $store->id)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->with('products');
            }, 'products'])
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    /**
     * Lista plana de todas las categorías de la tienda.
     * Útil para dropdowns.
     */
    public function getFlatList(Store $store)
    {
        return Category::where('store_id', $store->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Crear una nueva categoría.
     * Valida que parent_id (si existe) pertenezca a la misma tienda.
     */
    public function createCategory(Store $store, array $data): Category
    {
        return DB::transaction(function () use ($store, $data) {
            // Validar parent_id si existe
            if (isset($data['parent_id']) && $data['parent_id']) {
                $parent = Category::where('id', $data['parent_id'])
                    ->where('store_id', $store->id)
                    ->firstOrFail();
            }

            return Category::create([
                'store_id' => $store->id,
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
            ]);
        });
    }

    /**
     * Actualizar una categoría.
     * Valida que no se mueva a sí misma como padre (evitar loops).
     */
    public function updateCategory(Store $store, int $categoryId, array $data): Category
    {
        $category = Category::where('id', $categoryId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // Validar que no se asigne a sí misma como padre
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            throw new Exception('Una categoría no puede ser su propio padre.');
        }

        // Validar que el nuevo parent_id (si existe) pertenezca a la misma tienda
        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = Category::where('id', $data['parent_id'])
                ->where('store_id', $store->id)
                ->firstOrFail();

            // Validar que no se mueva a un hijo (evitar loops)
            if ($this->isDescendant($category, $data['parent_id'])) {
                throw new Exception('No puedes mover una categoría dentro de sus propias subcategorías.');
            }
        }

        $category->update([
            'name' => $data['name'] ?? $category->name,
            'parent_id' => $data['parent_id'] ?? $category->parent_id,
        ]);

        return $category->fresh();
    }

    /**
     * Eliminar una categoría con validaciones defensivas.
     * No permite eliminar si tiene hijos o productos.
     */
    public function deleteCategory(Store $store, int $categoryId): bool
    {
        $category = Category::where('id', $categoryId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // Validación 1: No eliminar si tiene hijos (subcategorías)
        if ($category->children()->exists()) {
            throw new Exception('No puedes eliminar esta categoría porque tiene subcategorías. Elimínalas o muévelas primero.');
        }

        // Validación 2: No eliminar si tiene productos
        if ($category->products()->exists()) {
            throw new Exception('No puedes eliminar esta categoría porque contiene productos. Mueve los productos a otra categoría antes de eliminar.');
        }

        $category->delete();

        return true;
    }

    /**
     * Helper: Verificar si una categoría es descendiente de otra.
     * Usado para evitar loops al mover categorías.
     */
    protected function isDescendant(Category $category, int $potentialParentId): bool
    {
        $current = $category->parent;
        
        while ($current) {
            if ($current->id == $potentialParentId) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }
}
