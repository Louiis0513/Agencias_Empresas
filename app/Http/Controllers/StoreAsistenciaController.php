<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreAsistenciaController extends Controller
{
    public function index(Request $request, Store $store, StorePermissionService $permission, SubscriptionService $subscriptionService): View
    {
        $permission->authorize($store, 'subscriptions.view');

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : null;
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;

        $entries = $subscriptionService->getAttendanceHistoryForStore($store, $from, $to, $customerId, 25);

        return view('stores.asistencias.index', compact('store', 'entries', 'from', 'to', 'customerId'));
    }
}
