<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Caja — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <livewire:create-bolsillo-modal :store-id="$store->id" />
    <livewire:edit-bolsillo-modal :store-id="$store->id" />

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Estado de sesión de caja --}}
            @if($sesionAbierta)
                <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-green-700 dark:text-green-300 font-medium">Sesión de caja abierta</p>
                        <p class="text-sm text-green-600 dark:text-green-400">Desde {{ $sesionAbierta->opened_at->format('d/m/Y H:i') }} por {{ $sesionAbierta->user->name ?? '—' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('stores.cajas.cerrar', $store) }}" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 font-medium">Cerrar caja</a>
                        <a href="{{ route('stores.cajas.sesiones', $store) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">Historial de sesiones</a>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm text-amber-800 dark:text-amber-200 font-medium">No hay sesión de caja abierta. Debe abrir la caja para registrar ventas, comprobantes de ingreso o egreso.</p>
                    <div class="flex gap-2">
                        <a href="{{ route('stores.cajas.apertura', $store) }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">Abrir caja</a>
                        <a href="{{ route('stores.cajas.sesiones', $store) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">Historial de sesiones</a>
                    </div>
                </div>
            @endif

            {{-- Total caja (suma de todos los bolsillos) --}}
            <div class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg">
                <p class="text-sm text-indigo-700 dark:text-indigo-300">Total caja (suma de bolsillos)</p>
                <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">${{ number_format($totalCaja, 2) }}</p>
                <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Sin bolsillos, la caja está vacía.</p>
            </div>

            <div class="mb-6 flex flex-wrap gap-2 justify-between items-center">
                <form method="GET" action="{{ route('stores.cajas', $store) }}" class="flex gap-2 flex-wrap">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar bolsillo" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Buscar</button>
                    @if(request('search'))
                        <a href="{{ route('stores.cajas', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Limpiar</a>
                    @endif
                </form>
                <button type="button" x-on:click="$dispatch('open-modal', 'create-bolsillo')" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Crear bolsillo
                </button>
            </div>

            @if($bolsillos->count() > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Detalles</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($bolsillos as $b)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3">
                                                <a href="{{ route('stores.cajas.bolsillos.show', [$store, $b]) }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    {{ $b->name }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" title="{{ $b->detalles }}">{{ $b->detalles ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">${{ number_format($b->saldo, 2) }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $b->is_bank_account ? 'Cuenta bancaria' : 'Efectivo' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $b->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                    {{ $b->is_active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm font-medium">
                                                <a href="{{ route('stores.cajas.bolsillos.show', [$store, $b]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline mr-3">Movimientos</a>
                                                <button type="button" x-on:click="$dispatch('open-edit-bolsillo-modal', { id: {{ $b->id }} })" class="text-indigo-600 dark:text-indigo-400 hover:underline mr-3">Editar</button>
                                                <form method="POST" action="{{ route('stores.cajas.bolsillos.destroy', [$store, $b]) }}" class="inline" onsubmit="return confirm('¿Eliminar el bolsillo «{{ $b->name }}»? Debe tener saldo 0.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $bolsillos->links() }}</div>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400">No hay bolsillos. La caja está vacía. Crea Caja 1, Caja 2, cuentas bancarias, etc.</p>
                        <div class="mt-4">
                            <button type="button" x-on:click="$dispatch('open-modal', 'create-bolsillo')" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Crear bolsillo
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
