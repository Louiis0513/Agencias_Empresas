<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StorePlan;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;

class StoreSubscriptionController extends Controller
{
    public function plans(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'subscriptions.view');

        $plans = $store->storePlans()->orderBy('name')->get();

        return view('stores.subscriptions.planes', compact('store', 'plans'));
    }

    public function destroy(Store $store, StorePlan $plan, StorePermissionService $permission)
    {
        $permission->authorize($store, 'subscriptions.destroy');

        if ($plan->store_id !== $store->id) {
            abort(404);
        }

        $plan->delete();

        return redirect()->route('stores.subscriptions.plans', $store)
            ->with('success', 'Plan eliminado correctamente.');
    }
}
