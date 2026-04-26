<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StoreFeatureAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorePlanDesignerController extends Controller
{
    public function index(Store $store, StoreFeatureAccessService $featureAccess): View
    {
        $this->authorizeDesigner($store);

        $user = auth()->user();
        $catalog = $featureAccess->getCatalogForStore($store, $user);

        return view('stores.subscriptions.plan-designer', [
            'store' => $store,
            'catalog' => $catalog,
        ]);
    }

    public function updateFeature(Request $request, Store $store, StoreFeatureAccessService $featureAccess): RedirectResponse
    {
        $this->authorizeDesigner($store);

        $data = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:plan_features,id'],
            'status' => ['required', 'string'],
        ]);

        $featureAccess->updateFeatureStatus($store, (int) $data['feature_id'], $data['status'], (int) auth()->id());

        return back()->with('success', 'Feature actualizada correctamente.');
    }

    public function bulk(Request $request, Store $store, StoreFeatureAccessService $featureAccess): RedirectResponse
    {
        $this->authorizeDesigner($store);

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $featureAccess->applyStatusToAll($store, $data['status'], (int) auth()->id());

        return back()->with('success', 'Acción masiva aplicada correctamente.');
    }

    private function authorizeDesigner(Store $store): void
    {
        if ((int) $store->user_id !== (int) auth()->id()) {
            abort(403, 'Solo el propietario puede usar el diseñador de planes.');
        }
    }
}

