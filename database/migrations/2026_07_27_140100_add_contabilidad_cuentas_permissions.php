<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsToCreate = [
            [
                'slug' => 'contabilidad.cuentas.view',
                'name' => 'Ver plan de cuentas',
                'description' => 'Ver el plan de cuentas contables de la tienda',
            ],
            [
                'slug' => 'contabilidad.cuentas.create',
                'name' => 'Crear cuentas auxiliares',
                'description' => 'Crear cuentas auxiliares en el plan de cuentas',
            ],
            [
                'slug' => 'contabilidad.cuentas.edit',
                'name' => 'Editar cuentas contables',
                'description' => 'Editar cuentas del plan de cuentas',
            ],
            [
                'slug' => 'contabilidad.cuentas.import',
                'name' => 'Importar plan de cuentas',
                'description' => 'Importar el PUC base (sin auxiliares) desde Excel',
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

        // Quienes ya veían caja reciben acceso al plan de cuentas
        $oldPermission = DB::table('permissions')->where('slug', 'caja.view')->first();
        if (! $oldPermission) {
            return;
        }

        $roleIds = DB::table('role_permission')
            ->where('permission_id', $oldPermission->id)
            ->pluck('role_id')
            ->unique();

        $newSlugs = [
            'contabilidad.cuentas.view',
            'contabilidad.cuentas.create',
            'contabilidad.cuentas.edit',
            'contabilidad.cuentas.import',
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
            'contabilidad.cuentas.view',
            'contabilidad.cuentas.create',
            'contabilidad.cuentas.edit',
            'contabilidad.cuentas.import',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
