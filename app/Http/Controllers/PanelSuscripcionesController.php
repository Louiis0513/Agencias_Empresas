<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PanelSuscripcionesConfig;
use App\Services\PanelPlansCartService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelSuscripcionesController extends Controller
{
    public function __construct(
        private PanelPlansCartService $plansCartService
    ) {}

    /**
     * Vista pública del Panel de Suscripciones. Acepta view=cart para mostrar carrito de planes.
     */
    public function show(Request $request, string $slug): View
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;
        $plans = $store->storePlans()->where('in_showcase', true)->orderBy('name')->get();
        $vitrinaSlug = $store->vitrinaConfig?->slug;

        $cartPlans = $this->plansCartService->getCartForStore($store);
        $totals = $this->plansCartService->getTotals($store);
        $currentView = $request->get('view', 'plans') === 'cart' ? 'cart' : 'plans';

        return view('panel_suscripciones.show', [
            'config' => $config,
            'store' => $store,
            'plans' => $plans,
            'vitrinaSlug' => $vitrinaSlug,
            'cartPlans' => $cartPlans,
            'cartSubtotal' => $totals['subtotal'],
            'cartTotal' => $totals['total'],
            'cartCount' => $totals['count'],
            'currentView' => $currentView,
        ]);
    }

    public function addToCart(Request $request, string $slug): RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $planId = (int) $request->input('plan_id');
        $quantity = max(1, (int) $request->input('quantity', 1));

        $plan = $store->storePlans()->where('id', $planId)->where('in_showcase', true)->first();
        if (! $plan) {
            return redirect()->route('panel_suscripciones.show', ['slug' => $slug])
                ->with('error', 'Plan no encontrado o no disponible.');
        }

        $this->plansCartService->addPlan($store, $planId, $quantity);

        return redirect()->route('panel_suscripciones.show', ['slug' => $slug])
            ->with('success', 'Plan añadido al carrito.');
    }

    public function updateCart(Request $request, string $slug): RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $lineKey = (string) $request->input('line_key');
        $delta = (int) $request->input('delta', 0);

        if ($lineKey !== '' && $delta !== 0) {
            $this->plansCartService->updateItemQuantity($store, $lineKey, $delta);
        }

        return redirect()->route('panel_suscripciones.show', ['slug' => $slug, 'view' => 'cart'])
            ->with('success', 'Carrito actualizado.');
    }

    public function clearCart(Request $request, string $slug): RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $this->plansCartService->clearCart($store);

        return redirect()->route('panel_suscripciones.show', ['slug' => $slug, 'view' => 'cart'])
            ->with('success', 'Carrito limpio.');
    }

    public function checkoutCart(Request $request, string $slug, SubscriptionService $subscriptionService): RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $cartPlans = $this->plansCartService->getCartForStore($store);
        if (empty($cartPlans)) {
            return redirect()->route('panel_suscripciones.show', ['slug' => $slug, 'view' => 'cart'])
                ->with('error', 'Añade planes al carrito antes de solicitar.');
        }

        if (! $request->has('nota')) {
            return redirect()->route('panel_suscripciones.show', ['slug' => $slug, 'view' => 'cart'])
                ->with('show_checkout_modal', true);
        }

        if (auth()->guest()) {
            return redirect()->route('panel_suscripciones.show', ['slug' => $slug, 'view' => 'cart'])
                ->with('show_checkout_modal', true)
                ->with('auth_form', 'login')
                ->with('error', 'Debes iniciar sesión o registrarte para contratar los planes.');
        }

        $customer = Customer::where('store_id', $store->id)->where('user_id', auth()->id())->first();
        if (! $customer) {
            return redirect()->route('panel_suscripciones.show', ['slug' => $slug, 'view' => 'cart'])
                ->with('error', 'No se encontró tu perfil de cliente. Contacta al negocio.');
        }

        $startsAt = Carbon::now();
        $errors = [];

        foreach ($cartPlans as $item) {
            $planId = (int) $item['store_plan_id'];
            $qty = max(1, (int) $item['quantity']);
            for ($i = 0; $i < $qty; $i++) {
                try {
                    $subscriptionService->createSubscription($store, $customer->id, $planId, $startsAt->copy());
                } catch (\InvalidArgumentException $e) {
                    $errors[] = $item['name'] . ': ' . $e->getMessage();
                }
            }
        }

        $this->plansCartService->clearCart($store);

        $redirect = redirect()->route('panel_suscripciones.show', ['slug' => $slug])
            ->with('success', 'Solicitud procesada. Te contactaremos a la brevedad.');
        if (! empty($errors)) {
            $redirect->with('error', implode(' ', $errors));
        }
        return $redirect;
    }
}
