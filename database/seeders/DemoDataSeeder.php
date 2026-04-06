<?php

namespace Database\Seeders;

use App\Models\Bolsillo;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use App\Services\AttributeService;
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
     * - Productos de demo (reutilizando ProductosDemoSeeder para store_id = 1).
     * - Proveedores y bolsillos de ejemplo para la tienda de demo.
     */
    public function run(): void
    {
        // 1. Usuario de demo
        $user = $this->seedDemoUser();

        // 2. Tienda principal de demo (intentando usar store_id = 1)
        $store = $this->seedDemoStore($user);

        // 3. Permisos base y rol administrador completo para la tienda
        $this->seedPermissionsAndAdminRole($user, $store);

        // 4. Grupos de atributos, atributos y categorías base para Abarrotes y Ropa
        $this->seedAttributeGroupsCategoriesAndRelations($store->id);

        // 5. Productos de demo (atributos, categorías, productos simples y por lote)
        //    Se reutiliza el seeder existente que ya crea una estructura coherente
        //    sobre store_id = 1.
        $this->call(ProductosDemoSeeder::class);

        // 6. Proveedores y bolsillos para la tienda de demo
        $this->seedProveedores($store->id);
        $this->seedBolsillos($store->id);
    }

    private function seedDemoUser(): User
    {
        // Intentamos localizar un plan "free" para vincular al usuario,
        // pero si no existe no bloqueamos el seeding.
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
        // Si ya existe la tienda con id = 1, la reutilizamos como tienda de demo
        // actualizando su dueño y nombre. Si no, creamos una nueva (normalmente
        // obtendrá id = 1 en una base limpia).
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

        // Vincular al usuario con la tienda en el pivot store_user
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

        Customer::ensureConsumidorFinalForStore((int) $store->id);

        return $store;
    }

    private function seedPermissionsAndAdminRole(User $user, Store $store): void
    {
        // Asegurar que existan todos los permisos base
        $this->call(PermissionSeeder::class);

        // Crear (o reutilizar) rol administrador para esta tienda
        $adminRole = Role::firstOrCreate(
            [
                'store_id' => $store->id,
                'name' => 'Admin',
            ]
        );

        // Asignar todos los permisos existentes al rol Admin
        $allPermissionIds = Permission::pluck('id')->all();
        $adminRole->permissions()->sync($allPermissionIds);

        // Asegurar que el usuario de demo tenga el rol Admin en esta tienda
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

    private function seedAttributeGroupsCategoriesAndRelations(int $storeId): void
    {
        $attributeService = app(AttributeService::class);

        // Grupos de atributos
        $grupoAbarrotes = AttributeGroup::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Abarrotes'],
            ['position' => 1]
        );

        $grupoParaRopa = AttributeGroup::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Para Ropa'],
            ['position' => 2]
        );

        // Atributos exclusivos por grupo (no compartidos para respetar el índice único por attribute_id)
        // Grupo Abarrotes
        $attrMarcaAbarrotes = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Marca Abarrotes'],
            ['is_required' => false]
        );

        $attrColorAbarrotes = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Color Abarrotes'],
            ['is_required' => false]
        );

        $attrMaterialAbarrotes = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Material Abarrotes'],
            ['is_required' => false]
        );

        // Grupo Para Ropa
        $attrMarcaRopa = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Marca Ropa'],
            ['is_required' => false]
        );

        $attrColorRopa = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Color Ropa'],
            ['is_required' => false]
        );

        $attrMaterialRopa = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Material Ropa'],
            ['is_required' => false]
        );

        $attrTallaRopa = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Talla Ropa'],
            ['is_required' => false]
        );

        // Vincular atributos a cada grupo con posiciones e is_required en el pivot
        $grupoAbarrotes->attributes()->syncWithoutDetaching([
            $attrMarcaAbarrotes->id => ['position' => 1, 'is_required' => false],
            $attrColorAbarrotes->id => ['position' => 2, 'is_required' => false],
            $attrMaterialAbarrotes->id => ['position' => 3, 'is_required' => false],
        ]);

        $grupoParaRopa->attributes()->syncWithoutDetaching([
            $attrMarcaRopa->id => ['position' => 1, 'is_required' => false],
            $attrTallaRopa->id => ['position' => 2, 'is_required' => false],
            $attrMaterialRopa->id => ['position' => 3, 'is_required' => false],
            $attrColorRopa->id => ['position' => 4, 'is_required' => false],
        ]);

        // Categorías base para la tienda de demo
        $categoriaAbarrotes = Category::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Abarrotes'],
            ['parent_id' => null]
        );

        $categoriaRopa = Category::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Ropa'],
            ['parent_id' => null]
        );

        // Asignar grupos de atributos a cada categoría usando el servicio de atributos
        $attributeService->assignGroupsToCategory($categoriaAbarrotes, [$grupoAbarrotes->id]);
        $attributeService->assignGroupsToCategory($categoriaRopa, [$grupoParaRopa->id]);
    }

    private function seedProveedores(int $storeId): void
    {
        $nombres = [
            'Empresa1 - Distribuciones Andes',
            'Empresa2 - Logística del Norte',
            'Empresa3 - Importaciones Caribe',
        ];

        foreach ($nombres as $index => $nombre) {
            Proveedor::firstOrCreate(
                [
                    'store_id' => $storeId,
                    'nombre' => $nombre,
                ],
                [
                    'numero_celular' => '30000000' . ($index + 1),
                    'telefono' => null,
                    'email' => 'proveedor' . ($index + 1) . '@demo.test',
                    'nit' => '900' . str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'direccion' => 'Calle ' . (10 + $index) . ' # 1-' . (20 + $index),
                    'estado' => true,
                ]
            );
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

