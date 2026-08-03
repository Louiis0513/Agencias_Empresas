<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsToCreate = [
            [
                'slug' => 'contabilidad.centros-costo.view',
                'name' => 'Ver centros de costo',
                'description' => 'Ver catálogo de centros y subcentros de costo',
            ],
            [
                'slug' => 'contabilidad.centros-costo.create',
                'name' => 'Crear centros de costo',
                'description' => 'Crear centros y subcentros de costo',
            ],
            [
                'slug' => 'contabilidad.centros-costo.edit',
                'name' => 'Editar centros de costo',
                'description' => 'Editar o inactivar centros y subcentros de costo',
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

        $oldPermission = DB::table('permissions')->where('slug', 'contabilidad.formas-pago.view')->first()
            ?? DB::table('permissions')->where('slug', 'contabilidad.tipos.view')->first()
            ?? DB::table('permissions')->where('slug', 'contabilidad.cuentas.view')->first();

        if (! $oldPermission) {
            return;
        }

        $roleIds = DB::table('role_permission')
            ->where('permission_id', $oldPermission->id)
            ->pluck('role_id')
            ->unique();

        $newSlugs = [
            'contabilidad.centros-costo.view',
            'contabilidad.centros-costo.create',
            'contabilidad.centros-costo.edit',
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
            'contabilidad.centros-costo.view',
            'contabilidad.centros-costo.create',
            'contabilidad.centros-costo.edit',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
