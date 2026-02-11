<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Permisos del rol «{{ $role->name }}» - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.roles', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Roles
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data>
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Tabla: Trabajadores con este rol --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Trabajadores con este rol
                    </h3>
                    @if($workersWithRole->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($workersWithRole as $worker)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $worker->name }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $worker->email }}</td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                <a href="{{ route('stores.workers.edit', [$store, $worker]) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Editar</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">Ningún trabajador tiene asignado este rol.</p>
                    @endif
                </div>
            </div>

            {{-- Pecera: Permisos que ya tiene este rol --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        Permisos asignados a este rol
                    </h3>
                    @if($role->permissions->count() > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($role->permissions as $permission)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    {{ $permission->name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">Este rol no tiene permisos asignados. Marca los que desees más abajo y guarda.</p>
                    @endif
                </div>
            </div>

            {{-- Gran contenedor: Gestionar permisos (desplegable) --}}
            <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-600">
                <button type="button"
                        @click="open = !open"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                    <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">Gestionar permisos</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Añadir o quitar permisos de este rol</span>
                    <svg class="w-6 h-6 text-gray-400 transition-transform shrink-0 ml-2" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div x-show="open" x-collapse class="border-t border-gray-200 dark:border-gray-600">
                    <form method="POST" action="{{ route('stores.roles.permissions.update', [$store, $role]) }}" class="p-6">
                        @csrf

                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Marca o desmarca los permisos por módulo. Al guardar se actualizarán los permisos de este rol.
                        </p>

                        @php
                            $rolePermissionIds = $role->permissions->pluck('id')->flip()->all();
                            $moduleLabels = [
                                'attribute-groups' => 'Grupos de atributos',
                                'categories' => 'Categorías',
                                'category-attributes' => 'Atributos de categoría',
                                'products' => 'Productos',
                                'product-purchases' => 'Compras de productos',
                                'invoices' => 'Facturas',
                                'proveedores' => 'Proveedores',
                                'customers' => 'Clientes',
                                'caja' => 'Caja',
                                'inventario' => 'Inventario',
                                'activos' => 'Activos',
                                'purchases' => 'Compras',
                                'accounts-payables' => 'Cuentas por pagar',
                                'accounts-receivables' => 'Cuentas por cobrar',
                                'comprobantes-ingreso' => 'Comprobantes de ingreso',
                                'comprobantes-egreso' => 'Comprobantes de egreso',
                                'roles' => 'Roles y permisos',
                                'workers' => 'Trabajadores',
                            ];
                            $permissionsByModule = $allPermissions->groupBy(fn ($p) => explode('.', $p->slug)[0]);
                        @endphp

                        <div class="space-y-2">
                            @foreach($permissionsByModule as $moduleKey => $groupPermissions)
                                <div x-data="{ openModule: false }" class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                                    <button type="button"
                                            @click="openModule = !openModule"
                                            class="w-full flex items-center justify-between px-4 py-3 text-left bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $moduleLabels[$moduleKey] ?? str_replace('-', ' ', $moduleKey) }}</span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $groupPermissions->count() }} permiso(s)</span>
                                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': openModule }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div x-show="openModule" x-collapse class="border-t border-gray-200 dark:border-gray-600">
                                        <div class="p-4 space-y-2 bg-white dark:bg-gray-800">
                                            @foreach($groupPermissions as $permission)
                                                <label class="flex items-center gap-2 py-1.5 cursor-pointer group">
                                                    <input type="checkbox"
                                                           name="permission_ids[]"
                                                           value="{{ $permission->id }}"
                                                           {{ isset($rolePermissionIds[$permission->id]) ? 'checked' : '' }}
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">{{ $permission->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <a href="{{ route('stores.roles', $store) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md font-medium text-sm hover:bg-gray-300 dark:hover:bg-gray-600">
                                Cancelar
                            </a>
                            <x-primary-button type="submit">
                                Guardar permisos
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
