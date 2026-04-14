<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsToCreate = [
            ['slug' => 'dashboard.view', 'name' => 'Ver panel principal', 'description' => 'Acceso al panel principal de la tienda'],
            ['slug' => 'store-config.view', 'name' => 'Ver configuración de tienda', 'description' => 'Ver configuración general de la tienda'],
            ['slug' => 'store-config.edit', 'name' => 'Editar configuración de tienda', 'description' => 'Editar configuración general de la tienda'],
            ['slug' => 'vitrina.view', 'name' => 'Ver vitrina virtual', 'description' => 'Ver la configuración de vitrina virtual de la tienda'],
            ['slug' => 'vitrina.edit', 'name' => 'Editar vitrina virtual', 'description' => 'Editar la configuración de vitrina virtual de la tienda'],
            ['slug' => 'panel-suscripciones-config.view', 'name' => 'Ver configuración de panel de suscripciones', 'description' => 'Ver la configuración del panel de suscripciones de la tienda'],
            ['slug' => 'panel-suscripciones-config.edit', 'name' => 'Editar configuración de panel de suscripciones', 'description' => 'Editar la configuración del panel de suscripciones de la tienda'],
            ['slug' => 'reports.products.view', 'name' => 'Ver informes de productos', 'description' => 'Ver informes del módulo de productos'],
            ['slug' => 'reports.billing.view', 'name' => 'Ver informes de facturación', 'description' => 'Ver informes del módulo de facturación'],
            ['slug' => 'asistencias.view', 'name' => 'Ver asistencias', 'description' => 'Ver historial de asistencias'],
            ['slug' => 'asistencias.create', 'name' => 'Registrar asistencias', 'description' => 'Registrar asistencias de clientes'],
            ['slug' => 'cotizaciones.destroy', 'name' => 'Eliminar cotizaciones', 'description' => 'Eliminar cotizaciones'],
            ['slug' => 'support-documents.view', 'name' => 'Ver documentos soporte', 'description' => 'Ver listado y detalle de documentos soporte'],
            ['slug' => 'support-documents.create', 'name' => 'Crear documentos soporte', 'description' => 'Crear documentos soporte en borrador'],
            ['slug' => 'support-documents.edit', 'name' => 'Editar documentos soporte', 'description' => 'Editar documentos soporte en borrador'],
            ['slug' => 'support-documents.approve', 'name' => 'Aprobar documentos soporte', 'description' => 'Aprobar documentos soporte para actualizar inventario'],
            ['slug' => 'support-documents.void', 'name' => 'Anular documentos soporte', 'description' => 'Anular documentos soporte en borrador'],
            ['slug' => 'support-documents.print', 'name' => 'Imprimir documentos soporte', 'description' => 'Imprimir tira de documentos soporte'],
            ['slug' => 'support-documents.export', 'name' => 'Exportar documentos soporte', 'description' => 'Exportar listado de documentos soporte'],
            ['slug' => 'inventario.movimientos.manual.create', 'name' => 'Registrar movimientos manuales de inventario', 'description' => 'Registrar entradas y salidas manuales de inventario'],
            ['slug' => 'caja.movimientos.manual.create', 'name' => 'Registrar movimientos manuales de caja', 'description' => 'Registrar entradas y salidas manuales de caja'],
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

        $permissionMappings = [
            // Documento soporte: preservar acceso previo basado en product-purchases.*
            'product-purchases.view' => ['support-documents.view', 'support-documents.print', 'support-documents.export'],
            'product-purchases.create' => ['support-documents.create', 'support-documents.edit', 'support-documents.approve', 'support-documents.void'],
            'product-purchases.edit' => ['support-documents.edit'],
            'product-purchases.approve' => ['support-documents.approve'],

            // Alias para diferenciar acciones manuales vs automáticas
            'inventario.movimientos.create' => ['inventario.movimientos.manual.create'],
            'caja.movimientos.create' => ['caja.movimientos.manual.create'],

            // Conserva acceso histórico al introducir permisos nuevos de navegación/módulo
            'products.view' => ['reports.products.view'],
            'invoices.view' => ['reports.billing.view'],
            'subscriptions.view' => ['asistencias.view'],
            'subscriptions.create' => ['asistencias.create'],
        ];

        foreach ($permissionMappings as $oldSlug => $newSlugs) {
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

    public function down(): void
    {
        // No revertimos para evitar pérdida de permisos en roles existentes.
    }
};
