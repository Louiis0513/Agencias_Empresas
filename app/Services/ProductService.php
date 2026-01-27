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
            unset($data['attribute_values']);

            $product = Product::create($data);

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
}
