<?php

namespace App\Http\Controllers;

use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\VitrinaConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class VitrinaController extends Controller
{
    /**
     * Vitrina pública por slug (sin autenticación).
     */
    public function show(string $slug)
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $catalogItems = collect();

        if ($config->show_products) {
            $products = $store->products()
                ->where('is_active', true)
                ->with([
                    'attributeValues.attribute',
                    'category.attributes',
                    'variants' => function ($q) {
                        $q->where('in_showcase', true)->where('is_active', true);
                    },
                    'productItems' => function ($q) {
                        $q->where('in_showcase', true)->where('status', ProductItem::STATUS_AVAILABLE);
                    },
                ])
                ->orderBy('name')
                ->get();

            foreach ($products as $product) {
                // Producto simple (sin variantes): usa flag in_showcase del propio producto
                if (! $product->isBatch() && ! $product->isSerialized() && $product->in_showcase) {
                    $displayName = $product->name;
                    if ($product->attributeValues && $product->attributeValues->isNotEmpty()) {
                        $attrs = $product->attributeValues
                            ->filter(fn ($av) => $av->attribute && $av->value !== null && $av->value !== '')
                            ->map(fn ($av) => $av->attribute->name . ': ' . $av->value)
                            ->values()
                            ->all();
                        if (! empty($attrs)) {
                            $displayName .= ' (' . implode(', ', $attrs) . ')';
                        }
                    }

                    $catalogItems->push((object) [
                        'display_name' => $displayName,
                        'price' => (float) ($product->price ?? 0),
                        'image_path' => $product->image_path,
                    ]);
                }

                // Producto por lotes: cada variante en vitrina se muestra como ítem
                if ($product->isBatch() && $product->relationLoaded('variants')) {
                    foreach ($product->variants as $variant) {
                        if (! $variant->in_showcase) {
                            continue;
                        }

                        $displayName = $product->name . ' (' . $variant->display_name . ')';

                        $catalogItems->push((object) [
                            'display_name' => $displayName,
                            'price' => $variant->selling_price,
                            'image_path' => $variant->image_path ?: $product->image_path,
                        ]);
                    }
                }

                // Producto serializado: cada unidad marcada para vitrina se muestra como ítem
                if ($product->isSerialized() && $product->relationLoaded('productItems')) {
                    $attrNames = $product->category && $product->category->attributes
                        ? $product->category->attributes->pluck('name', 'id')->all()
                        : [];

                    foreach ($product->productItems as $item) {
                        if (! $item->in_showcase) {
                            continue;
                        }

                        $displayName = $product->name;
                        $featuresStr = ! empty($item->features) && is_array($item->features)
                            ? ProductVariant::formatFeaturesWithAttributeNames($item->features, $attrNames)
                            : '';

                        if ($featuresStr !== '') {
                            $displayName .= ' (' . $featuresStr . ')';
                        }

                        $catalogItems->push((object) [
                            'display_name' => $displayName,
                            'price' => (float) ($item->price ?? $product->price ?? 0),
                            'image_path' => $item->image_path ?: $product->image_path,
                        ]);
                    }
                }
            }

            // Ordenar por nombre para una presentación consistente
            $catalogItems = $catalogItems->sortBy('display_name')->values();
        }

        $plans = $config->show_plans
            ? $store->storePlans()->where('in_showcase', true)->orderBy('name')->get()
            : collect();

        return view('vitrina.show', [
            'config' => $config,
            'store' => $store,
            'catalogItems' => $catalogItems,
            'plans' => $plans,
        ]);
    }
}
