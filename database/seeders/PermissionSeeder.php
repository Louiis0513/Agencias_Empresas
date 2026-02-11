<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Permisos del módulo de tienda (administración de tienda).
     * Slug único para verificación en código; name para mostrar en UI.
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
            ['slug' => 'category-attributes.manage', 'name' => 'Gestionar atributos de categoría', 'description' => 'Asignar grupos de atributos a categorías'],

            // Productos
            ['slug' => 'products.view', 'name' => 'Ver productos', 'description' => 'Ver listado y detalle de productos'],
            ['slug' => 'products.create', 'name' => 'Crear productos', 'description' => 'Crear nuevos productos'],
            ['slug' => 'products.edit', 'name' => 'Editar productos', 'description' => 'Modificar productos y variantes'],
            ['slug' => 'products.destroy', 'name' => 'Eliminar productos', 'description' => 'Eliminar productos'],
            ['slug' => 'product-purchases.view', 'name' => 'Ver compras de productos', 'description' => 'Ver compras/órdenes de productos'],
            ['slug' => 'product-purchases.manage', 'name' => 'Gestionar compras de productos', 'description' => 'Crear y gestionar compras de productos'],

            // Facturas
            ['slug' => 'invoices.view', 'name' => 'Ver facturas', 'description' => 'Ver listado y detalle de facturas'],
            ['slug' => 'invoices.create', 'name' => 'Crear facturas', 'description' => 'Emitir nuevas facturas'],
            ['slug' => 'invoices.void', 'name' => 'Anular facturas', 'description' => 'Anular facturas emitidas'],

            // Proveedores
            ['slug' => 'proveedores.view', 'name' => 'Ver proveedores', 'description' => 'Ver listado de proveedores'],
            ['slug' => 'proveedores.create', 'name' => 'Crear proveedores', 'description' => 'Crear nuevos proveedores'],
            ['slug' => 'proveedores.edit', 'name' => 'Editar proveedores', 'description' => 'Modificar datos de proveedores'],
            ['slug' => 'proveedores.destroy', 'name' => 'Eliminar proveedores', 'description' => 'Eliminar proveedores'],

            // Clientes
            ['slug' => 'customers.view', 'name' => 'Ver clientes', 'description' => 'Ver listado de clientes'],
            ['slug' => 'customers.create', 'name' => 'Crear clientes', 'description' => 'Crear nuevos clientes'],
            ['slug' => 'customers.edit', 'name' => 'Editar clientes', 'description' => 'Modificar datos de clientes'],
            ['slug' => 'customers.destroy', 'name' => 'Eliminar clientes', 'description' => 'Eliminar clientes'],

            // Caja / Bolsillos / Movimientos
            ['slug' => 'caja.view', 'name' => 'Ver caja', 'description' => 'Ver caja y bolsillos'],
            ['slug' => 'caja.bolsillos.manage', 'name' => 'Gestionar bolsillos', 'description' => 'Crear, editar y eliminar bolsillos'],
            ['slug' => 'caja.movimientos.manage', 'name' => 'Gestionar movimientos de caja', 'description' => 'Registrar y eliminar movimientos de caja'],

            // Inventario
            ['slug' => 'inventario.view', 'name' => 'Ver inventario', 'description' => 'Ver inventario de productos'],
            ['slug' => 'inventario.movimientos.manage', 'name' => 'Gestionar movimientos de inventario', 'description' => 'Registrar entradas y salidas de inventario'],

            // Activos
            ['slug' => 'activos.view', 'name' => 'Ver activos', 'description' => 'Ver listado de activos'],
            ['slug' => 'activos.create', 'name' => 'Crear activos', 'description' => 'Crear nuevos activos'],
            ['slug' => 'activos.edit', 'name' => 'Editar activos', 'description' => 'Modificar activos'],
            ['slug' => 'activos.destroy', 'name' => 'Eliminar activos', 'description' => 'Eliminar activos'],

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
            ['slug' => 'comprobantes-egreso.manage', 'name' => 'Gestionar comprobantes de egreso', 'description' => 'Crear, editar, reversar y anular comprobantes de egreso'],

            // Roles y trabajadores (solo para dueño o roles con este permiso)
            ['slug' => 'roles.view', 'name' => 'Ver roles y permisos', 'description' => 'Ver roles y permisos de la tienda'],
            ['slug' => 'roles.manage', 'name' => 'Gestionar roles y permisos', 'description' => 'Crear, editar y eliminar roles; asignar permisos'],
            ['slug' => 'workers.view', 'name' => 'Ver trabajadores', 'description' => 'Ver listado de trabajadores'],
            ['slug' => 'workers.manage', 'name' => 'Gestionar trabajadores', 'description' => 'Añadir, editar y eliminar trabajadores; asignar roles'],
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
