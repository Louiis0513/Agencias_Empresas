<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Productos - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <livewire:create-product-modal :store-id="$store->id" />
    <livewire:edit-product-modal :store-id="$store->id" />

    <div class="py-12" x-data="{ expandedId: null }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Botón crear producto --}}
                    <div class="mb-6 flex justify-end">
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'create-product')"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Producto
                        </button>
                    </div>

                    {{-- Tabla de productos --}}
                    @if($products->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Nombre
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            SKU
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Barcode
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Categoría
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Atributos
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Precio
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Costo
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Stock
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Ubicación
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Tipo
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Estado
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($products as $product)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $product->name }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                                                    {{ $product->sku ?? '—' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                                                    {{ $product->barcode ?? '—' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $product->category?->name ?? '—' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-wrap gap-1 max-w-[12rem]">
                                                    @foreach($product->attributeValues as $av)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200" title="{{ $av->attribute->name }}">
                                                            {{ $av->attribute->name }}: {{ $av->value }}
                                                        </span>
                                                    @endforeach
                                                    @if($product->attributeValues->isEmpty())
                                                        <span class="text-xs text-gray-400">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ number_format($product->price, 2) }} €
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ number_format($product->cost, 2) }} €
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="flex items-center gap-1">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $product->stock }}</span>
                                                    @if($product->isProductoInventario() && $product->stock > 0)
                                                        <button type="button" x-on:click="expandedId = expandedId === {{ $product->id }} ? null : {{ $product->id }}"
                                                                class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-500"
                                                                title="Ver detalle de inventario">
                                                            <svg class="w-4 h-4 transition-transform" :class="expandedId === {{ $product->id }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-500 dark:text-gray-400 max-w-[8rem] truncate" title="{{ $product->location }}">
                                                    {{ $product->location ?? '—' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @php
                                                    $typeLabels = [
                                                        'serialized' => 'Serializado',
                                                        'batch' => 'Por lotes',
                                                    ];
                                                @endphp
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $typeLabels[$product->type ?? ''] ?? $product->type ?? '—' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    {{ $product->is_active
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                    {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                <button x-on:click="$dispatch('open-edit-product-modal', { id: {{ $product->id }} })"
                                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                                                    Editar
                                                </button>
                                                <form method="POST" action="{{ route('stores.products.destroy', [$store, $product]) }}" 
                                                      onsubmit="return confirm('¿Estás seguro de eliminar el producto «{{ $product->name }}»?');"
                                                      class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        {{-- Fila expandible: detalle de lotes / seriales --}}
                                        @if($product->isProductoInventario())
                                            <tr x-show="expandedId === {{ $product->id }}"
                                                class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                                                <td colspan="12" class="px-4 py-4">
                                                    @if($product->isBatch())
                                                        <div class="space-y-3">
                                                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Lotes y variantes</h4>
                                                            @php
                                                                $batchesConStock = $product->batches->filter(fn($b) => $b->batchItems->where('quantity', '>', 0)->isNotEmpty());
                                                            @endphp
                                                            @forelse($batchesConStock as $batch)
                                                                @php $items = $batch->batchItems->where('quantity', '>', 0); @endphp
                                                                <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-3 bg-white dark:bg-gray-800">
                                                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $batch->reference }}</span>
                                                                        @if($batch->expiration_date)
                                                                            <span class="text-xs text-amber-600 dark:text-amber-400">Vence: {{ $batch->expiration_date->format('d/m/Y') }}</span>
                                                                        @endif
                                                                        <span class="text-xs text-gray-500">Total: {{ $items->sum('quantity') }} uds</span>
                                                                    </div>
                                                                    <div class="flex flex-wrap gap-2">
                                                                        @foreach($items as $bi)
                                                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                                                                @if(!empty($bi->features))
                                                                                    {{ collect($bi->features)->map(fn($v, $k) => "$k: $v")->implode(', ') }}:
                                                                                @endif
                                                                                {{ $bi->quantity }} uds × {{ number_format($bi->unit_cost, 2) }} €
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <p class="text-sm text-gray-500 dark:text-gray-400">Sin lotes con stock.</p>
                                                            @endforelse
                                                        </div>
                                                    @else
                                                        {{-- Serializado --}}
                                                        <div class="space-y-2">
                                                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Unidades serializadas</h4>
                                                            @php $disponibles = $product->productItems->where('status', \App\Models\ProductItem::STATUS_AVAILABLE); @endphp
                                                            <div class="flex flex-wrap gap-2">
                                                                @foreach($disponibles->take(20) as $pi)
                                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200" title="{{ $pi->serial_number }} — {{ number_format($pi->cost, 2) }} €">
                                                                        {{ $pi->serial_number }}
                                                                        @if(!empty($pi->features))
                                                                            ({{ collect($pi->features)->implode(', ') }})
                                                                        @endif
                                                                    </span>
                                                                @endforeach
                                                                @if($disponibles->count() > 20)
                                                                    <span class="text-xs text-gray-500">+ {{ $disponibles->count() - 20 }} más</span>
                                                                @endif
                                                            </div>
                                                            @if($disponibles->isEmpty())
                                                                <p class="text-sm text-gray-500 dark:text-gray-400">Sin unidades disponibles.</p>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No hay productos</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Comienza creando tu primer producto para esta tienda.</p>
                            <div class="mt-4">
                                <button type="button"
                                        x-on:click="$dispatch('open-modal', 'create-product')"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Crear Producto
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
