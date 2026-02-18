<?php

namespace App\Providers;

use App\Models\Store;
use App\Services\StorePermissionService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            // Si no hay usuario autenticado, dejamos pasar (return null) para no interferir con el resto del Gate
            if (! $user) {
                return null;
            }

            // 1. Intentar obtener la tienda de la ruta actual
            $store = request()->route('store');

            // Si es un slug (string), buscamos el modelo. Con Route Model Binding suele ser ya un Store.
            if (is_string($store)) {
                $store = Store::where('slug', $store)->first();
            }

            // 2. Si no viene en ruta, intentar fallback a la sesión (útil para Livewire/Ajax)
            if (! $store instanceof Store) {
                $storeId = Session::get('current_store_id');
                if ($storeId) {
                    $store = Store::find($storeId);
                }
            }

            // 3. Si definitivamente NO hay contexto de tienda, retornamos null.
            // Esto permite que otras Policies o Gates globales funcionen normalmente (ej: Perfil de usuario)
            if (! $store instanceof Store) {
                return null;
            }

            // 4. Si hay tienda, delegamos la autorización al servicio de permisos de tienda
            return app(StorePermissionService::class)
                ->userHasPermission($user, $store, $ability);
        });

        Blade::if('storeCan', function ($store, string $permission): bool {
            return app(StorePermissionService::class)->can($store, $permission);
        });
    }
}
