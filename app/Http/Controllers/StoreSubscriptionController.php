<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StorePlan;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreSubscriptionController extends Controller
{
    public function memberships(Request $request, Store $store, StorePermissionService $permission, SubscriptionService $subscriptionService): View
    {
        $permission->authorize($store, 'subscriptions.view');

        $filters = [
            'status' => $request->get('status', 'all'),
            'per_page' => (int) $request->get('per_page', 25),
            'name' => $request->get('name', ''),
            'document' => $request->get('document', ''),
            'phone' => $request->get('phone', ''),
        ];

        $data = $subscriptionService->getMembershipDashboardData($store, $filters);

        $subscriptions = $data['subscriptions'];
        $counters = $data['counters'];
        $normalizedFilters = $data['filters'];

        return view('stores.subscriptions.membresias', [
            'store' => $store,
            'subscriptions' => $subscriptions,
            'counters' => $counters,
            'statusFilter' => $normalizedFilters['status'],
            'perPage' => $normalizedFilters['per_page'],
            'nameFilter' => $normalizedFilters['name'],
            'documentFilter' => $normalizedFilters['document'],
            'phoneFilter' => $normalizedFilters['phone'],
        ]);
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
