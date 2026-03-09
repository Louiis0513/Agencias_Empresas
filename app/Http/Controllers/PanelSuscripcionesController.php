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
     * Vista pública del Panel de Suscripciones.
     * view=plans (default) | view=cart | view=panel (solo autenticados).
     * Filtros en planes: name, limit_type, duration.
     */
    public function show(Request $request, string $slug, SubscriptionService $subscriptionService): View|RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $requestedView = $request->get('view', 'plans');
        if ($requestedView === 'panel') {
            if (auth()->guest()) {
                return redirect()->route('panel_suscripciones.show', ['slug' => $slug])
                    ->with('error', 'Inicia sesión para ver tu panel.');
            }
        }

        $currentView = in_array($requestedView, ['plans', 'cart', 'panel'], true) ? $requestedView : 'plans';

        $baseQuery = $store->storePlans()->where('in_showcase', true);

        if ($request->filled('name')) {
            $baseQuery->where('name', 'like', '%' . $request->input('name') . '%');
        }
        $limitType = $request->get('limit_type');
        if ($limitType === 'unlimited') {
            $baseQuery->whereNull('total_entries_limit');
        } elseif ($limitType === 'limited') {
            $baseQuery->whereNotNull('total_entries_limit');
        }
        if ($request->filled('duration') && is_numeric($request->input('duration'))) {
            $baseQuery->where('duration_days', (int) $request->input('duration'));
        }

        $plans = $baseQuery->orderBy('name')->get();

        $durations = $store->storePlans()
            ->where('in_showcase', true)
            ->distinct()
            ->orderBy('duration_days')
            ->pluck('duration_days');

        $vitrinaSlug = $store->vitrinaConfig?->slug;

        $cartPlans = $this->plansCartService->getCartForStore($store);
        $totals = $this->plansCartService->getTotals($store);

        $customer = null;
        $activeSubscription = null;
        $panelStatusLabel = null;
        $panelSubscriptionEndDate = null;
        $panelAttendancesCount = null;
        $panelDaysLeft = null;
        $panelPlanName = null;
        $panelTotalEntriesLimit = null;

        if ($currentView === 'panel' && auth()->check()) {
            $customer = Customer::where('store_id', $store->id)->where('user_id', auth()->id())->first();
            $activeSubscription = $customer
                ? $subscriptionService->getActiveSubscriptionForCustomer($store, $customer->id)
                : null;
            if ($activeSubscription) {
                $activeSubscription->load('storePlan');
                $panelStatusLabel = ($activeSubscription->isActive() && $activeSubscription->hasEntriesRemaining())
                    ? 'Activo'
                    : 'Inactivo';
                $panelSubscriptionEndDate = $activeSubscription->expires_at;
                $panelAttendancesCount = $activeSubscription->entries_used;
                $now = Carbon::now();
                $panelDaysLeft = max(0, (int) $now->diffInDays($activeSubscription->expires_at, false));
                $panelPlanName = $activeSubscription->storePlan->name ?? null;
                $panelTotalEntriesLimit = $activeSubscription->storePlan->total_entries_limit;
            }
        }

        return view('panel_suscripciones.show', [
            'config' => $config,
            'store' => $store,
            'plans' => $plans,
            'durations' => $durations,
            'filterName' => $request->get('name', ''),
            'filterLimitType' => $request->get('limit_type', ''),
            'filterDuration' => $request->get('duration', ''),
            'vitrinaSlug' => $vitrinaSlug,
            'cartPlans' => $cartPlans,
            'cartSubtotal' => $totals['subtotal'],
            'cartTotal' => $totals['total'],
            'cartCount' => $totals['count'],
            'currentView' => $currentView,
            'customer' => $customer,
            'activeSubscription' => $activeSubscription,
            'panelStatusLabel' => $panelStatusLabel,
            'panelSubscriptionEndDate' => $panelSubscriptionEndDate,
            'panelAttendancesCount' => $panelAttendancesCount,
            'panelDaysLeft' => $panelDaysLeft,
            'panelPlanName' => $panelPlanName,
            'panelTotalEntriesLimit' => $panelTotalEntriesLimit,
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
