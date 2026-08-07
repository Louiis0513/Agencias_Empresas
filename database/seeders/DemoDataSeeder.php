<?php

namespace Database\Seeders;

use App\Models\Bolsillo;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Ejecuta el seeding de datos de demo para la tienda principal.
     *
     * - Usuario administrador de demo.
     * - Tienda "LouisPremium" (idealmente con id = 1).
     * - Permisos y rol administrador con todos los permisos.
     * - Proveedores y bolsillos de ejemplo para la tienda de demo.
     */
    public function run(): void
    {
        $user = $this->seedDemoUser();
        $store = $this->seedDemoStore($user);
        $this->seedPermissionsAndAdminRole($user, $store);
        $this->seedProveedores($store->id);
        $this->seedBolsillos($store->id);
    }

    private function seedDemoUser(): User
    {
        $plan = Plan::where('slug', 'free')->first() ?? Plan::first();

        return User::updateOrCreate(
            ['email' => 'luisjavi0513@gmail.com'],
            [
                'name' => 'Admin LouisPremium',
                'password' => bcrypt('admin1234'),
                'plan_id' => $plan?->id,
            ]
        );
    }

    private function seedDemoStore(User $user): Store
    {
        $store = Store::find(1);

        if (! $store) {
            $store = Store::create([
                'name' => 'LouisPremium',
                'slug' => Str::slug('LouisPremium'),
                'user_id' => $user->id,
            ]);
        } else {
            $store->update([
                'name' => 'LouisPremium',
                'slug' => $store->slug ?: Str::slug('LouisPremium'),
                'user_id' => $user->id,
            ]);
        }

        $now = now();
        DB::table('store_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'store_id' => $store->id,
            ],
            [
                'role_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        app(\App\Services\TerceroService::class)->asegurarConsumidorFinal($store);

        return $store;
    }

    private function seedPermissionsAndAdminRole(User $user, Store $store): void
    {
        $this->call(PermissionSeeder::class);

        $adminRole = Role::firstOrCreate(
            [
                'store_id' => $store->id,
                'name' => 'Admin',
            ]
        );

        $this->call(DefaultStoreRolesSeeder::class);

        $allPermissionIds = Permission::pluck('id')->all();
        $adminRole->permissions()->sync($allPermissionIds);

        $now = now();
        DB::table('store_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'store_id' => $store->id,
            ],
            [
                'role_id' => $adminRole->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function seedProveedores(int $storeId): void
    {
        $store = Store::findOrFail($storeId);
        $terceroService = app(\App\Services\TerceroService::class);

        $nombres = [
            'Empresa1 - Distribuciones Andes',
            'Empresa2 - Logística del Norte',
            'Empresa3 - Importaciones Caribe',
        ];

        foreach ($nombres as $index => $nombre) {
            $nit = '900'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);
            $exists = \App\Models\Tercero::query()
                ->deStore($store)
                ->where('numero_identificacion', $nit)
                ->exists();
            if ($exists) {
                continue;
            }

            $terceroService->crear($store, [
                'nombre' => $nombre,
                'tipo_persona' => 'juridica',
                'tipo_identificacion' => 'NIT',
                'numero_identificacion' => $nit,
                'telefono' => '30000000'.($index + 1),
                'email' => 'proveedor'.($index + 1).'@demo.test',
                'direccion' => 'Calle '.(10 + $index).' # 1-'.(20 + $index),
                'activo' => true,
                'roles' => [\App\Models\Tercero::ROL_PROVEEDOR],
            ]);
        }
    }

    private function seedBolsillos(int $storeId): void
    {
        $bolsillos = [
            [
                'name' => 'Cajero1',
                'detalles' => 'Caja principal del punto de venta',
                'is_bank_account' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Nequi (Bancario)',
                'detalles' => 'Bolsillo bancario Nequi para pagos digitales',
                'is_bank_account' => true,
                'is_active' => true,
            ],
        ];

        foreach ($bolsillos as $data) {
            Bolsillo::firstOrCreate(
                [
                    'store_id' => $storeId,
                    'name' => $data['name'],
                ],
                [
                    'detalles' => $data['detalles'],
                    'saldo' => 0,
                    'is_bank_account' => $data['is_bank_account'],
                    'is_active' => $data['is_active'],
                ]
            );
        }
    }
}
