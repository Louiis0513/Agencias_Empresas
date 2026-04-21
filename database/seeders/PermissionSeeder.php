<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Permisos del módulo de tienda (administración de tienda).
     * Cada acción es un permiso independiente para asignación granular por rol.
     */
    public function run(): void
    {
        $permissions = [
            // Grupos de atributos
            ['slug' => 'attribute-groups.view', 'name' => 'Ver grupos de atributos', 'description' => 'Acceso a la lista de grupos de atributos'],
            ['slug' => 'attribute-groups.create', 'name' => 'Crear grupos de atributos', 'description' => 'Crear nuevos grupos de atributos'],
            ['slug' => 'attribute-groups.edit', 'name' => 'Editar grupos de atributos', 'description' => 'Modificar grupos y atributos dentro del grupo'],
            ['slug' => 'attribute-groups.destroy', 'name' => 'Eliminar grupos de atributos', 'description' => 'Eliminar grupos de atributos'],

            // Categorías
            ['slug' => 'categories.view', 'name' => 'Ver categorías', 'description' => 'Ver listado y detalle de categorías'],
            ['slug' => 'categories.create', 'name' => 'Crear categorías', 'description' => 'Crear y editar categorías'],
            ['slug' => 'categories.destroy', 'name' => 'Eliminar categorías', 'description' => 'Eliminar categorías'],
            ['slug' => 'category-attributes.assign', 'name' => 'Asignar atributos de categoría', 'description' => 'Asignar grupos de atributos a categorías'],
            ['slug' => 'category-attributes.create', 'name' => 'Crear atributos de categoría', 'description' => 'Agregar atributos a una categoría'],
            ['slug' => 'category-attributes.edit', 'name' => 'Editar atributos de categoría', 'description' => 'Modificar atributos asignados a una categoría'],
            ['slug' => 'category-attributes.destroy', 'name' => 'Eliminar atributos de categoría', 'description' => 'Retirar atributos de una categoría'],

            // Productos
            ['slug' => 'products.view', 'name' => 'Ver productos', 'description' => 'Ver listado y detalle de productos'],
            ['slug' => 'products.create', 'name' => 'Crear productos', 'description' => 'Crear nuevos productos'],
            ['slug' => 'products.edit', 'name' => 'Editar productos', 'description' => 'Modificar productos y variantes'],
            ['slug' => 'products.destroy', 'name' => 'Eliminar productos', 'description' => 'Eliminar productos'],
            ['slug' => 'product-purchases.view', 'name' => 'Ver compras de productos', 'description' => 'Ver compras/órdenes de productos'],
            ['slug' => 'product-purchases.create', 'name' => 'Crear compras de productos', 'description' => 'Crear compras de productos'],
            ['slug' => 'product-purchases.edit', 'name' => 'Editar compras de productos', 'description' => 'Editar compras de productos'],
            ['slug' => 'product-purchases.destroy', 'name' => 'Eliminar compras de productos', 'description' => 'Eliminar compras de productos'],
            ['slug' => 'product-purchases.approve', 'name' => 'Aprobar compras de productos', 'description' => 'Aprobar compras de productos para actualizar inventario'],

            // Facturas
            ['slug' => 'invoices.view', 'name' => 'Ver facturas', 'description' => 'Ver listado y detalle de facturas'],
            ['slug' => 'invoices.create', 'name' => 'Crear facturas', 'description' => 'Emitir nuevas facturas'],
            ['slug' => 'invoices.void', 'name' => 'Anular facturas', 'description' => 'Anular facturas emitidas'],

            // Proveedores
            ['slug' => 'proveedores.view', 'name' => 'Ver proveedores', 'description' => 'Ver listado de proveedores'],
            ['slug' => 'proveedores.create', 'name' => 'Crear proveedores', 'description' => 'Crear nuevos proveedores'],
            ['slug' => 'proveedores.edit', 'name' => 'Editar proveedores', 'description' => 'Modificar datos de proveedores'],
            ['slug' => 'proveedores.destroy', 'name' => 'Eliminar proveedores', 'description' => 'Eliminar proveedores'],
            ['slug' => 'proveedores.products.assign', 'name' => 'Asignar productos a proveedores', 'description' => 'Relacionar productos existentes con un proveedor'],

            // Clientes
            ['slug' => 'customers.view', 'name' => 'Ver clientes', 'description' => 'Ver listado de clientes'],
            ['slug' => 'customers.create', 'name' => 'Crear clientes', 'description' => 'Crear nuevos clientes'],
            ['slug' => 'customers.edit', 'name' => 'Editar clientes', 'description' => 'Modificar datos de clientes'],
            ['slug' => 'customers.destroy', 'name' => 'Eliminar clientes', 'description' => 'Eliminar clientes'],

            // Caja / Bolsillos / Movimientos
            ['slug' => 'caja.view', 'name' => 'Ver caja', 'description' => 'Ver caja y bolsillos'],
            ['slug' => 'caja.bolsillos.create', 'name' => 'Crear bolsillos', 'description' => 'Crear nuevos bolsillos'],
            ['slug' => 'caja.bolsillos.edit', 'name' => 'Editar bolsillos', 'description' => 'Modificar bolsillos'],
            ['slug' => 'caja.bolsillos.destroy', 'name' => 'Eliminar bolsillos', 'description' => 'Eliminar bolsillos'],
            ['slug' => 'caja.movimientos.create', 'name' => 'Registrar movimientos de caja', 'description' => 'Registrar entradas y salidas de caja'],
            ['slug' => 'caja.sesiones.view', 'name' => 'Ver sesiones de caja', 'description' => 'Ver historial de sesiones de caja'],
            ['slug' => 'caja.sesiones.abrir', 'name' => 'Abrir caja', 'description' => 'Abrir sesión de caja'],
            ['slug' => 'caja.sesiones.cerrar', 'name' => 'Cerrar caja', 'description' => 'Cerrar sesión de caja (wizard)'],

            // Inventario
            ['slug' => 'inventario.view', 'name' => 'Ver inventario', 'description' => 'Ver inventario de productos'],
            ['slug' => 'inventario.movimientos.create', 'name' => 'Registrar movimientos de inventario', 'description' => 'Registrar entradas y salidas de inventario'],
            ['slug' => 'inventario.movimientos.destroy', 'name' => 'Eliminar movimientos de inventario', 'description' => 'Eliminar movimientos de inventario'],

            // Activos
            ['slug' => 'activos.view', 'name' => 'Ver activos', 'description' => 'Ver listado de activos'],
            ['slug' => 'activos.create', 'name' => 'Crear activos', 'description' => 'Crear nuevos activos'],
            ['slug' => 'activos.edit', 'name' => 'Editar activos', 'description' => 'Modificar activos'],
            ['slug' => 'activos.destroy', 'name' => 'Eliminar activos', 'description' => 'Eliminar activos'],
            ['slug' => 'activos.movimientos.view', 'name' => 'Ver movimientos de activos', 'description' => 'Ver entradas y salidas de activos'],
            ['slug' => 'activos.movimientos.create', 'name' => 'Registrar movimientos de activos', 'description' => 'Registrar movimientos de activos'],

            // Compras
            ['slug' => 'purchases.view', 'name' => 'Ver compras', 'description' => 'Ver listado y detalle de compras'],
            ['slug' => 'purchases.create', 'name' => 'Crear compras', 'description' => 'Crear y editar compras'],
            ['slug' => 'purchases.approve', 'name' => 'Aprobar compras', 'description' => 'Aprobar compras'],
            ['slug' => 'purchases.void', 'name' => 'Anular compras', 'description' => 'Anular compras'],

            // Cuentas por pagar
            ['slug' => 'accounts-payables.view', 'name' => 'Ver cuentas por pagar', 'description' => 'Ver listado y detalle de cuentas por pagar'],
            ['slug' => 'accounts-payables.pay', 'name' => 'Registrar pagos (cuentas por pagar)', 'description' => 'Registrar pagos y reversar pagos'],

            // Cuentas por cobrar
            ['slug' => 'accounts-receivables.view', 'name' => 'Ver cuentas por cobrar', 'description' => 'Ver listado y detalle de cuentas por cobrar'],
            ['slug' => 'accounts-receivables.cobrar', 'name' => 'Registrar cobros', 'description' => 'Registrar cobros de cuentas por cobrar'],

            // Comprobantes de ingreso
            ['slug' => 'comprobantes-ingreso.view', 'name' => 'Ver comprobantes de ingreso', 'description' => 'Ver comprobantes de ingreso'],
            ['slug' => 'comprobantes-ingreso.create', 'name' => 'Crear comprobantes de ingreso', 'description' => 'Crear comprobantes de ingreso'],

            // Comprobantes de egreso
            ['slug' => 'comprobantes-egreso.view', 'name' => 'Ver comprobantes de egreso', 'description' => 'Ver comprobantes de egreso'],
            ['slug' => 'comprobantes-egreso.create', 'name' => 'Crear comprobantes de egreso', 'description' => 'Crear comprobantes de egreso'],
            ['slug' => 'comprobantes-egreso.edit', 'name' => 'Editar comprobantes de egreso', 'description' => 'Editar comprobantes de egreso'],
            ['slug' => 'comprobantes-egreso.reversar', 'name' => 'Reversar comprobantes de egreso', 'description' => 'Reversar comprobantes de egreso'],
            ['slug' => 'comprobantes-egreso.anular', 'name' => 'Anular comprobantes de egreso', 'description' => 'Anular comprobantes de egreso'],

            // Roles
            ['slug' => 'roles.view', 'name' => 'Ver roles y permisos', 'description' => 'Ver roles y permisos de la tienda'],
            ['slug' => 'roles.create', 'name' => 'Crear roles', 'description' => 'Crear nuevos roles'],
            ['slug' => 'roles.edit', 'name' => 'Editar roles', 'description' => 'Modificar roles'],
            ['slug' => 'roles.destroy', 'name' => 'Eliminar roles', 'description' => 'Eliminar roles'],
            ['slug' => 'roles.permissions', 'name' => 'Asignar permisos a roles', 'description' => 'Gestionar permisos asignados a cada rol'],

            // Trabajadores
            ['slug' => 'workers.view', 'name' => 'Ver trabajadores', 'description' => 'Ver listado de trabajadores'],
            ['slug' => 'workers.create', 'name' => 'Crear trabajadores', 'description' => 'Añadir trabajadores a la tienda'],
            ['slug' => 'workers.edit', 'name' => 'Editar trabajadores', 'description' => 'Modificar datos y rol de trabajadores'],
            ['slug' => 'workers.destroy', 'name' => 'Eliminar trabajadores', 'description' => 'Quitar trabajadores de la tienda'],
            ['slug' => 'workers.assign-role', 'name' => 'Asignar roles a trabajadores', 'description' => 'Vincular trabajadores existentes a un rol específico'],
            ['slug' => 'workers.schedules.view', 'name' => 'Ver registro de horarios', 'description' => 'Ver horarios de trabajadores'],
            ['slug' => 'workers.schedules.create', 'name' => 'Registrar horarios', 'description' => 'Registrar entradas y salidas de trabajadores'],
            ['slug' => 'workers.schedules.edit', 'name' => 'Editar horarios', 'description' => 'Modificar registros de horarios'],
            ['slug' => 'workers.schedules.destroy', 'name' => 'Eliminar horarios', 'description' => 'Eliminar registros de horarios'],

            // Ventas (carrito y cotizaciones)
            ['slug' => 'ventas.carrito.view', 'name' => 'Ver carrito de ventas', 'description' => 'Acceso al carrito de ventas'],
            ['slug' => 'cotizaciones.view', 'name' => 'Ver cotizaciones', 'description' => 'Ver las cotizaciones guardadas y eliminarlas'],
            ['slug' => 'cotizaciones.destroy', 'name' => 'Eliminar cotizaciones', 'description' => 'Eliminar cotizaciones'],

            // Dashboard y configuración de tienda
            ['slug' => 'dashboard.view', 'name' => 'Ver panel principal', 'description' => 'Acceso al panel principal de la tienda'],
            ['slug' => 'store-config.view', 'name' => 'Ver configuración de tienda', 'description' => 'Ver configuración general de la tienda'],
            ['slug' => 'store-config.edit', 'name' => 'Editar configuración de tienda', 'description' => 'Editar configuración general de la tienda'],
            ['slug' => 'vitrina.view', 'name' => 'Ver vitrina virtual', 'description' => 'Ver la configuración de vitrina virtual de la tienda'],
            ['slug' => 'vitrina.edit', 'name' => 'Editar vitrina virtual', 'description' => 'Editar la configuración de vitrina virtual de la tienda'],
            ['slug' => 'panel-suscripciones-config.view', 'name' => 'Ver configuración de panel de suscripciones', 'description' => 'Ver la configuración del panel de suscripciones de la tienda'],
            ['slug' => 'panel-suscripciones-config.edit', 'name' => 'Editar configuración de panel de suscripciones', 'description' => 'Editar la configuración del panel de suscripciones de la tienda'],

            // Informes
            ['slug' => 'reports.products.view', 'name' => 'Ver informes de productos', 'description' => 'Ver informes del módulo de productos'],
            ['slug' => 'reports.billing.view', 'name' => 'Ver informes de facturación', 'description' => 'Ver informes del módulo de facturación'],

            // Asistencias
            ['slug' => 'asistencias.view', 'name' => 'Ver asistencias', 'description' => 'Ver historial de asistencias'],
            ['slug' => 'asistencias.create', 'name' => 'Registrar asistencias', 'description' => 'Registrar asistencias de clientes'],

            // Documento soporte
            ['slug' => 'support-documents.view', 'name' => 'Ver documentos soporte', 'description' => 'Ver listado y detalle de documentos soporte'],
            ['slug' => 'support-documents.create', 'name' => 'Crear documentos soporte', 'description' => 'Crear documentos soporte en borrador'],
            ['slug' => 'support-documents.edit', 'name' => 'Editar documentos soporte', 'description' => 'Editar documentos soporte en borrador'],
            ['slug' => 'support-documents.approve', 'name' => 'Aprobar documentos soporte', 'description' => 'Aprobar documentos soporte para actualizar inventario'],
            ['slug' => 'support-documents.void', 'name' => 'Anular documentos soporte', 'description' => 'Anular documentos soporte en borrador'],
            ['slug' => 'support-documents.print', 'name' => 'Imprimir documentos soporte', 'description' => 'Imprimir tira de documentos soporte'],
            ['slug' => 'support-documents.export', 'name' => 'Exportar documentos soporte', 'description' => 'Exportar listado de documentos soporte'],

            // Movimientos manuales
            ['slug' => 'inventario.movimientos.manual.create', 'name' => 'Registrar movimientos manuales de inventario', 'description' => 'Registrar entradas y salidas manuales de inventario'],
            ['slug' => 'caja.movimientos.manual.create', 'name' => 'Registrar movimientos manuales de caja', 'description' => 'Registrar entradas y salidas manuales de caja'],

            // Suscripciones (planes, membresías)
            ['slug' => 'subscriptions.view', 'name' => 'Ver suscripciones', 'description' => 'Ver planes y suscripciones de la tienda'],
            ['slug' => 'subscriptions.create', 'name' => 'Crear planes de suscripción', 'description' => 'Crear planes de suscripción o membresía'],
            ['slug' => 'subscriptions.edit', 'name' => 'Editar planes de suscripción', 'description' => 'Modificar planes de suscripción'],
            ['slug' => 'subscriptions.destroy', 'name' => 'Eliminar planes de suscripción', 'description' => 'Eliminar planes de suscripción'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'description' => $p['description'] ?? null,
                ]
            );
        }
    }
}
