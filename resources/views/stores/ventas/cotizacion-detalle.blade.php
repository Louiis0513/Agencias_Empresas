<x-app-layout>
@php
        // Datos por ítem para Alpine y para construir el payload al hacer clic en Facturar
        $itemsParaFacturarData = collect($itemsConPrecios)->map(function($row, $index) {
            $item = $row['item'];
            $type = 'simple';
            if (!empty($item->serial_numbers) && count($item->serial_numbers) > 0) {
                $type = 'serialized';
            } elseif (!empty($item->variant_features)) {
                $type = 'batch';
            }
            $baseName = $item->name ?? $item->product->name ?? 'Producto';
            if (!empty($item->variant_display_name)) {
                $displayName = $baseName . ' (' . $item->variant_display_name . ')';
            } elseif (!empty($item->serial_numbers) && is_array($item->serial_numbers) && count($item->serial_numbers) > 0) {
                $displayName = $baseName . ' (Serie: ' . implode(', ', $item->serial_numbers) . ')';
            } else {
                $displayName = $baseName;
            }
            $precioCotizado = $row['unit_price'];
            $precioActual = $row['unit_price_actual'] ?? $precioCotizado;
            $precioCambio = $row['precio_cambio'] ?? false;
            return [
                'index' => $index,
                'product_id' => $item->product_id,
                'name' => $displayName,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $precioCotizado,
                'unit_price_actual' => (float) $precioActual,
                'precio_cambio' => $precioCambio,
                'type' => $type,
                'product_variant_id' => $item->product_variant_id,
                'variant_features' => $item->variant_features ?? [],
                'variant_display_name' => $item->variant_display_name ?? '',
                'serial_numbers' => $item->serial_numbers ?? [],
            ];
        })->values()->all();
    @endphp
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Cotización #{{ $cotizacion->id }} - {{ $store->name }}
            </h2>
            <div class="flex items-center gap-3">
                @if($preConversion['ya_facturada'] ?? false)
                    @if($cotizacion->invoice_id)
                        <a href="{{ route('stores.invoices.show', [$store, $cotizacion->invoice_id]) }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-md hover:bg-emerald-700">
                            Ver factura #{{ $cotizacion->invoice_id }}
                        </a>
                    @else
                        <span class="inline-flex items-center px-3 py-1.5 bg-gray-500 text-white text-sm rounded-md">Ya facturada</span>
                    @endif
                @else
                    <form action="{{ route('stores.ventas.cotizaciones.destroy', [$store, $cotizacion]) }}" method="POST" class="inline"
                          onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta cotización?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">
                            Eliminar cotización
                        </button>
                    </form>
                @endif
                <a href="{{ route('stores.ventas.cotizaciones', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    ← Volver a Cotizaciones
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if($preConversion['vencida'] ?? false)
                <div class="mb-4 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                    <p class="text-amber-800 dark:text-amber-200 text-sm font-medium">
                        Cotización vencida (vencimiento: {{ $cotizacion->vence_at?->format('d/m/Y') ?? '—' }}). Puede facturarla igualmente; se le pedirá confirmación al emitir la factura.
                    </p>
                </div>
            @endif
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

                    {{-- Ítems de la cotización con precios y selectores por ítem --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4"
                         x-data="cotizacionFacturar({{ \Illuminate\Support\Js::from($itemsParaFacturarData) }}, {{ $cotizacion->customer_id ?? 'null' }}, {{ $cotizacion->id }})"
                         x-init="$nextTick(() => { rows.forEach((r, i) => { if (r.precio_cambio) itemSelections[i] = 'cotizado' }) })"
                         x-effect="itemSelections; if ($refs.totalDisplay && typeof total === 'number') $refs.totalDisplay.textContent = formatNum(total)">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Productos</h3>
                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producto</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio cotizado</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio actual</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usar precio</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                    @foreach($itemsParaFacturarData as $index => $rd)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $rd['name'] }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">{{ $rd['quantity'] }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format($rd['unit_price'], 2) }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">{{ number_format($rd['unit_price_actual'], 2) }}</td>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100 text-right" x-text="formatNum(getSubtotal(rows[{{ $index }}], {{ $index }}))">{{ number_format($rd['unit_price'] * $rd['quantity'], 2) }}</td>
                                            <td class="px-4 py-2 text-sm">
                                                @if($rd['precio_cambio'])
                                                    <div class="flex flex-wrap gap-2">
                                                        <label class="inline-flex items-center gap-1 cursor-pointer">
                                                            <input type="radio" name="precio-{{ $index }}" value="cotizado" checked x-model="itemSelections[{{ $index }}]" class="rounded border-gray-500 text-indigo-600 focus:ring-indigo-500">
                                                            <span class="text-gray-700 dark:text-gray-300 text-xs">Respetar cotización</span>
                                                        </label>
                                                        <label class="inline-flex items-center gap-1 cursor-pointer">
                                                            <input type="radio" name="precio-{{ $index }}" value="actual" x-model="itemSelections[{{ $index }}]" class="rounded border-gray-500 text-indigo-600 focus:ring-indigo-500">
                                                            <span class="text-gray-700 dark:text-gray-300 text-xs">Valor actual</span>
                                                        </label>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if(count($itemsConPrecios) > 0)
                            @php $totalInicial = collect($itemsParaFacturarData)->sum(fn($rd) => $rd['unit_price'] * $rd['quantity']); @endphp
                            @if(!($preConversion['ya_facturada'] ?? false))
                            <div class="mt-4 flex flex-wrap items-center justify-end gap-4">
                                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-6 py-4 min-w-[200px]">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-1" x-ref="totalDisplay">{{ number_format($totalInicial, 2) }}</p>
                                </div>
                                <button type="button"
                                    x-on:click="facturar()"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Facturar Cotización
                                </button>
                            </div>
                            @else
                            <div class="mt-4 flex justify-end">
                                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-6 py-4 min-w-[200px]">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($totalInicial, 2) }}</p>
                                </div>
                            </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <livewire:create-invoice-modal :store-id="$store->id" />

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('cotizacionFacturar', (rows, customerId, cotizacionId) => {
                const itemSelections = {};
                rows.forEach((r, i) => {
                    if (r.precio_cambio) itemSelections[i] = 'cotizado';
                });
                return {
                rows: rows,
                customerId: customerId,
                cotizacionId: cotizacionId,
                itemSelections: itemSelections,
                getSelection(index) {
                    return this.itemSelections[index] ?? 'cotizado';
                },
                setSelection(index, value) {
                    this.itemSelections[index] = value;
                },
                getPrice(row, index) {
                    if (row.precio_cambio) {
                        return this.getSelection(index) === 'actual' ? row.unit_price_actual : row.unit_price;
                    }
                    return row.unit_price;
                },
                getSubtotal(row, index) {
                    return this.getPrice(row, index) * row.quantity;
                },
                get total() {
                    return this.rows.reduce((sum, row, i) => sum + this.getSubtotal(row, i), 0);
                },
                formatNum(n) {
                    return typeof n === 'number' ? n.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : n;
                },
                facturar() {
                    const items = this.rows.map((row, i) => ({
                        product_id: row.product_id,
                        name: row.name,
                        quantity: row.quantity,
                        price: this.getPrice(row, i),
                        type: row.type,
                        product_variant_id: row.product_variant_id,
                        variant_features: row.variant_features || [],
                        variant_display_name: row.variant_display_name || '',
                        serial_numbers: row.serial_numbers || [],
                    }));
                    Livewire.dispatch('load-items-from-cart', {
                        items,
                        customer_id: this.customerId,
                        cotizacion_id: this.cotizacionId,
                    });
                },
            };
            });
        });
    </script>
</x-app-layout>
