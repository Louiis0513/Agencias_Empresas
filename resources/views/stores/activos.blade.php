<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Activos - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.activos', $store) }}" class="flex flex-wrap gap-2 items-end">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Buscar por nombre, código, serial..."
                                   class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <select name="status" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="">Todos los estados</option>
                                @foreach(\App\Models\Activo::estadosDisponibles() as $k => $v)
                                    <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Buscar</button>
                            @if(request()->anyFilled(['search', 'status']))
                                <a href="{{ route('stores.activos', $store) }}" class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md">Limpiar</a>
                            @endif
                        </form>
                        <div class="flex gap-2">
                            <a href="{{ route('stores.activos.movimientos', $store) }}"
                               class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                Historial
                            </a>
                            <button type="button" x-on:click="$dispatch('open-modal', 'create-activo')"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Crear Activo
                            </button>
                        </div>
                    </div>

                    @if($activos->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Serial</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ubicación</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($activos as $activo)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $activo->name }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $activo->serial_number }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">{{ number_format($activo->unit_cost, 2) }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $activo->locationRelation?->name ?? $activo->location ?? '-' }}</td>
                                            <td class="px-4 py-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                                    @if($activo->status === \App\Models\Activo::STATUS_OPERATIVO) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                    @elseif(in_array($activo->status, [\App\Models\Activo::STATUS_DADO_DE_BAJA, \App\Models\Activo::STATUS_VENDIDO, \App\Models\Activo::STATUS_DONADO])) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                                    @else bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                                    @endif
                                                ">{{ \App\Models\Activo::estadosDisponibles()[$activo->status] ?? $activo->status }}</span>
                                            </td>
                                            <td class="px-4 py-4 text-right text-sm font-medium">
                                                <a href="{{ route('stores.activos.show', [$store, $activo]) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">Ver</a>
                                                <a href="{{ route('stores.activos.edit', [$store, $activo]) }}" class="text-emerald-600 hover:text-emerald-900 dark:text-emerald-400 dark:hover:text-emerald-300">Editar</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $activos->links() }}</div>
                    @else
                        <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                            @if(request('search'))
                                No hay activos que coincidan con la búsqueda.
                            @else
                                No hay activos registrados. Los activos son bienes que se compran para usar (computadores, muebles, etc.), no para vender.
                                <button type="button" x-on:click="$dispatch('open-modal', 'create-activo')" class="text-indigo-600 dark:text-indigo-400 hover:underline bg-transparent border-0 p-0 cursor-pointer font-inherit">Crear el primero</button>
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
        @livewire('create-activo-modal', ['storeId' => $store->id, 'fromPurchase' => false])
    </div>
</x-app-layout>
