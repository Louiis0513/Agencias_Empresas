<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsToCreate = [
            [
                'slug' => 'contabilidad.formas-pago.view',
                'name' => 'Ver formas de pago',
                'description' => 'Ver catálogo de formas de pago contables',
            ],
            [
                'slug' => 'contabilidad.formas-pago.create',
                'name' => 'Crear formas de pago',
                'description' => 'Crear formas de pago en el catálogo contable',
            ],
            [
                'slug' => 'contabilidad.formas-pago.edit',
                'name' => 'Editar formas de pago',
                'description' => 'Editar formas de pago del catálogo contable',
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

        $oldPermission = DB::table('permissions')->where('slug', 'contabilidad.impuestos.view')->first()
            ?? DB::table('permissions')->where('slug', 'contabilidad.tipos.view')->first()
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
            'contabilidad.formas-pago.view',
            'contabilidad.formas-pago.create',
            'contabilidad.formas-pago.edit',
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
            'contabilidad.formas-pago.view',
            'contabilidad.formas-pago.create',
            'contabilidad.formas-pago.edit',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
