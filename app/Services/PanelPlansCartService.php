<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StorePlan;
use Illuminate\Support\Facades\Session;

class PanelPlansCartService
{
    private const SESSION_KEY_PREFIX = 'panel_plans_cart_';

    public function getSessionKey(Store $store): string
    {
        return self::SESSION_KEY_PREFIX . $store->id;
    }

    /**
     * Clave única por plan en el carrito.
     */
    public function lineKey(int $storePlanId): string
    {
        return 'plan_' . $storePlanId;
    }

    /**
     * Devuelve todas las líneas del carrito de planes para la tienda.
     *
     * @return array<int, array{line_key: string, store_plan_id: int, name: string, price: float, quantity: int, image_path: string|null}>
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
            if (! is_array($row) || empty($row['store_plan_id'])) {
                continue;
            }
            $items[] = [
                'line_key' => $lineKey,
                'store_plan_id' => (int) $row['store_plan_id'],
                'name' => (string) ($row['name'] ?? ''),
                'price' => (float) ($row['price'] ?? 0),
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'image_path' => isset($row['image_path']) ? (string) $row['image_path'] : null,
            ];
        }

        return array_values($items);
    }

    /**
     * Añade un plan al carrito (o incrementa cantidad).
     */
    public function addPlan(Store $store, int $storePlanId, int $quantity = 1): void
    {
        $plan = StorePlan::where('store_id', $store->id)
            ->where('id', $storePlanId)
            ->where('in_showcase', true)
            ->first();

        if (! $plan) {
            return;
        }

        $lineKey = $this->lineKey($storePlanId);
        $key = $this->getSessionKey($store);
        $cart = Session::get($key, []);
        if (! is_array($cart)) {
            $cart = [];
        }

        if (isset($cart[$lineKey])) {
            $cart[$lineKey]['quantity'] = (int) $cart[$lineKey]['quantity'] + $quantity;
        } else {
            $cart[$lineKey] = [
                'store_plan_id' => $storePlanId,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'quantity' => $quantity,
                'image_path' => $plan->image_path,
            ];
        }

        Session::put($key, $cart);
    }

    /**
     * Actualiza la cantidad de una línea. Si quantity <= 0, elimina la línea.
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

        $currency = $store->currency ?? 'COP';
        $currencyService = app(\App\Services\CurrencyFormatService::class);
        $rounded = $currencyService->roundForCurrency($subtotal, $currency);

        return [
            'subtotal' => $rounded,
            'total' => $rounded,
            'count' => $count,
        ];
    }
}
