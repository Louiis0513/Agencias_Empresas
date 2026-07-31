<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsToCreate = [
            [
                'slug' => 'terceros.view',
                'name' => 'Ver terceros',
                'description' => 'Ver catálogo de terceros (clientes, proveedores, trabajadores)',
            ],
            [
                'slug' => 'terceros.create',
                'name' => 'Crear terceros',
                'description' => 'Crear terceros',
            ],
            [
                'slug' => 'terceros.edit',
                'name' => 'Editar terceros',
                'description' => 'Editar terceros, roles, contactos y perfiles',
            ],
            [
                'slug' => 'terceros.destroy',
                'name' => 'Eliminar terceros',
                'description' => 'Desactivar o eliminar terceros',
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

        $legacySlugs = [
            'customers.view',
            'proveedores.view',
            'workers.view',
        ];

        $roleIds = DB::table('role_permission')
            ->whereIn('permission_id', function ($q) use ($legacySlugs) {
                $q->select('id')->from('permissions')->whereIn('slug', $legacySlugs);
            })
            ->pluck('role_id')
            ->unique();

        if ($roleIds->isEmpty()) {
            $fallback = DB::table('permissions')->where('slug', 'caja.view')->first();
            if ($fallback) {
                $roleIds = DB::table('role_permission')
                    ->where('permission_id', $fallback->id)
                    ->pluck('role_id')
                    ->unique();
            }
        }

        $newSlugs = ['terceros.view', 'terceros.create', 'terceros.edit', 'terceros.destroy'];

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
        $slugs = ['terceros.view', 'terceros.create', 'terceros.edit', 'terceros.destroy'];
        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
