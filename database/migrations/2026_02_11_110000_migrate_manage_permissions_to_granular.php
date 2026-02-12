<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convierte roles con permisos .manage a los permisos granulados equivalentes.
     * Asegura que los permisos granulados existan antes de mapear.
     */
    public function up(): void
    {
        $this->ensureGranularPermissionsExist();

        $mappings = [
            'workers.manage' => ['workers.create', 'workers.edit', 'workers.destroy'],
            'roles.manage' => ['roles.create', 'roles.edit', 'roles.destroy', 'roles.permissions'],
            'category-attributes.manage' => ['category-attributes.assign'],
            'product-purchases.manage' => ['product-purchases.create', 'product-purchases.edit', 'product-purchases.destroy'],
            'caja.bolsillos.manage' => ['caja.bolsillos.create', 'caja.bolsillos.edit', 'caja.bolsillos.destroy'],
            'caja.movimientos.manage' => ['caja.movimientos.create', 'caja.movimientos.destroy'],
            'inventario.movimientos.manage' => ['inventario.movimientos.create', 'inventario.movimientos.destroy'],
            'comprobantes-egreso.manage' => ['comprobantes-egreso.create', 'comprobantes-egreso.edit', 'comprobantes-egreso.reversar', 'comprobantes-egreso.anular'],
        ];

        foreach ($mappings as $oldSlug => $newSlugs) {
            $oldPermission = DB::table('permissions')->where('slug', $oldSlug)->first();
            if (! $oldPermission) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $oldPermission->id)
                ->pluck('role_id')
                ->unique();

            foreach ($newSlugs as $newSlug) {
                $newPermission = DB::table('permissions')->where('slug', $newSlug)->first();
                if (! $newPermission) {
                    continue;
                }

                foreach ($roleIds as $roleId) {
                    $exists = DB::table('role_permission')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $newPermission->id)
                        ->exists();

                    if (! $exists) {
                        DB::table('role_permission')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $newPermission->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertimos: los roles quedarían con permisos granulados adicionales
    }

    private function ensureGranularPermissionsExist(): void
    {
        $toCreate = [
            ['slug' => 'category-attributes.assign', 'name' => 'Asignar atributos de categoría', 'description' => 'Asignar grupos de atributos a categorías'],
            ['slug' => 'product-purchases.create', 'name' => 'Crear compras de productos', 'description' => 'Crear compras de productos'],
            ['slug' => 'product-purchases.edit', 'name' => 'Editar compras de productos', 'description' => 'Editar compras de productos'],
            ['slug' => 'product-purchases.destroy', 'name' => 'Eliminar compras de productos', 'description' => 'Eliminar compras de productos'],
            ['slug' => 'caja.bolsillos.create', 'name' => 'Crear bolsillos', 'description' => 'Crear nuevos bolsillos'],
            ['slug' => 'caja.bolsillos.edit', 'name' => 'Editar bolsillos', 'description' => 'Modificar bolsillos'],
            ['slug' => 'caja.bolsillos.destroy', 'name' => 'Eliminar bolsillos', 'description' => 'Eliminar bolsillos'],
            ['slug' => 'caja.movimientos.create', 'name' => 'Registrar movimientos de caja', 'description' => 'Registrar entradas y salidas de caja'],
            ['slug' => 'caja.movimientos.destroy', 'name' => 'Eliminar movimientos de caja', 'description' => 'Eliminar movimientos de caja'],
            ['slug' => 'inventario.movimientos.create', 'name' => 'Registrar movimientos de inventario', 'description' => 'Registrar entradas y salidas de inventario'],
            ['slug' => 'inventario.movimientos.destroy', 'name' => 'Eliminar movimientos de inventario', 'description' => 'Eliminar movimientos de inventario'],
            ['slug' => 'comprobantes-egreso.create', 'name' => 'Crear comprobantes de egreso', 'description' => 'Crear comprobantes de egreso'],
            ['slug' => 'comprobantes-egreso.edit', 'name' => 'Editar comprobantes de egreso', 'description' => 'Editar comprobantes de egreso'],
            ['slug' => 'comprobantes-egreso.reversar', 'name' => 'Reversar comprobantes de egreso', 'description' => 'Reversar comprobantes de egreso'],
            ['slug' => 'comprobantes-egreso.anular', 'name' => 'Anular comprobantes de egreso', 'description' => 'Anular comprobantes de egreso'],
            ['slug' => 'roles.create', 'name' => 'Crear roles', 'description' => 'Crear nuevos roles'],
            ['slug' => 'roles.edit', 'name' => 'Editar roles', 'description' => 'Modificar roles'],
            ['slug' => 'roles.destroy', 'name' => 'Eliminar roles', 'description' => 'Eliminar roles'],
            ['slug' => 'roles.permissions', 'name' => 'Asignar permisos a roles', 'description' => 'Gestionar permisos asignados a cada rol'],
            ['slug' => 'workers.create', 'name' => 'Crear trabajadores', 'description' => 'Añadir trabajadores a la tienda'],
            ['slug' => 'workers.edit', 'name' => 'Editar trabajadores', 'description' => 'Modificar datos y rol de trabajadores'],
            ['slug' => 'workers.destroy', 'name' => 'Eliminar trabajadores', 'description' => 'Quitar trabajadores de la tienda'],
        ];

        foreach ($toCreate as $p) {
            if (! DB::table('permissions')->where('slug', $p['slug'])->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $p['slug'],
                    'name' => $p['name'],
                    'description' => $p['description'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
