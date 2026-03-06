<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\VitrinaConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetStoreTimezone
{
    /**
     * Establece la zona horaria de la aplicación según la tienda actual.
     * Así los timestamps (created_at, updated_at) se guardan en hora local de la tienda.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->resolveStore($request);

        if ($store && $store->timezone) {
            config(['app.timezone' => $store->timezone]);
            date_default_timezone_set($store->timezone);
        }

        return $next($request);
    }

    private function resolveStore(Request $request): ?Store
    {
        // Rutas stores/{store}: la tienda viene por model binding
        $store = $request->route('store');
        if ($store instanceof Store) {
            return $store;
        }

        // Rutas vitrina/{slug}: buscar tienda por VitrinaConfig
        $slug = $request->route('slug');
        if (is_string($slug)) {
            $config = VitrinaConfig::where('slug', $slug)->with('store')->first();

            return $config?->store;
        }

        // Rutas sin store en URL (dashboard, profile): usar la última tienda visitada
        $storeId = Session::get('current_store_id');
        if ($storeId) {
            return Store::find($storeId);
        }

        return null;
    }
}
