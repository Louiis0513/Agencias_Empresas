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
            @if($product->type === 'serialized' || $product->isSerialized())
                <livewire:edit-product-item-modal :store-id="$store->id" :product-id="$product->id" />
                <livewire:add-initial-stock-serialized-modal :store-id="$store->id" :product-id="$product->id" />
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Unidades serializadas ({{ $product->productItems->count() }})</h3>
                            <button type="button"
                                    x-on:click="$dispatch('open-modal', 'add-initial-stock-serialized')"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Añadir stock inicial
                            </button>
                        </div>
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
                                ]);
                            }
                            $uniqueVariants->get($key)->total_quantity += (int) $bi->quantity;
                            if ($uniqueVariants->get($key)->price === null && $bi->price !== null) {
                                $uniqueVariants->get($key)->price = $bi->price;
                            }
                        }
                        $uniqueVariants = $uniqueVariants->values();
                    @endphp
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Variantes permitidas</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Marca las opciones que tendrá este producto. En compras solo se podrá elegir entre estas (evita escribir "S" o "Small" a mano). Si no marcas ninguna, se permiten todas las opciones de la categoría.</p>
                        </div>
                        <form method="POST" action="{{ route('stores.productos.variant-options.update', [$store, $product]) }}" class="p-6">
                            @csrf
                            @method('PUT')
                            <div class="space-y-4">
                                @foreach($product->category->attributes as $attr)
                                    <div>
                                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $attr->name }}</span>
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($attr->options as $opt)
                                                <label class="inline-flex items-center">
                                                    <input type="checkbox" name="attribute_option_ids[]" value="{{ $opt->id }}"
                                                           {{ $product->allowedVariantOptions->contains('id', $opt->id) ? 'checked' : '' }}
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $opt->value }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <button type="button"
                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'detalles-variantes-lote', bubbles: true }))"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                    Detalles
                                </button>
                                <button type="submit" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                    Guardar variantes permitidas
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Modal Detalles: datos del producto y tabla de variantes existentes por lote --}}
                    <x-modal name="detalles-variantes-lote" focusable maxWidth="4xl">
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Detalles del producto y variantes</h2>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6 p-4 bg-gray-50 dark:bg-gray-900/40 rounded-lg">
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Nombre</dt>
                                    <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">SKU</dt>
                                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $product->sku ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Código de barras</dt>
                                    <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">{{ $product->barcode ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                                    <dd class="mt-0.5">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Variantes del producto</h3>
                            @if($uniqueVariants->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">Aún no hay variantes en inventario.</p>
                            @else
                                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-900">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variante / Atributos</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total en inventario</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($uniqueVariants as $uv)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                        @if(!empty($uv->features) && is_array($uv->features))
                                                            @php
                                                                $parts = collect($uv->features)->map(function ($v, $k) use ($attrById) {
                                                                    $attr = $attrById->get((int) $k) ?? $attrById->get((string) $k);
                                                                    $name = $attr ? $attr->name : (string) $k;
                                                                    return $name . ': ' . $v;
                                                                })->values();
                                                            @endphp
                                                            {{ $parts->implode(', ') }}
                                                            <span class="text-gray-500 dark:text-gray-400 text-xs ml-1">({{ $uv->total_quantity }} uds)</span>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ $uv->total_quantity }} uds</td>
                                                    <td class="px-4 py-3 text-right">
                                                        <button type="button"
                                                                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'modificar-variante-{{ $loop->index }}', bubbles: true }))"
                                                                class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                                            Modificar
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                            <div class="mt-6 flex flex-wrap gap-3 justify-end">
                                <button type="button"
                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'crear-variantes-lote', bubbles: true }))"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                    Crear variantes
                                </button>
                                <button type="button"
                                        x-on:click="$dispatch('close-modal', 'detalles-variantes-lote')"
                                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm font-medium rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </x-modal>

                    {{-- Modal Modificar variante (uno por variante única): prellenar atributos de la categoría y permitir abrir Crear variantes --}}
                    @foreach($uniqueVariants as $uv)
                        <x-modal name="modificar-variante-{{ $loop->index }}" focusable maxWidth="2xl">
                            <div class="p-6">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Modificar variante</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Edita los atributos de esta variante. Si la categoría tiene nuevos atributos, aparecerán aquí para asignarlos.</p>
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
                                                <x-input-label for="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" :value="$currentValue" />
                                            </div>
                                        @elseif($attr->type === 'number')
                                            <div>
                                                <x-input-label for="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" :value="$currentValue" />
                                            </div>
                                        @elseif($attr->type === 'select')
                                            <div>
                                                <x-input-label for="mod-var-{{ $loop->parent->index }}-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" />
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
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $attr->name }}{{ $isRequired ? ' *' : '' }}</span>
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                    {{-- Precio al público de la variante --}}
                                    <div class="pt-3 border-t border-gray-200 dark:border-gray-600">
                                        <x-input-label for="mod-var-{{ $loop->index }}-price" value="{{ __('Precio al público') }}" />
                                        <x-text-input name="price" id="mod-var-{{ $loop->index }}-price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" value="{{ $uv->price !== null && $uv->price !== '' ? number_format((float) $uv->price, 2, '.', '') : '' }}" />
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
                        <div class="p-6">
                            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Crear variantes</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Completa los atributos según la categoría del producto. Los marcados con * son obligatorios.</p>
                            <form method="POST" action="{{ route('stores.productos.variants.store', [$store, $product]) }}" class="space-y-4" id="form-crear-variantes-lote">
                                @csrf
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/40">
                                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Nueva variante</h3>
                                    @foreach($product->category->attributes as $attr)
                                        @php $isRequired = $attr->pivot->is_required ?? $attr->is_required ?? false; @endphp
                                        @if($attr->type === 'text')
                                            <div class="mb-3">
                                                <x-input-label for="crear-var-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="crear-var-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                            </div>
                                        @elseif($attr->type === 'number')
                                            <div class="mb-3">
                                                <x-input-label for="crear-var-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" />
                                                <x-text-input name="attribute_values[{{ $attr->id }}]" id="crear-var-attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" />
                                            </div>
                                        @elseif($attr->type === 'select')
                                            <div class="mb-3">
                                                <x-input-label for="crear-var-attr-{{ $attr->id }}" :value="$attr->name . ($isRequired ? ' *' : '')" />
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
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $attr->name }}{{ $isRequired ? ' *' : '' }}</span>
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                    {{-- Precio --}}
                                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
                                        <x-input-label for="crear-var-price" value="{{ __('Precio al público') }}" />
                                        <x-text-input name="price" id="crear-var-price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                    </div>
                                    {{-- Opcional: stock inicial (como en crear producto lote) --}}
                                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600" x-data="{ hasStock: false }">
                                        <label class="flex items-center gap-2 mb-3">
                                            <input type="checkbox" name="has_stock" value="1" x-model="hasStock"
                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tiene stock inicial</span>
                                        </label>
                                        <div x-show="hasStock" x-cloak x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <x-input-label for="crear-var-cost" value="{{ __('Costo') }}" />
                                                <x-text-input name="cost" id="crear-var-cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                            </div>
                                            <div>
                                                <x-input-label for="crear-var-stock-initial" value="{{ __('Stock inicial') }}" />
                                                <x-text-input name="stock_initial" id="crear-var-stock-initial" class="block mt-1 w-full" type="number" min="0" placeholder="0" />
                                            </div>
                                            <div>
                                                <x-input-label for="crear-var-batch-number" value="{{ __('Número de lote') }}" />
                                                <x-text-input name="batch_number" id="crear-var-batch-number" class="block mt-1 w-full" type="text" placeholder="Ej: L-001" />
                                            </div>
                                            <div>
                                                <x-input-label for="crear-var-expiration" value="{{ __('Fecha de vencimiento') }}" />
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
