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

            // Ventas (carrito y cotizaciones)
            ['slug' => 'ventas.carrito.view', 'name' => 'Ver carrito de ventas', 'description' => 'Acceso al carrito de ventas'],
            ['slug' => 'cotizaciones.view', 'name' => 'Ver cotizaciones', 'description' => 'Ver las cotizaciones guardadas y eliminarlas'],
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
