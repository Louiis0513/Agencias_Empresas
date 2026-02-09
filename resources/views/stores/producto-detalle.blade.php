<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $product->name }} - {{ $store->name }}
            </h2>
            <div class="flex items-center gap-3">
                @if(!$product->isBatch() && !$product->isSerialized())
                    <button type="button" 
                            x-data
                            @click="window.dispatchEvent(new CustomEvent('open-edit-product-modal', { detail: { id: {{ $product->id }} }, bubbles: true }))" 
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Editar producto
                    </button>
                @endif
                <a href="{{ route('stores.products', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    ← Volver a Productos
                </a>
            </div>
        </div>
    </x-slot>

    <livewire:edit-product-modal :store-id="$store->id" />

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
                                Serializado ({{ $product->type }})
                            @elseif($product->isBatch())
                                Por lotes ({{ $product->type }})
                            @else
                                Simple (sin variantes; en inventario por lotes de compra)
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
                    @if(!$product->isBatch())
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="mt-0.5">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </dd>
                    </div>
                    @endif
                    @if(!$product->isBatch() && !$product->isSerialized())
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Precio</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ number_format($product->price, 2) }} €</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Costo (ref.)</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ number_format($product->cost, 2) }} €</dd>
                    </div>
                    @endif
                    @if(!$product->isBatch() && !$product->isSerialized() && $product->attributeValues->isNotEmpty())
                        @foreach($product->attributeValues as $attrValue)
                            @if($attrValue->attribute)
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $attrValue->attribute->name }}</dt>
                                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                                        @if($attrValue->attribute->type === 'boolean')
                                            {{ $attrValue->value === '1' ? 'Sí' : 'No' }}
                                        @else
                                            {{ $attrValue->value ?? '—' }}
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </dl>
            </div>

            {{-- Tabla de variantes (solo producto por lote): Variante, Stock, Costo, Precio, Ver detalles --}}
            @if($product->isBatch())
                @php
                    $attrById = $product->category?->attributes?->keyBy('id') ?? collect();
                    $variantesParaTabla = collect();
                    foreach ($product->batches as $batch) {
                        foreach ($batch->batchItems as $bi) {
                            $features = is_array($bi->features ?? null) ? $bi->features : [];
                            ksort($features);
                            $key = json_encode($features);
                            if (!$variantesParaTabla->has($key)) {
                                $variantesParaTabla->put($key, (object) [
                                    'features' => $bi->features ?? [],
                                    'total_quantity' => 0,
                                    'total_cost' => 0,
                                    'price' => null,
                                    'movimientos' => [],
                                    'is_active' => $bi->is_active ?? true,
                                ]);
                            }
                            $obj = $variantesParaTabla->get($key);
                            $obj->total_quantity += (int) $bi->quantity;
                            $obj->total_cost += (float) $bi->quantity * (float) ($bi->unit_cost ?? 0);
                            if ($obj->price === null && $bi->price !== null) {
                                $obj->price = $bi->price;
                            }
                            if (!isset($obj->is_active)) {
                                $obj->is_active = $bi->is_active ?? true;
                            }
                            $obj->movimientos[] = (object) [
                                'reference' => $batch->reference,
                                'expiration_date' => $batch->expiration_date,
                                'created_at' => $batch->created_at,
                                'quantity' => (int) $bi->quantity,
                                'unit_cost' => (float) ($bi->unit_cost ?? 0),
                            ];
                        }
                    }
                    $variantesParaTabla = $variantesParaTabla->values()->map(function ($v) use ($attrById) {
                        $v->costo_promedio = $v->total_quantity > 0 ? $v->total_cost / $v->total_quantity : 0;
                        $label = '';
                        if (!empty($v->features) && is_array($v->features)) {
                            $parts = collect($v->features)->map(function ($val, $k) use ($attrById) {
                                $attr = $attrById->get((int) $k) ?? $attrById->get((string) $k);
                                $name = $attr ? $attr->name : (string) $k;
                                return $name . ': ' . $val;
                            })->values();
                            $label = $parts->implode(', ');
                        } else {
                            $label = '—';
                        }
                        $v->label = $label;
                        return $v;
                    });
                @endphp
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Variantes del producto</h3>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cada variante tiene su propio stock, costo y precio. Usa «Ver detalles» para modificar o gestionar.</p>
                            </div>
                            @if($product->category && $product->category->attributes->isNotEmpty())
                                <button type="button"
                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'crear-variantes-lote', bubbles: true }))"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    Crear nueva variante
                                </button>
                            @endif
                        </div>
                    </div>
                    @if($variantesParaTabla->isEmpty())
                        <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">Aún no hay variantes en inventario.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variante</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stock</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Costo</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($variantesParaTabla as $vp)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $vp->label }}</td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-gray-100">{{ $vp->total_quantity }}</td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-500 dark:text-gray-400">{{ number_format($vp->costo_promedio, 2) }} €</td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-gray-100">
                                                @if($vp->price !== null && (float)$vp->price > 0)
                                                    {{ number_format((float) $vp->price, 2) }} €
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ ($vp->is_active ?? true) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                    {{ ($vp->is_active ?? true) ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <button type="button"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'detalle-variante-{{ $loop->index }}', bubbles: true }))"
                                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium text-sm">
                                                    Ver detalles
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Modal Ver detalles por variante: vista tipo producto simple + movimientos de inventario --}}
                @foreach($variantesParaTabla as $vp)
                    <x-modal name="detalle-variante-{{ $loop->index }}" focusable maxWidth="4xl">
                        <div class="p-6 bg-white dark:bg-gray-800">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-600">Detalle de la variante</h2>
                            {{-- Resumen como producto simple: Variante, Stock, Costo, Precio, Ubicación --}}
                            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6 p-4 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div>
                                    <dt class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Variante</dt>
                                    <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $vp->label }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Stock</dt>
                                    <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $vp->total_quantity }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Costo</dt>
                                    <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ number_format($vp->costo_promedio, 2) }} €</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Precio</dt>
                                    <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                                        @if($vp->price !== null && (float)$vp->price > 0)
                                            {{ number_format((float) $vp->price, 2) }} €
                                        @else
                                            <span class="text-gray-600 dark:text-gray-400">—</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Ubicación</dt>
                                    <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $product->location ?? '—' }}</dd>
                                </div>
                            </dl>
                            {{-- Movimientos de inventario de esta variante (ingresos por lote: coste, fecha caducidad) --}}
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">Movimientos de inventario</h3>
                            @if(empty($vp->movimientos))
                                <p class="text-sm text-gray-500 dark:text-gray-400">No hay movimientos registrados para esta variante.</p>
                            @else
                                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <thead class="bg-gray-100 dark:bg-gray-900">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Fecha</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Referencia lote</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Cantidad</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Costo unit.</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Fecha caducidad</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($vp->movimientos as $mov)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100 font-medium">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100 font-medium">{{ $mov->reference }}</td>
                                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100 font-medium">{{ $mov->quantity }}</td>
                                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100 font-medium">{{ number_format($mov->unit_cost, 2) }} €</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100 font-medium">
                                                        @if($mov->expiration_date)
                                                            {{ $mov->expiration_date->format('d/m/Y') }}
                                                        @else
                                                            <span class="text-gray-600 dark:text-gray-400">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <div class="mt-6 flex justify-end gap-3">
                                @if($product->category && $product->category->attributes->isNotEmpty())
                                    <button type="button"
                                            x-on:click="$dispatch('close-modal', 'detalle-variante-{{ $loop->index }}'); window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modificar-variante-{{ $loop->index }}', bubbles: true }))"
                                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                        Editar variante
                                    </button>
                                @endif
                                <button type="button"
                                        x-on:click="$dispatch('close-modal', 'detalle-variante-{{ $loop->index }}')"
                                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </x-modal>
                @endforeach
            @endif

            {{-- Inventario: serializado = product_items --}}
            @if($product->type === 'serialized' || $product->isSerialized())
                <livewire:edit-product-item-modal :store-id="$store->id" :product-id="$product->id" />
                <livewire:add-initial-stock-serialized-modal :store-id="$store->id" :product-id="$product->id" />
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Unidades serializadas ({{ $product->productItems->count() }})</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cada fila es una unidad en inventario. Asigna el precio de venta y edita datos con «Modificar».</p>
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
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                                <button type="button"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'ver-item-{{ $item->id }}', bubbles: true }))"
                                                        class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 font-medium">
                                                    Ver
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @php
                            $statusLabelsSerial = \App\Models\ProductItem::estadosDisponibles();
                            $attrNamesById = $product->category && $product->category->attributes->isNotEmpty()
                                ? $product->category->attributes->keyBy('id')
                                : collect();
                        @endphp
                        @foreach($product->productItems as $item)
                            <x-modal name="ver-item-{{ $item->id }}" focusable maxWidth="md">
                                <div class="p-6 bg-white dark:bg-gray-800">
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-600">Unidad serializada</h2>
                                    <dl class="space-y-4 text-sm">
                                        <div class="py-2 border-b border-gray-100 dark:border-gray-700">
                                            <dt class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Nº Serie</dt>
                                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $item->serial_number ?? '—' }}</dd>
                                        </div>
                                        <div class="py-2 border-b border-gray-100 dark:border-gray-700">
                                            <dt class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Costo</dt>
                                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ number_format($item->cost, 2) }} €</dd>
                                        </div>
                                        <div class="py-2 border-b border-gray-100 dark:border-gray-700">
                                            <dt class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Precio venta</dt>
                                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                                                @if($item->price !== null && (float)$item->price > 0)
                                                    {{ number_format($item->price, 2) }} €
                                                @else
                                                    <span class="text-gray-600 dark:text-gray-400">—</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="py-2 border-b border-gray-100 dark:border-gray-700">
                                            <dt class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Estado</dt>
                                            <dd class="mt-1">
                                                <span class="px-2.5 py-1 inline-flex text-xs font-semibold leading-5 rounded-full
                                                    @if($item->status === \App\Models\ProductItem::STATUS_AVAILABLE) bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200
                                                    @elseif($item->status === \App\Models\ProductItem::STATUS_SOLD) bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-200
                                                    @elseif($item->status === \App\Models\ProductItem::STATUS_RESERVED) bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200
                                                    @else bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200
                                                    @endif">
                                                    {{ $statusLabelsSerial[$item->status] ?? $item->status }}
                                                </span>
                                            </dd>
                                        </div>
                                        <div class="py-2 border-b border-gray-100 dark:border-gray-700">
                                            <dt class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Referencia</dt>
                                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">{{ $item->batch ?? '—' }}</dd>
                                        </div>
                                        <div class="py-2">
                                            <dt class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Atributos</dt>
                                            <dd class="mt-1 text-base font-medium text-gray-900 dark:text-gray-100">
                                                @if(!empty($item->features) && is_array($item->features))
                                                    <ul class="list-disc list-inside space-y-0.5">
                                                        @foreach($item->features as $key => $value)
                                                            @php
                                                                $attrId = is_numeric($key) ? (int) $key : $key;
                                                                $attrLabel = $attrNamesById->get($attrId)?->name ?? $key;
                                                            @endphp
                                                            <li><span class="font-semibold text-gray-800 dark:text-gray-200">{{ $attrLabel }}:</span> <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span></li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="text-gray-600 dark:text-gray-400">—</span>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                    <div class="mt-6 pt-4 flex justify-end gap-3 border-t border-gray-200 dark:border-gray-600">
                                        <x-secondary-button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'ver-item-{{ $item->id }}', bubbles: true }))">
                                            Cerrar
                                        </x-secondary-button>
                                        <button type="button"
                                                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'ver-item-{{ $item->id }}', bubbles: true })); window.dispatchEvent(new CustomEvent('open-edit-product-item-modal', { detail: { id: {{ $item->id }} }, bubbles: true }));"
                                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                            Modificar
                                        </button>
                                    </div>
                                </div>
                            </x-modal>
                        @endforeach
                    @endif
                </div>
            @endif

            {{-- Inventario: por lotes = batches + batch_items --}}
            @if($product->isBatch())
                {{-- Variantes permitidas: define qué opciones se podrán elegir al comprar --}}
                @if($product->category && $product->category->attributes->isNotEmpty())
                    @php
                        $attrById = $product->category->attributes->keyBy('id');
                        $batchItemsFlat = $product->batches->flatMap(fn($b) => $b->batchItems);
                        $uniqueVariants = collect();
                        foreach ($batchItemsFlat as $bi) {
                            $features = is_array($bi->features ?? null) ? $bi->features : [];
                            ksort($features);
                            $key = json_encode($features);
                            if (!$uniqueVariants->has($key)) {
                                $uniqueVariants->put($key, (object) [
                                    'features' => $bi->features ?? [],
                                    'total_quantity' => 0,
                                    'price' => null,
                                    'is_active' => $bi->is_active ?? true,
                                ]);
                            }
                            $uniqueVariants->get($key)->total_quantity += (int) $bi->quantity;
                            if ($uniqueVariants->get($key)->price === null && $bi->price !== null) {
                                $uniqueVariants->get($key)->price = $bi->price;
                            }
                        }
                        $uniqueVariants = $uniqueVariants->values();
                    @endphp
                    {{-- Modal Modificar variante (uno por variante única): prellenar atributos de la categoría --}}
                    @foreach($uniqueVariants as $uv)
                        <x-modal name="modificar-variante-{{ $loop->index }}" focusable maxWidth="2xl">
                            <div class="p-6 bg-white dark:bg-gray-800">
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2 pb-2 border-b border-gray-200 dark:border-gray-600">Modificar variante</h2>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Edita los atributos de esta variante. Si la categoría tiene nuevos atributos, aparecerán aquí para asignarlos.</p>
                                <form method="POST" action="{{ route('stores.productos.variant.update', [$store, $product]) }}" class="space-y-4" id="form-modificar-variante-{{ $loop->index }}">
                                    @csrf
                                    @method('PUT')
                                    @foreach($uv->features ?? [] as $attrId => $val)
                                        <input type="hidden" name="old_attribute_values[{{ $attrId }}]" value="{{ $val }}" />
                                    @endforeach
                                    @foreach($product->category->attributes as $attr)
                                        @php
                                            $currentValue = $uv->features[$attr->id] ?? $uv->features[(string)$attr->id] ?? ($attr->type === 'boolean' ? '0' : '');
                                            $isRequired = $attr->pivot->is_required ?? $attr->is_required ?? false;
                                        @endphp
                                        @if($attr->type === 'text')
                                            <div>
                                                <x-input-label for="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" class="dark:text-white font-semibold" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" :value="$currentValue" />
                                            </div>
                                        @elseif($attr->type === 'number')
                                            <div>
                                                <x-input-label for="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" class="dark:text-white font-semibold" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" :value="$currentValue" />
                                            </div>
                                        @elseif($attr->type === 'select')
                                            <div>
                                                <x-input-label for="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" class="dark:text-white font-semibold" />
                                                <select name="attribute_values[{{ $attr->id }}]" id="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}"
                                                        class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">{{ __('Selecciona') }}</option>
                                                    @foreach($attr->options as $opt)
                                                        <option value="{{ $opt->value }}" {{ (string)$currentValue === (string)$opt->value ? 'selected' : '' }}>{{ $opt->value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @elseif($attr->type === 'boolean')
                                            <div>
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="attribute_values[{{ $attr->id }}]" value="1"
                                                           {{ in_array($currentValue, ['1', 1, true], true) ? 'checked' : '' }}
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $attr->name }}{{ $isRequired ? ' *' : '' }}</span>
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                    {{-- Precio al público de la variante --}}
                                    <div class="pt-3 border-t border-gray-200 dark:border-gray-600">
                                        <x-input-label for="mod-var-{{ $loop->index }}-price" value="{{ __('Precio al público') }}" class="dark:text-white font-semibold" />
                                        <x-text-input name="price" id="mod-var-{{ $loop->index }}-price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" value="{{ $uv->price !== null && $uv->price !== '' ? number_format((float) $uv->price, 2, '.', '') : '' }}" />
                                    </div>
                                    {{-- Estado activo de la variante --}}
                                    <div class="pt-3">
                                        <label class="flex items-center gap-2">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1"
                                                   {{ ($uv->is_active ?? true) ? 'checked' : '' }}
                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Activo') }}</span>
                                        </label>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Las variantes inactivas no aparecerán al vender ni en compras.') }}</p>
                                    </div>
                                    <div class="mt-6 flex flex-wrap gap-3 justify-end pt-4">
                                        <button type="button"
                                                x-on:click="$dispatch('close-modal', 'modificar-variante-{{ $loop->index }}')"
                                                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                                            Cerrar
                                        </button>
                                        <button type="submit"
                                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                            Guardar cambios
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </x-modal>
                    @endforeach

                    {{-- Modal Crear variantes: misma estructura que en crear producto (lote) — atributos de la categoría, requeridos/opcionales --}}
                    <x-modal name="crear-variantes-lote" focusable maxWidth="2xl">
                        <div class="p-6 bg-white dark:bg-gray-800">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2 pb-2 border-b border-gray-200 dark:border-gray-600">Crear variantes</h2>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Completa los atributos según la categoría del producto. Los marcados con * son obligatorios.</p>
                            <form method="POST" action="{{ route('stores.productos.variants.store', [$store, $product]) }}" class="space-y-4" id="form-crear-variantes-lote">
                                @csrf
                                <div class="border-2 border-gray-300 dark:border-gray-600 rounded-lg p-5 bg-white dark:bg-gray-800 shadow-sm">
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 pb-3 border-b-2 border-gray-300 dark:border-gray-600">Nueva variante</h3>
                                    @foreach($product->category->attributes as $attr)
                                        @php $isRequired = $attr->pivot->is_required ?? $attr->is_required ?? false; @endphp
                                        @if($attr->type === 'text')
                                            <div class="mb-3">
                                                <x-input-label for="crear-var-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" class="dark:text-gray-200 font-semibold" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="crear-var-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                            </div>
                                        @elseif($attr->type === 'number')
                                            <div class="mb-3">
                                                <x-input-label for="crear-var-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" class="dark:text-gray-200 font-semibold" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="crear-var-attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" />
                                            </div>
                                        @elseif($attr->type === 'select')
                                            <div class="mb-3">
                                                <x-input-label for="crear-var-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" class="dark:text-gray-200 font-semibold" />
                                                <select name="attribute_values[{{ $attr->id }}]" id="crear-var-attr-{{ $attr->id }}"
                                                        class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">{{ __('Selecciona') }}</option>
                                                    @foreach($attr->options as $opt)
                                                        <option value="{{ $opt->value }}">{{ $opt->value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @elseif($attr->type === 'boolean')
                                            <div class="mb-3">
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="attribute_values[{{ $attr->id }}]" value="1"
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-200">{{ $attr->name }}{{ $isRequired ? ' *' : '' }}</span>
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                    {{-- Precio --}}
                                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
                                        <x-input-label for="crear-var-price" value="{{ __('Precio al público') }}" class="dark:text-gray-200 font-semibold" />
                                        <x-text-input name="price" id="crear-var-price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                    </div>
                                    {{-- Opcional: stock inicial (como en crear producto lote) --}}
                                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600" x-data="{ hasStock: false }">
                                        <label class="flex items-center gap-2 mb-3">
                                            <input type="checkbox" name="has_stock" value="1" x-model="hasStock"
                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-200">Tiene stock inicial</span>
                                        </label>
                                        <div x-show="hasStock" x-cloak x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label for="crear-var-cost" value="{{ __('Costo') }}" class="dark:text-gray-200 font-semibold" />
                                                <x-text-input name="cost" id="crear-var-cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                            </div>
                                            <div>
                                                <x-input-label for="crear-var-stock-initial" value="{{ __('Stock inicial') }}" class="dark:text-gray-200 font-semibold" />
                                                <x-text-input name="stock_initial" id="crear-var-stock-initial" class="block mt-1 w-full" type="number" min="0" placeholder="0" />
                                            </div>
                                            <div>
                                                <x-input-label for="crear-var-batch-number" value="{{ __('Número de lote') }}" class="dark:text-gray-200 font-semibold" />
                                                <x-text-input name="batch_number" id="crear-var-batch-number" class="block mt-1 w-full" type="text" placeholder="Ej: L-001" />
                                            </div>
                                            <div>
                                                <x-input-label for="crear-var-expiration" value="{{ __('Fecha de vencimiento') }}" class="dark:text-gray-200 font-semibold" />
                                                <x-text-input name="expiration_date" id="crear-var-expiration" class="block mt-1 w-full" type="date" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6 flex flex-wrap gap-3 justify-end">
                                    <button type="button"
                                            x-on:click="$dispatch('close-modal', 'crear-variantes-lote')"
                                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                                        Cerrar
                                    </button>
                                    <button type="submit"
                                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                        Crear variante
                                    </button>
                                </div>
                            </form>
                        </div>
                    </x-modal>
                @endif

            @endif

            {{-- Inventario: simple = también por lotes (cada compra = un lote con cantidad y costo) --}}
            @if(($product->type === 'simple' || empty($product->type)) && $product->batches->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Lotes (entradas por compra)</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Producto simple: cada lote es una entrada (compra). Se muestra la referencia y el costo al que llegó.</p>
                    </div>
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
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Costo unit.</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($batch->batchItems as $bi)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
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
                </div>
            @endif

            @if(!$product->isSerialized() && !$product->isBatch() && ($product->type !== 'simple' && $product->type !== null && $product->type !== ''))
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Este producto no tiene control de inventario por unidades (serializado o por lotes).</p>
                </div>
            @endif
            @if(($product->type === 'simple' || empty($product->type)) && $product->batches->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Producto simple. Aún no hay lotes en inventario (entradas por compra).</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
