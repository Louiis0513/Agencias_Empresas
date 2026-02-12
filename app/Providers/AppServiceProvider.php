<?php

namespace App\Providers;

use App\Services\StorePermissionService;
use Illuminate\Support\Facades\Blade;
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
        Blade::if('storeCan', function ($store, string $permission): bool {
            return app(StorePermissionService::class)->can($store, $permission);
        });
    }
}
