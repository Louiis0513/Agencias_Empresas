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
     */
    public function createStore(User $user, string $name): Store
    {
        // ---------------------------------------------------------
        // 1. VALIDACIÓN DEL PLAN (Lógica de Negocio)
        // ---------------------------------------------------------
        
        // Obtenemos el plan del usuario
        $plan = $user->plan;

        // Si por alguna razón el usuario no tiene plan, lo bloqueamos (o le damos límite 0)
        if (!$plan) {
            throw new Exception("No tienes un plan asignado. Por favor contacta a soporte.");
        }

        // Obtenemos el límite permitido en la base de datos
        $limit = $plan->max_stores;

        // Contamos cuántas tiendas tiene actualmente este usuario
        $currentStores = Store::where('user_id', $user->id)->count();

        // Si ya tiene igual o más tiendas que el límite, lanzamos el error
        if ($currentStores >= $limit) {
            throw new Exception("Tu plan '{$plan->name}' solo permite crear {$limit} tiendas. ¡Actualiza tu plan para tener más!");
        }

        // ---------------------------------------------------------
        // 2. EJECUCIÓN (Solo llegamos aquí si pasó la validación)
        // ---------------------------------------------------------
        return DB::transaction(function () use ($user, $name) {
            
            // A. Crear la tienda
            $store = Store::create([
                'name' => $name,
                'slug' => Str::slug($name) . '-' . Str::random(4), // Slug único
                'user_id' => $user->id,
            ]);

            // B. Asociar al usuario en la tabla pivote como dueño
            $user->stores()->attach($store->id, ['role_id' => null]);

            return $store;
        });
    }
}