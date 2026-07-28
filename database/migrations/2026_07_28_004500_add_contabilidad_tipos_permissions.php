<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsToCreate = [
            [
                'slug' => 'contabilidad.tipos.view',
                'name' => 'Ver tipos de comprobante',
                'description' => 'Ver catálogo de tipos de comprobante contable',
            ],
            [
                'slug' => 'contabilidad.tipos.create',
                'name' => 'Crear tipos de comprobante',
                'description' => 'Crear tipos de comprobante contable',
            ],
            [
                'slug' => 'contabilidad.tipos.edit',
                'name' => 'Editar tipos de comprobante',
                'description' => 'Editar tipos de comprobante contable',
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

        $oldPermission = DB::table('permissions')->where('slug', 'contabilidad.categorias.view')->first()
            ?? DB::table('permissions')->where('slug', 'contabilidad.cuentas.view')->first()
            ?? DB::table('permissions')->where('slug', 'caja.view')->first();

        if (! $oldPermission) {
            return;
        }

        $roleIds = DB::table('role_permission')
            ->where('permission_id', $oldPermission->id)
            ->pluck('role_id')
            ->unique();

        $newSlugs = [
            'contabilidad.tipos.view',
            'contabilidad.tipos.create',
            'contabilidad.tipos.edit',
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
            'contabilidad.tipos.view',
            'contabilidad.tipos.create',
            'contabilidad.tipos.edit',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
