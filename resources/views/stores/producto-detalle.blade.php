<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $product->name }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.products', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Productos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Resumen del producto --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Datos del producto</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Categoría</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $product->category?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                            @if($product->isSerialized())
                                Serializado
                            @elseif($product->isBatch())
                                Por lotes
                            @else
                                {{ $product->type ?? '—' }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Stock</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $product->stock }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Ubicación</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $product->location ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="mt-0.5">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Precio</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ number_format($product->price, 2) }} €</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Costo (ref.)</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ number_format($product->cost, 2) }} €</dd>
                    </div>
                </dl>
            </div>

            {{-- Inventario: serializado = product_items --}}
            @if($product->isSerialized())
                <livewire:edit-product-item-modal :store-id="$store->id" :product-id="$product->id" />
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Unidades serializadas ({{ $product->productItems->count() }})</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cada fila es una unidad en inventario. Asigna el precio de venta y edita datos con «Modificar».</p>
                    </div>
                    @if($product->productItems->isEmpty())
                        <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">No hay unidades en inventario.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nº Serie</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Costo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio venta</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Referencia</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Atributos</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($product->productItems as $item)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->serial_number }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($item->cost, 2) }} €</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                @if($item->price !== null && (float)$item->price > 0)
                                                    {{ number_format($item->price, 2) }} €
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @php
                                                    $statusLabels = \App\Models\ProductItem::estadosDisponibles();
                                                @endphp
                                                <span class="px-2 inline-flex text-xs leading-5 font-medium rounded-full
                                                    @if($item->status === \App\Models\ProductItem::STATUS_AVAILABLE) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                    @elseif($item->status === \App\Models\ProductItem::STATUS_SOLD) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                                    @elseif($item->status === \App\Models\ProductItem::STATUS_RESERVED) bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                                    @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                                    @endif">
                                                    {{ $statusLabels[$item->status] ?? $item->status }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $item->batch ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                @if(!empty($item->features) && is_array($item->features))
                                                    {{ collect($item->features)->map(fn($v, $k) => "{$k}: {$v}")->implode(', ') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                                <button type="button"
                                                        x-data
                                                        @click="$dispatch('open-edit-product-item-modal', { id: {{ $item->id }} })"
                                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium">
                                                    Modificar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Inventario: por lotes = batches + batch_items --}}
            @if($product->isBatch())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Lotes y variantes</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cada lote agrupa variantes (atributos) con cantidad y costo.</p>
                    </div>
                    @if($product->batches->isEmpty())
                        <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">No hay lotes en inventario.</div>
                    @else
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($product->batches as $batch)
                                <div class="p-6">
                                    <div class="flex flex-wrap items-center gap-2 mb-3">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $batch->reference }}</span>
                                        @if($batch->expiration_date)
                                            <span class="text-xs text-amber-600 dark:text-amber-400">Vence: {{ $batch->expiration_date->format('d/m/Y') }}</span>
                                        @endif
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Total: {{ $batch->batchItems->sum('quantity') }} uds</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                            <thead class="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variante / Atributos</th>
                                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Costo unit.</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($batch->batchItems as $bi)
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                                            @if(!empty($bi->features) && is_array($bi->features))
                                                                {{ collect($bi->features)->map(fn($v, $k) => "{$k}: {$v}")->implode(', ') }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ $bi->quantity }}</td>
                                                        <td class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">{{ number_format($bi->unit_cost, 2) }} €</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if(!$product->isSerialized() && !$product->isBatch())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Este producto no tiene control de inventario por unidades (serializado o por lotes).</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
