<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Cotizaciones - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.ventas.carrito', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Ir al Carrito
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative dark:bg-green-900/30 dark:border-green-700 dark:text-green-300" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($cotizaciones->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nota</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ítems</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor cotización</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor actual</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Alerta</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($cotizaciones as $cot)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $cot->id }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"
                                                x-data="{ d: new Date('{{ $cot->created_at->utc()->toIso8601String() }}') }"
                                                x-text="d.toLocaleString('es', { dateStyle: 'short', timeStyle: 'short' })">
                                                {{ $cot->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $cot->user->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $cot->customer?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate" title="{{ $cot->nota }}">{{ $cot->nota }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $cot->items->count() }}</td>
                                            @php
                                                $totales = $totalesPorCotizacion[$cot->id] ?? ['total_cotizado' => 0, 'total_actual' => 0];
                                                $diff = $totales['total_actual'] - $totales['total_cotizado'];
                                                $hayCambio = abs($diff) > 0.005;
                                            @endphp
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right font-medium">{{ number_format($totales['total_cotizado'], 2) }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right font-medium">{{ number_format($totales['total_actual'], 2) }}</td>
                                            <td class="px-4 py-3 text-center">
                                                @if($hayCambio)
                                                    <span class="text-xs font-medium {{ $diff > 0 ? 'text-amber-800 dark:text-amber-200' : 'text-emerald-800 dark:text-emerald-200' }}"
                                                        title="{{ $diff > 0 ? 'El valor actual es mayor que el cotizado' : 'El valor actual es menor que el cotizado' }}">
                                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }} {{ $diff > 0 ? '(aumentó)' : '(disminuyó)' }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <a href="{{ route('stores.ventas.cotizaciones.show', [$store, $cot]) }}"
                                                   wire:navigate
                                                   class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700">
                                                    Ver
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $cotizaciones->links() }}
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay cotizaciones. Agrega productos al carrito y guárdalos como cotización.</p>
                        <a href="{{ route('stores.ventas.carrito', $store) }}" class="inline-flex mt-4 items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                            Ir al Carrito
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
