<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Cotización #{{ $cotizacion->id }} - {{ $store->name }}
            </h2>
            <div class="flex items-center gap-3">
                <form action="{{ route('stores.ventas.cotizaciones.destroy', [$store, $cotizacion]) }}" method="POST" class="inline"
                      onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta cotización?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">
                        Eliminar cotización
                    </button>
                </form>
                <a href="{{ route('stores.ventas.cotizaciones', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    ← Volver a Cotizaciones
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Información de la cotización --}}
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Número</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">#{{ $cotizacion->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Fecha y hora</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                               x-data="{ d: new Date('{{ $cotizacion->created_at->utc()->toIso8601String() }}') }"
                               x-text="d.toLocaleString('es', { dateStyle: 'short', timeStyle: 'short' })">
                                {{ $cotizacion->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Creada por</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $cotizacion->user->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cliente</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $cotizacion->customer?->name ?? '—' }}</p>
                        </div>
                    </div>

                    @if($cotizacion->nota)
                        <div class="mb-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Nota</p>
                            <p class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $cotizacion->nota }}</p>
                        </div>
                    @endif

                    {{-- Ítems de la cotización con precios --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Productos</h3>
                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producto</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio unit.</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                    @foreach($itemsConPrecios as $row)
                                        @php $item = $row['item']; @endphp
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $item->name ?? $item->product->name ?? '—' }}
                                                @if($item->variant_display_name)
                                                    <span class="block text-gray-600 dark:text-gray-400 text-xs mt-0.5">— {{ $item->variant_display_name }}</span>
                                                @elseif($item->serial_numbers && count($item->serial_numbers) > 0)
                                                    <span class="block text-gray-600 dark:text-gray-400 text-xs mt-0.5">Serie: {{ implode(', ', $item->serial_numbers) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">{{ $item->quantity }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format($row['unit_price'], 2) }}</td>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100 text-right">{{ number_format($row['subtotal'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if(count($itemsConPrecios) > 0)
                            @php $totalCotizacion = collect($itemsConPrecios)->sum('subtotal'); @endphp
                            <div class="mt-4 flex justify-end">
                                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-6 py-4 min-w-[200px]">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($totalCotizacion, 2) }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
