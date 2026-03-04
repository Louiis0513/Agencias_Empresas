<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\Session;

class VitrinaCartService
{
    private const SESSION_KEY_PREFIX = 'vitrina_cart_';

    public function getSessionKey(Store $store): string
    {
        return self::SESSION_KEY_PREFIX . $store->id;
    }

    /**
     * Genera la clave única de una línea del carrito.
     */
    public function lineKey(int $productId, ?int $variantId, ?int $productItemId): string
    {
        return $productId . '_' . ($variantId ?? 0) . '_' . ($productItemId ?? 0);
    }

    /**
     * Devuelve todas las líneas del carrito para la tienda.
     *
     * @return array<int, array{line_key: string, product_id: int, variant_id: int|null, product_item_id: int|null, name: string, price: float, quantity: int, image_path: string|null}>
     */
    public function getCartForStore(Store $store): array
    {
        $key = $this->getSessionKey($store);
        $raw = Session::get($key, []);

        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $lineKey => $row) {
            if (! is_array($row) || empty($row['product_id'])) {
                continue;
            }
            $items[] = [
                'line_key' => $lineKey,
                'product_id' => (int) $row['product_id'],
                'variant_id' => isset($row['variant_id']) ? (int) $row['variant_id'] : null,
                'product_item_id' => isset($row['product_item_id']) ? (int) $row['product_item_id'] : null,
                'name' => (string) ($row['name'] ?? ''),
                'price' => (float) ($row['price'] ?? 0),
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'image_path' => isset($row['image_path']) ? (string) $row['image_path'] : null,
            ];
        }

        return array_values($items);
    }

    /**
     * Resuelve nombre, precio e imagen para un producto/variante/ítem y lo añade al carrito.
     */
    public function addItem(Store $store, int $productId, ?int $variantId, ?int $productItemId, int $quantity = 1): void
    {
        $resolved = $this->resolveItem($store, $productId, $variantId, $productItemId);
        if ($resolved === null) {
            return;
        }

        $lineKey = $this->lineKey($productId, $variantId, $productItemId);
        $key = $this->getSessionKey($store);
        $cart = Session::get($key, []);
        if (! is_array($cart)) {
            $cart = [];
        }

        if (isset($cart[$lineKey])) {
            $cart[$lineKey]['quantity'] = (int) $cart[$lineKey]['quantity'] + $quantity;
        } else {
            $cart[$lineKey] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'product_item_id' => $productItemId,
                'name' => $resolved['name'],
                'price' => $resolved['price'],
                'quantity' => $quantity,
                'image_path' => $resolved['image_path'],
            ];
        }

        Session::put($key, $cart);
    }

    /**
     * Actualiza la cantidad de una línea (delta: +1 o -1). Si la cantidad resulta <= 0, se elimina la línea.
     */
    public function updateItemQuantity(Store $store, string $lineKey, int $delta): void
    {
        $key = $this->getSessionKey($store);
        $cart = Session::get($key, []);
        if (! is_array($cart) || ! isset($cart[$lineKey])) {
            return;
        }

        $newQty = (int) $cart[$lineKey]['quantity'] + $delta;
        if ($newQty <= 0) {
            unset($cart[$lineKey]);
        } else {
            $cart[$lineKey]['quantity'] = $newQty;
        }

        Session::put($key, $cart);
    }

    public function clearCart(Store $store): void
    {
        Session::forget($this->getSessionKey($store));
    }

    /**
     * @return array{subtotal: float, total: float, count: int}
     */
    public function getTotals(Store $store): array
    {
        $items = $this->getCartForStore($store);
        $subtotal = 0.0;
        $count = 0;
        foreach ($items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
            $count += $item['quantity'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal, 2),
            'count' => $count,
        ];
    }

    /**
     * Resuelve product_id + variant_id/product_item_id a nombre, precio e imagen.
     *
     * @return array{name: string, price: float, image_path: string|null}|null
     */
    private function resolveItem(Store $store, int $productId, ?int $variantId, ?int $productItemId): ?array
    {
        $product = Product::where('store_id', $store->id)
            ->where('id', $productId)
            ->with(['category.attributes', 'attributeValues.attribute', 'variants', 'productItems'])
            ->first();

        if (! $product) {
            return null;
        }

        if ($productItemId !== null && $product->isSerialized()) {
            $item = ProductItem::where('product_id', $product->id)
                ->where('id', $productItemId)
                ->where('in_showcase', true)
                ->where('status', ProductItem::STATUS_AVAILABLE)
                ->first();
            if (! $item) {
                return null;
            }
            $name = $product->name;
            $attrNames = $product->category && $product->category->attributes
                ? $product->category->attributes->pluck('name', 'id')->all()
                : [];
            $featuresStr = ! empty($item->features) && is_array($item->features)
                ? ProductVariant::formatFeaturesWithAttributeNames($item->features, $attrNames)
                : '';
            if ($featuresStr !== '') {
                $name .= ' (' . $featuresStr . ')';
            }
            $price = (float) ($item->price ?? $product->price ?? 0);
            $imagePath = $item->image_path ?: $product->image_path;

            return ['name' => $name, 'price' => $price, 'image_path' => $imagePath];
        }

        if ($variantId !== null && $product->isBatch()) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('id', $variantId)
                ->where('in_showcase', true)
                ->where('is_active', true)
                ->first();
            if (! $variant) {
                return null;
            }
            $name = $product->name . ' (' . $variant->display_name . ')';
            $price = (float) $variant->selling_price;
            $imagePath = $variant->image_path ?: $product->image_path;

            return ['name' => $name, 'price' => $price, 'image_path' => $imagePath];
        }

        // Producto simple
        if (! $product->isBatch() && ! $product->isSerialized() && $product->in_showcase) {
            $name = $product->name;
            if ($product->attributeValues && $product->attributeValues->isNotEmpty()) {
                $attrs = $product->attributeValues
                    ->filter(fn ($av) => $av->attribute && $av->value !== null && $av->value !== '')
                    ->map(fn ($av) => $av->attribute->name . ': ' . $av->value)
                    ->values()
                    ->all();
                if (! empty($attrs)) {
                    $name .= ' (' . implode(', ', $attrs) . ')';
                }
            }
            $price = (float) ($product->price ?? 0);
            $imagePath = $product->image_path;

            return ['name' => $name, 'price' => $price, 'image_path' => $imagePath];
        }

        return null;
    }
}
