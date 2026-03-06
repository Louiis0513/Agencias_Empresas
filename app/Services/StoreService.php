<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Exception; // <--- Importante: Importar esto para poder lanzar errores

class StoreService
{
    /**
     * Crea una nueva tienda y asigna al usuario como dueño,
     * verificando primero los límites de su plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStore(User $user, array $data): Store
    {
        // ---------------------------------------------------------
        // 1. VALIDACIÓN DEL PLAN (Lógica de Negocio)
        // ---------------------------------------------------------

        $plan = $user->plan;

        if (! $plan) {
            throw new Exception("No tienes un plan asignado. Por favor contacta a soporte.");
        }

        $limit = $plan->max_stores;
        $currentStores = Store::where('user_id', $user->id)->count();

        if ($currentStores >= $limit) {
            throw new Exception("Tu plan '{$plan->name}' solo permite crear {$limit} tiendas. ¡Actualiza tu plan para tener más!");
        }

        // ---------------------------------------------------------
        // 2. EJECUCIÓN
        // ---------------------------------------------------------
        // Usar timezone de la tienda para que created_at sea en hora local
        $timezone = $data['timezone'] ?? 'America/Bogota';
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return DB::transaction(function () use ($user, $data) {
            $name = $data['name'];

            $store = Store::create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::random(4),
                'user_id' => $user->id,
                'rut_nit' => $data['rut_nit'] ?? null,
                'currency' => $data['currency'] ?? 'COP',
                'timezone' => $data['timezone'] ?? 'America/Bogota',
                'date_format' => $data['date_format'] ?? 'd-m-Y',
                'time_format' => $data['time_format'] ?? '24',
                'country' => $data['country'] ?? null,
                'department' => $data['department'] ?? null,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'domain' => $data['domain'] ?? null,
                'regimen' => $data['regimen'] ?? null,
                'logo_path' => $data['logo_path'] ?? null,
            ]);

            $user->stores()->attach($store->id, ['role_id' => null]);

            return $store;
        });
    }
}