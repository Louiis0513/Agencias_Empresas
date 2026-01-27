<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Plan; // <--- Importante: No olvides importar el Modelo Plan
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. CREAR LOS PLANES DEL SAAS
        // ---------------------------------------------------
        
        // Plan A: Gratuito (Limitado)
        $freePlan = Plan::create([
            'name' => 'Plan Emprendedor (Gratis)',
            'slug' => 'free',
            'max_stores' => 1,      // Solo 1 tienda
            'max_employees' => 2,   // Máximo 2 empleados por tienda
            'price' => 0.00,
        ]);

        // Plan B: De Pago (Más capacidad)
        Plan::create([
            'name' => 'Plan Empresario (Pro)',
            'slug' => 'pro',
            'max_stores' => 5,      // Hasta 5 tiendas
            'max_employees' => 10,  // Hasta 10 empleados
            'price' => 29.99,
        ]);


        // 2. CREAR EL USUARIO DE PRUEBA (ASIGNADO AL PLAN)
        // ---------------------------------------------------
        User::factory()->create([
            'name' => 'Luis Javier Correa', // Puse tu nombre para que se vea bien
            'email' => 'test@example.com',
            'password' => bcrypt('password'), // La clave por defecto suele ser 'password'
            'plan_id' => $freePlan->id,       // <--- AQUÍ LO CONECTAMOS AL PLAN GRATIS
        ]);
    }
}