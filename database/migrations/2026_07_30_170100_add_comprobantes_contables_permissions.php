<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['slug' => 'contabilidad.comprobantes.view', 'name' => 'Ver comprobantes contables', 'description' => 'Ver asientos y comprobantes contables'],
            ['slug' => 'contabilidad.comprobantes.create', 'name' => 'Crear comprobantes contables', 'description' => 'Crear asientos manuales en borrador'],
            ['slug' => 'contabilidad.comprobantes.edit', 'name' => 'Editar comprobantes contables', 'description' => 'Editar asientos manuales en borrador'],
            ['slug' => 'contabilidad.comprobantes.post', 'name' => 'Contabilizar comprobantes', 'description' => 'Contabilizar asientos manuales balanceados'],
            ['slug' => 'contabilidad.comprobantes.reverse', 'name' => 'Reversar comprobantes contables', 'description' => 'Crear el reverso de un asiento contabilizado'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $source = DB::table('permissions')->where('slug', 'contabilidad.tipos.view')->first()
            ?? DB::table('permissions')->where('slug', 'contabilidad.cuentas.view')->first();

        if (! $source) {
            return;
        }

        $roleIds = DB::table('role_permission')
            ->where('permission_id', $source->id)
            ->pluck('role_id')
            ->unique();

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_column($permissions, 'slug'))
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'contabilidad.comprobantes.view',
            'contabilidad.comprobantes.create',
            'contabilidad.comprobantes.edit',
            'contabilidad.comprobantes.post',
            'contabilidad.comprobantes.reverse',
        ];

        $ids = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
