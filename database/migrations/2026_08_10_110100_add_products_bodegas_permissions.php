<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsToCreate = [
            [
                'slug' => 'products.bodegas.view',
                'name' => 'Ver bodegas',
                'description' => 'Ver catálogo de bodegas y configuración de manejo',
            ],
            [
                'slug' => 'products.bodegas.create',
                'name' => 'Crear bodegas',
                'description' => 'Crear bodegas en la tienda',
            ],
            [
                'slug' => 'products.bodegas.edit',
                'name' => 'Editar bodegas',
                'description' => 'Editar, inactivar bodegas o cambiar el manejo de bodegas',
            ],
        ];

        foreach ($permissionsToCreate as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $permission['slug'],
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $oldPermission = DB::table('permissions')->where('slug', 'products.view')->first();

        if (! $oldPermission) {
            return;
        }

        $roleIds = DB::table('role_permission')
            ->where('permission_id', $oldPermission->id)
            ->pluck('role_id')
            ->unique();

        $newSlugs = [
            'products.bodegas.view',
            'products.bodegas.create',
            'products.bodegas.edit',
        ];

        foreach ($roleIds as $roleId) {
            foreach ($newSlugs as $slug) {
                $newPermission = DB::table('permissions')->where('slug', $slug)->first();
                if (! $newPermission) {
                    continue;
                }

                $exists = DB::table('role_permission')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $newPermission->id)
                    ->exists();

                if (! $exists) {
                    DB::table('role_permission')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $newPermission->id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'products.bodegas.view',
            'products.bodegas.create',
            'products.bodegas.edit',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
