<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Proveedores - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <livewire:create-proveedor-modal :store-id="$store->id" />
    <livewire:edit-proveedor-modal :store-id="$store->id" />

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.proveedores', $store) }}" class="flex-1 flex gap-2">
                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Buscar por nombre, email, NIT, teléfono o productos..."
                                   class="flex-1 rounded-md border-white/10 bg-white/5 text-gray-100">
                            <button type="submit"
                                    class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                                Buscar
                            </button>
                            @if(request('search'))
                                <a href="{{ route('stores.proveedores', $store) }}"
                                   class="px-4 py-2 bg-white/10 text-gray-300 rounded-xl hover:bg-white/20 border border-white/10">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'create-proveedor')"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Proveedor
                        </button>
                    </div>

                    @if($proveedores->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Celular</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Teléfono</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">NIT</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Productos</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($proveedores as $proveedor)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">{{ $proveedor->nombre }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $proveedor->numero_celular ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $proveedor->telefono ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $proveedor->email ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $proveedor->nit ?? '-' }}</td>
                                            <td class="px-4 py-4">
                                                <div class="flex flex-wrap gap-1 max-w-[10rem]">
                                                    @foreach($proveedor->productos as $prod)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200" title="{{ $prod->name }}">
                                                            {{ \Illuminate\Support\Str::limit($prod->name, 15) }}
                                                        </span>
                                                    @endforeach
                                                    @if($proveedor->productos->isEmpty())
                                                        <span class="text-xs text-gray-400">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($proveedor->estado)
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Activo</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inactivo</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <button type="button"
                                                        x-on:click="$dispatch('open-edit-proveedor-modal', { id: {{ $proveedor->id }} })"
                                                        class="text-brand hover:text-white transition mr-3">
                                                    Editar
                                                </button>
                                                <form method="POST" action="{{ route('stores.proveedores.destroy', [$store, $proveedor]) }}" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar este proveedor?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $proveedores->links() }}
                        </div>
                    @else
                        <p class="text-gray-400 text-center py-8">
                            @if(request('search'))
                                No se encontraron proveedores con el término "{{ request('search') }}".
                            @else
                                No hay proveedores registrados.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
