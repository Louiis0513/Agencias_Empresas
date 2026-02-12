<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Plan; // <--- Importante: No olvides importar el Modelo Plan
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
            'max_employees' => 2,   // Máximo 2 empleados por tienda (legacy)
            'price' => 0.00,
        ]);

        // Plan B: De Pago (Más capacidad)
        $proPlan = Plan::create([
            'name' => 'Plan Empresario (Pro)',
            'slug' => 'pro',
            'max_stores' => 5,      // Hasta 5 tiendas
            'max_employees' => 10,  // Máximo 10 empleados (legacy)
            'price' => 29.99,
        ]);

        // 1.1. LIMITES POR MODULO (BASE PARA FUTURA LÓGICA DE PLANES)
        // ------------------------------------------------------------
        // Nota: estos límites aún no se usan en servicios/middleware.
        // Sirven como base de datos para el futuro sistema de validación de planes.

        $now = now();

        // Helper simple para insertar límites evitando duplicados
        $insertLimit = function (Plan $plan, string $module, ?int $limit) use ($now) {
            DB::table('plan_module_limits')->updateOrInsert(
                ['plan_id' => $plan->id, 'module' => $module],
                ['limit' => $limit, 'updated_at' => $now, 'created_at' => $now]
            );
        };

        // Plan Free
        $insertLimit($freePlan, 'stores', 1);
        $insertLimit($freePlan, 'workers', 1);
        $insertLimit($freePlan, 'products', 100);
        $insertLimit($freePlan, 'customers', 200);
        $insertLimit($freePlan, 'proveedores', 50);
        $insertLimit($freePlan, 'invoices', 500);
        $insertLimit($freePlan, 'purchases', 200);
        $insertLimit($freePlan, 'caja', 1);                 // 1 = módulo habilitado
        $insertLimit($freePlan, 'inventario', 1);           // 1 = módulo habilitado
        $insertLimit($freePlan, 'activos', 50);
        $insertLimit($freePlan, 'comprobantes-ingreso', 200);
        $insertLimit($freePlan, 'comprobantes-egreso', 200);
        $insertLimit($freePlan, 'accounts-payables', 1);    // 1 = módulo habilitado
        $insertLimit($freePlan, 'accounts-receivables', 1); // 1 = módulo habilitado

        // Plan Pro
        $insertLimit($proPlan, 'stores', 5);
        $insertLimit($proPlan, 'workers', 10);
        $insertLimit($proPlan, 'products', null);           // null = sin límite
        $insertLimit($proPlan, 'customers', null);
        $insertLimit($proPlan, 'proveedores', null);
        $insertLimit($proPlan, 'invoices', null);
        $insertLimit($proPlan, 'purchases', null);
        $insertLimit($proPlan, 'caja', 1);
        $insertLimit($proPlan, 'inventario', 1);
        $insertLimit($proPlan, 'activos', null);
        $insertLimit($proPlan, 'comprobantes-ingreso', null);
        $insertLimit($proPlan, 'comprobantes-egreso', null);
        $insertLimit($proPlan, 'accounts-payables', 1);
        $insertLimit($proPlan, 'accounts-receivables', 1);

        // 2. PERMISOS DEL MÓDULO DE TIENDA
        // ---------------------------------------------------
        $this->call(PermissionSeeder::class);

        // 3. CREAR EL USUARIO DE PRUEBA (ASIGNADO AL PLAN)
        // ---------------------------------------------------
        User::factory()->create([
            'name' => 'Luis Javier Correa', // Puse tu nombre para que se vea bien
            'email' => 'test@example.com',
            'password' => bcrypt('password'), // La clave por defecto suele ser 'password'
            'plan_id' => $freePlan->id,       // <--- AQUÍ LO CONECTAMOS AL PLAN GRATIS
        ]);
    }
}