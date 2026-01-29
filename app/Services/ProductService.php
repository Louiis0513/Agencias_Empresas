<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductService
{
    /**
     * Crea un nuevo producto en la tienda.
     * - Categoría obligatoria.
     * - La categoría debe tener al menos un atributo asignado.
     * - Se guardan los valores de atributos (attribute_values) del producto.
     */
    public function createProduct(Store $store, array $data): Product
    {
        return DB::transaction(function () use ($store, $data) {
            $categoryId = $data['category_id'] ?? null;
            if (! $categoryId) {
                throw new Exception('Debes seleccionar una categoría para el producto.');
            }

            $category = Category::where('id', $categoryId)
                ->where('store_id', $store->id)
                ->with('attributes')
                ->firstOrFail();

            if ($category->attributes->isEmpty()) {
                throw new Exception("La categoría «{$category->name}» no tiene atributos. Asigna atributos a la categoría antes de crear productos.");
            }

            $data['store_id'] = $store->id;
            $attributeValues = $data['attribute_values'] ?? [];
            $proveedorIds = $data['proveedor_ids'] ?? null;
            unset($data['attribute_values'], $data['proveedor_ids']);

            $product = Product::create($data);

            if ($proveedorIds !== null) {
                $this->syncProveedores($product, $store, $proveedorIds);
            }

            foreach ($attributeValues as $attributeId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                ProductAttributeValue::create([
                    'product_id' => $product->id,
                    'attribute_id' => $attributeId,
                    'value' => (string) $value,
                ]);
            }

            return $product->load('attributeValues.attribute');
        });
    }

    /**
     * Actualiza un producto existente en la tienda.
     * - Valida que el producto pertenezca a la tienda.
     * - Actualiza los valores de atributos del producto.
     */
    public function updateProduct(Store $store, int $productId, array $data): Product
    {
        return DB::transaction(function () use ($store, $productId, $data) {
            $product = Product::where('id', $productId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $categoryId = $data['category_id'] ?? $product->category_id;
            
            if ($categoryId) {
                $category = Category::where('id', $categoryId)
                    ->where('store_id', $store->id)
                    ->with('attributes')
                    ->firstOrFail();

                if ($category->attributes->isEmpty()) {
                    throw new Exception("La categoría «{$category->name}» no tiene atributos. Asigna atributos a la categoría antes de actualizar el producto.");
                }
            }

            $attributeValues = $data['attribute_values'] ?? [];
            $proveedorIds = $data['proveedor_ids'] ?? null;
            unset($data['attribute_values'], $data['proveedor_ids']);

            // Actualizar los campos del producto
            $product->update($data);

            if ($proveedorIds !== null) {
                $this->syncProveedores($product, $store, $proveedorIds);
            }

            // Eliminar valores de atributos existentes
            $product->attributeValues()->delete();

            // Crear nuevos valores de atributos
            foreach ($attributeValues as $attributeId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                ProductAttributeValue::create([
                    'product_id' => $product->id,
                    'attribute_id' => $attributeId,
                    'value' => (string) $value,
                ]);
            }

            return $product->fresh()->load('attributeValues.attribute');
        });
    }

    /**
     * Elimina un producto de la tienda.
     * - Valida que el producto pertenezca a la tienda.
     * - Elimina también los valores de atributos asociados (cascade).
     */
    public function deleteProduct(Store $store, int $productId): bool
    {
        return DB::transaction(function () use ($store, $productId) {
            $product = Product::where('id', $productId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            // Los valores de atributos se eliminan automáticamente por cascade
            $product->delete();

            return true;
        });
    }

    /**
     * Busca productos de la tienda por término (nombre, SKU, código de barras).
     */
    public function buscarProductos(Store $store, string $termino, array $excluirIds = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Product::where('store_id', $store->id)
            ->where(function ($q) use ($termino) {
                $q->where('id', $termino)
                    ->orWhere('name', 'like', "%{$termino}%")
                    ->orWhere('sku', 'like', "%{$termino}%")
                    ->orWhere('barcode', 'like', "%{$termino}%");
            });

        if (! empty($excluirIds)) {
            $query->whereNotIn('id', $excluirIds);
        }

        return $query->orderBy('name')->limit(20)->get();
    }

    /**
     * Sincroniza los proveedores de un producto (solo proveedores de la misma tienda).
     */
    protected function syncProveedores(Product $product, Store $store, array $proveedorIds): void
    {
        $validIds = \App\Models\Proveedor::where('store_id', $store->id)
            ->whereIn('id', $proveedorIds)
            ->pluck('id')
            ->toArray();

        $product->proveedores()->sync($validIds);
    }
}
