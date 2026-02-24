<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Clientes - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <livewire:create-customer-modal :store-id="$store->id" />
    <livewire:edit-customer-modal :store-id="$store->id" />
    <livewire:customer-detail-modal :store-id="$store->id" />

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes de éxito/error --}}
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
                    {{-- Buscador y botón crear --}}
                    <div class="mb-6 flex justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.customers', $store) }}" class="flex-1 flex gap-2">
                            <input type="text" 
                                   name="search" 
                                   value="{{ request('search') }}" 
                                   placeholder="Buscar por nombre, email, documento o teléfono..." 
                                   class="flex-1 rounded-md border-white/10 bg-white/5 text-gray-100">
                            <button type="submit" 
                                    class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                                Buscar
                            </button>
                            @if(request('search'))
                                <a href="{{ route('stores.customers', $store) }}" 
                                   class="px-4 py-2 bg-white/10 text-gray-300 rounded-xl hover:bg-white/20 border border-white/10">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'create-customer')"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Cliente
                        </button>
                    </div>

                    {{-- Tabla de clientes --}}
                    @if($customers->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Documento</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Teléfono</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($customers as $customer)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">{{ $customer->name }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $customer->document_number ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $customer->email ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $customer->phone ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <button type="button"
                                                        x-on:click="$dispatch('open-customer-detail-modal', { id: {{ $customer->id }} })"
                                                        class="text-brand hover:text-white transition mr-3">
                                                    Ver Detalle
                                                </button>
                                                <button type="button"
                                                        x-on:click="$dispatch('open-edit-customer-modal', { id: {{ $customer->id }} })"
                                                        class="text-brand hover:text-white transition mr-3">
                                                    Editar
                                                </button>
                                                <form method="POST" action="{{ route('stores.customers.destroy', [$store, $customer]) }}" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar este cliente?');">
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

                        {{-- Paginación --}}
                        <div class="mt-4">
                            {{ $customers->links() }}
                        </div>
                    @else
                        <p class="text-gray-400 text-center py-8">
                            @if(request('search'))
                                No se encontraron clientes con el término "{{ request('search') }}".
                            @else
                                No hay clientes registrados.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
