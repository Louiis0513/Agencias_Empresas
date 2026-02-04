<div>
    {{-- Búsqueda por nombre --}}
    <div class="mb-6">
        <label for="busquedaProducto" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Buscar producto por nombre</label>
        <div class="mt-1 flex gap-2">
            <input type="text"
                   id="busquedaProducto"
                   wire:model="busquedaProducto"
                   wire:keydown.enter.prevent="buscarProductos"
                   placeholder="Escribe el nombre del producto..."
                   class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            <button type="button"
                    wire:click="buscarProductos"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                Buscar
            </button>
        </div>
    </div>

    @if($errorStock)
        <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm">
            {{ $errorStock }}
        </div>
    @endif

    {{-- Resultados de búsqueda --}}
    @if(count($productosEncontrados) > 0)
        <div class="mb-8">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Resultados</h3>
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producto</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stock / Disponibles</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                        @foreach($productosEncontrados as $p)
                            @php
                                $stock = (int) ($p['stock'] ?? 0);
                                $maxQty = max(1, $stock);
                                $isSerialized = ($p['type'] ?? '') === 'serialized';
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" @if(!$isSerialized) x-data="{ qty: 1 }" @endif>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $p['name'] }}</td>
                                <td class="px-4 py-2 text-sm">
                                    @if($isSerialized)
                                        <span class="px-2 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Serializado</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-medium rounded bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">Lote</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ $stock }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ number_format($p['price'] ?? 0, 2) }}</td>
                                <td class="px-4 py-2">
                                    @if($isSerialized)
                                        <span class="text-gray-400 dark:text-gray-500 text-sm">—</span>
                                    @else
                                        <input type="number"
                                               x-model.number="qty"
                                               min="1"
                                               max="{{ $maxQty }}"
                                               class="w-20 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @if($isSerialized)
                                        <button type="button"
                                                wire:click="abrirModalUnidades({{ $p['id'] }})"
                                                class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                            Ver disponibles
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="$wire.agregarAlCarrito({{ $p['id'] }}, Math.min(Math.max(1, qty), {{ $maxQty }}))"
                                                class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                            Agregar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif(!empty(trim($busquedaProducto)))
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">No se encontraron productos con ese nombre.</p>
    @endif

    {{-- Modal: Unidades disponibles (producto serializado) --}}
    @if($productoSerializadoId !== null)
        @php
            $totalUnidades = $unidadesDisponiblesTotal;
            $perPage = $unidadesDisponiblesPerPage ?: 15;
            $maxPage = $totalUnidades > 0 ? (int) ceil($totalUnidades / $perPage) : 1;
            $from = $totalUnidades === 0 ? 0 : ($unidadesDisponiblesPage - 1) * $perPage + 1;
            $to = min($unidadesDisponiblesPage * $perPage, $totalUnidades);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity" wire:click="cerrarModalUnidades"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] flex flex-col">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Seleccionar unidades — {{ $productoSerializadoNombre }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Elige los ítems disponibles que quieres agregar al carrito.</p>
                        {{-- Buscador por serie --}}
                        <div class="mt-3">
                            <label for="modal-buscar-serie" class="sr-only">Buscar por número de serie</label>
                            <input type="text"
                                   id="modal-buscar-serie"
                                   wire:model.live.debounce.400ms="unidadesDisponiblesSearch"
                                   placeholder="Buscar por número de serie..."
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1">
                        @if(count($unidadesDisponibles) > 0)
                            <ul class="space-y-2">
                                @foreach($unidadesDisponibles as $unit)
                                    <li class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <input type="checkbox"
                                               id="serial-{{ $unit['id'] }}"
                                               wire:model.live="serialesSeleccionados"
                                               value="{{ $unit['serial_number'] }}"
                                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                        <label for="serial-{{ $unit['id'] }}" class="flex-1 text-sm text-gray-900 dark:text-gray-100 cursor-pointer">
                                            <span class="font-medium">{{ $unit['serial_number'] }}</span>
                                            @if(!empty($unit['features']) && is_array($unit['features']))
                                                <span class="text-gray-500 dark:text-gray-400 ml-2">— {{ implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($unit['features']), $unit['features'])) }}</span>
                                            @endif
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                @if(!empty(trim($unidadesDisponiblesSearch)))
                                    No hay unidades con ese número de serie.
                                @else
                                    No hay unidades disponibles en este momento.
                                @endif
                            </p>
                        @endif
                    </div>
                    {{-- Paginador (solo si hay resultados) --}}
                    @if($totalUnidades > 0)
                        <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between gap-2 flex-wrap">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Mostrando {{ $from }}-{{ $to }} de {{ $totalUnidades }}
                            </p>
                            <div class="flex items-center gap-1">
                                <button type="button"
                                        wire:click="irAPaginaUnidades({{ $unidadesDisponiblesPage - 1 }})"
                                        @if($unidadesDisponiblesPage <= 1) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Anterior
                                </button>
                                @for($p = max(1, $unidadesDisponiblesPage - 2); $p <= min($maxPage, $unidadesDisponiblesPage + 2); $p++)
                                    <button type="button"
                                            wire:click="irAPaginaUnidades({{ $p }})"
                                            class="px-2 py-1 text-sm rounded {{ $p === $unidadesDisponiblesPage ? 'bg-indigo-600 text-white' : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                        {{ $p }}
                                    </button>
                                @endfor
                                <button type="button"
                                        wire:click="irAPaginaUnidades({{ $unidadesDisponiblesPage + 1 }})"
                                        @if($unidadesDisponiblesPage >= $maxPage) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Siguiente
                                </button>
                            </div>
                        </div>
                    @endif
                    <div class="p-4 border-t border-gray-200 dark:border-gray-600 flex justify-end gap-2">
                        <button type="button"
                                wire:click="cerrarModalUnidades"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Cerrar
                        </button>
                        <button type="button"
                                wire:click="agregarSerializadosAlCarrito"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                @if(empty($serialesSeleccionados)) disabled @endif>
                            Agregar seleccionados ({{ count($serialesSeleccionados) }})
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Carrito --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Carrito</h3>
        @if(count($carrito) > 0)
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producto</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo / Detalle</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio unit.</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quitar</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                        @foreach($carrito as $index => $item)
                            @php
                                $qty = (int) ($item['quantity'] ?? 0);
                                $isSerialized = !empty($item['serial_numbers'] ?? []);
                                $serialNumbers = $item['serial_numbers'] ?? [];
                                $stock = (int) ($item['stock'] ?? 0);
                                $maxQty = max(1, $stock);
                                $prices = $item['prices'] ?? [];
                                $precioUnitTexto = $isSerialized && !empty($prices)
                                    ? number_format((float) $prices[0], 2)
                                    : number_format($item['price'] ?? 0, 2);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $item['name'] }}</td>
                                <td class="px-4 py-2 text-sm">
                                    @if($isSerialized)
                                        <span class="px-2 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200 mr-1">Serializado</span>
                                        <span class="text-gray-600 dark:text-gray-400">{{ $serialNumbers[0] ?? '—' }}</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-medium rounded bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">Lote</span>
                                        <span class="text-gray-500 dark:text-gray-400 text-xs">máx. {{ $stock }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if($isSerialized)
                                        <span class="text-sm font-medium">1</span>
                                    @else
                                        <input type="number"
                                               min="1"
                                               max="{{ $maxQty }}"
                                               value="{{ $qty }}"
                                               @change="$wire.actualizarCantidad({{ $item['product_id'] }}, Math.min(Math.max(1, parseInt($event.target.value) || 1), {{ $maxQty }}))"
                                               class="w-20 rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $precioUnitTexto }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button"
                                            wire:click="quitarLineaCarrito({{ $index }})"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium">
                                        Quitar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">El carrito está vacío. Busca productos y agrégalos.</p>
        @endif
    </div>
</div>
