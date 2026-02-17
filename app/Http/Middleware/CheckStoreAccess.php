<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckStoreAccess
{
    public function handle(Request $request, Closure $next)
    {
        $store = $request->route('store');

        if (! $store || ! Auth::user()?->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        session(['current_store_id' => $store->id]);

        return $next($request);
    }
}
