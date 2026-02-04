<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Comprobantes de Ingreso - {{ $store->name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('stores.comprobantes-ingreso.create', $store) }}" class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Nuevo ingreso</a>
                <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">← Volver</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="GET" action="{{ route('stores.comprobantes-ingreso.index', $store) }}" class="mb-6 flex gap-2 flex-wrap">
                        <select name="type" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Todos</option>
                            <option value="COBRO_CUENTA" {{ request('type') == 'COBRO_CUENTA' ? 'selected' : '' }}>Cobro a cuenta</option>
                            <option value="INGRESO_MANUAL" {{ request('type') == 'INGRESO_MANUAL' ? 'selected' : '' }}>Ingreso manual</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filtrar</button>
                    </form>

                    @if($comprobantes->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Número</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente / Origen</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($comprobantes as $ci)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $ci->number }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $ci->date->format('d/m/Y') }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                @if($ci->type === 'COBRO_CUENTA')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">Cobro</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Ingreso manual</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($ci->total_amount, 2) }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                @if($ci->customer)
                                                    {{ $ci->customer->name }}
                                                @elseif($ci->aplicaciones->count() > 0)
                                                    Factura(s) por cobrar
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('stores.comprobantes-ingreso.show', [$store, $ci]) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Ver</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $comprobantes->links() }}</div>
                    @else
                        <p class="text-center py-8 text-gray-500 dark:text-gray-400">No hay comprobantes de ingreso.</p>
                        <div class="text-center">
                            <a href="{{ route('stores.comprobantes-ingreso.create', $store) }}" class="text-indigo-600 hover:text-indigo-800">Crear el primero</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
