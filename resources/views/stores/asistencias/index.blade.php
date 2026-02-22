<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Asistencias - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ showForm: false }">
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

            <div class="mb-6 flex items-center gap-3">
                <button type="button" x-show="!showForm" x-on:click="showForm = true" x-cloak
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                    Registrar asistencia
                </button>
                <button type="button" x-show="showForm" x-on:click="showForm = false"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cerrar
                </button>
            </div>

            <div x-show="showForm" x-cloak class="mb-8">
                @livewire('registrar-asistencia-form', ['storeId' => $store->id])
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('stores.asistencias', $store) }}" class="mb-6 flex flex-wrap items-end gap-4">
                        <div>
                            <label for="from" class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Desde</label>
                            <input type="date" name="from" id="from" value="{{ $from?->format('Y-m-d') }}"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="to" class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Hasta</label>
                            <input type="date" name="to" id="to" value="{{ $to?->format('Y-m-d') }}"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="customer_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">ID Cliente</label>
                            <input type="number" name="customer_id" id="customer_id" value="{{ $customerId }}"
                                placeholder="Opcional" min="1"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm w-28">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                            Filtrar
                        </button>
                        <a href="{{ route('stores.asistencias', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                            Limpiar
                        </a>
                    </form>

                    @if($entries->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha y hora</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($entries as $entry)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $entry->recorded_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $entry->customer?->name ?? '—' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $entry->customerSubscription?->storePlan?->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $entries->withQueryString()->links() }}
                        </div>
                    @else
                        <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                            No hay registros de asistencia con los filtros indicados.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
