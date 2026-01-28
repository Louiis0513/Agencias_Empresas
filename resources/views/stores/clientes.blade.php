<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Clientes - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
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
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Buscador y botón crear --}}
                    <div class="mb-6 flex justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.customers', $store) }}" class="flex-1 flex gap-2">
                            <input type="text" 
                                   name="search" 
                                   value="{{ request('search') }}" 
                                   placeholder="Buscar por nombre, email, documento o teléfono..." 
                                   class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Buscar
                            </button>
                            @if(request('search'))
                                <a href="{{ route('stores.customers', $store) }}" 
                                   class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-600">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'create-customer')"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Cliente
                        </button>
                    </div>

                    {{-- Tabla de clientes --}}
                    @if($customers->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Documento</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Teléfono</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($customers as $customer)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $customer->name }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $customer->document_number ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $customer->email ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $customer->phone ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <button type="button"
                                                        x-on:click="$dispatch('open-customer-detail-modal', { id: {{ $customer->id }} })"
                                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                                                    Ver Detalle
                                                </button>
                                                <button type="button"
                                                        x-on:click="$dispatch('open-edit-customer-modal', { id: {{ $customer->id }} })"
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
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
                        <p class="text-gray-500 dark:text-gray-400 text-center py-8">
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
