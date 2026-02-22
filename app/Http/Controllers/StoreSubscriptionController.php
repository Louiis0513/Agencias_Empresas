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
}
