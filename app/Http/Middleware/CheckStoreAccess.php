<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckStoreAccess
{
    public function handle(Request $request, Closure $next)
    {
        $store = $request->route('store');

        if ($store instanceof Store) {
            $storeId = $store->id;
        } elseif (is_string($store)) {
            $model = Store::where('slug', $store)->first();
            if (! $model) {
                abort(404, 'Tienda no encontrada.');
            }
            $storeId = $model->id;
        } else {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        if (! Auth::user()?->stores->contains($storeId)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $storeId]);

        return $next($request);
    }
}
