<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StorePlan;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class StoreSubscriptionController extends Controller
{
    public function memberships(Store $store, StorePermissionService $permission, SubscriptionService $subscriptionService)
    {
        $permission->authorize($store, 'subscriptions.view');

        $subscriptions = $subscriptionService->getSubscriptionHistoryForStore($store);

        return view('stores.subscriptions.membresias', compact('store', 'subscriptions'));
    }

    public function plans(Store $store, StorePermissionService $permission, SubscriptionService $subscriptionService)
    {
        $permission->authorize($store, 'subscriptions.view');

        $plans = $subscriptionService->getPlansForStore($store);

        return view('stores.subscriptions.planes', compact('store', 'plans'));
    }

    public function destroy(Store $store, StorePlan $plan, StorePermissionService $permission, SubscriptionService $subscriptionService)
    {
        $permission->authorize($store, 'subscriptions.destroy');

        if ($plan->store_id !== $store->id) {
            abort(404);
        }

        $subscriptionService->deletePlan($store, $plan->id);

        return redirect()->route('stores.subscriptions.plans', $store)
            ->with('success', 'Plan eliminado correctamente.');
    }

    /**
     * Actualiza qué planes se muestran en el Panel de suscripciones (in_showcase).
     */
    public function updateVisibility(Request $request, Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'subscriptions.edit');

        $request->validate([
            'store_plan_ids' => ['nullable', 'array'],
            'store_plan_ids.*' => ['integer', 'exists:store_plans,id'],
        ]);

        $store->storePlans()->update(['in_showcase' => false]);
        $planIds = $request->input('store_plan_ids', []);
        if (! empty($planIds)) {
            $store->storePlans()->whereIn('id', $planIds)->update(['in_showcase' => true]);
        }

        return redirect()->route('stores.subscriptions.plans', $store)
            ->with('success', 'Visibilidad en Panel de suscripciones actualizada.');
    }
}
