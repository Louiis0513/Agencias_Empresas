<div>
    {{-- Botón selector de productos --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <button type="button"
                wire:click="abrirSelectorProducto"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
            + Agregar producto
        </button>
    </div>

    @if($errorStock)
        <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm">
            {{ $errorStock }}
        </div>
    @endif

    {{-- Pendiente: cantidad para producto simple --}}
    @if($pendienteSimple)
        <div class="mb-6 p-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cantidad para <strong>{{ $pendienteSimple['name'] }}</strong> (máx. {{ $pendienteSimple['stock'] }})</p>
            <div class="flex flex-wrap items-center gap-2">
                <input type="number"
                       wire:model="cantidadSimple"
                       min="1"
                       placeholder="Cantidad"
                       class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm">
                <button type="button"
                        wire:click="confirmarAgregarSimple"
                        wire:target="confirmarAgregarSimple"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                    Agregar al carrito
                </button>
                <button type="button"
                        wire:click="cancelarPendienteSimple"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm">
                    Cancelar
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Precio unit.: {{ number_format($pendienteSimple['price'] ?? 0, 2) }}</p>
        </div>
    @endif

    {{-- Pendiente: cantidad para variante (lote) --}}
    @if($pendienteBatch)
        <div class="mb-6 p-4 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Cantidad para <strong>{{ $pendienteBatch['name'] }}</strong> — {{ $pendienteBatch['variant_display_name'] }} (máx. {{ $pendienteBatch['stock'] }})
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <input type="number"
                       wire:model="cantidadBatch"
                       min="1"
                       placeholder="Cantidad"
                       class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm">
                <button type="button"
                        wire:click="confirmarAgregarVariante"
                        wire:target="confirmarAgregarVariante"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                    Agregar al carrito
                </button>
                <button type="button"
                        wire:click="cancelarPendienteBatch"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm">
                    Cancelar
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Precio unit.: {{ number_format($pendienteBatch['price'] ?? 0, 2) }}</p>
        </div>
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

    {{-- Carrito (factura preliminar) --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Carrito — Factura preliminar</h3>
        @if(count($carrito) > 0)
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producto / Detalle</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio unit.</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quitar</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                        @foreach($carrito as $index => $item)
                            @php
                                $qty = (int) ($item['quantity'] ?? 0);
                                $isSerialized = !empty($item['serial_numbers'] ?? []);
                                $serialNumbers = $item['serial_numbers'] ?? [];
                                $serialFeatures = $item['serial_features'] ?? [];
                                $stock = (int) ($item['stock'] ?? 0);
                                $maxQty = max(1, $stock);
                                $prices = $item['prices'] ?? [];
                                $precioUnit = $isSerialized && !empty($prices) ? (float) $prices[0] : (float) ($item['price'] ?? 0);
                                $subtotal = $precioUnit * $qty;
                                $tipo = $item['type'] ?? 'batch';
                                $detalleTexto = $item['name'];
                                if ($tipo === 'batch' && !empty($item['variant_display_name'] ?? '')) {
                                    $detalleTexto .= ' — ' . $item['variant_display_name'];
                                } elseif ($isSerialized) {
                                    $detalleTexto .= ' · Serie: ' . ($serialNumbers[0] ?? '—');
                                    if (!empty($serialFeatures[0]) && is_array($serialFeatures[0])) {
                                        $detalleTexto .= ' (' . implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($serialFeatures[0]), $serialFeatures[0])) . ')';
                                    }
                                }
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    <span class="font-medium">{{ $item['name'] }}</span>
                                    @if($tipo === 'batch' && !empty($item['variant_display_name'] ?? ''))
                                        <span class="block text-gray-600 dark:text-gray-400 text-xs mt-0.5">— {{ $item['variant_display_name'] }}</span>
                                    @elseif($isSerialized)
                                        <span class="block text-gray-600 dark:text-gray-400 text-xs mt-0.5">Serie: {{ $serialNumbers[0] ?? '—' }}</span>
                                        @if(!empty($serialFeatures[0]) && is_array($serialFeatures[0]))
                                            <span class="block text-gray-500 dark:text-gray-500 text-xs">{{ implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($serialFeatures[0]), $serialFeatures[0])) }}</span>
                                        @endif
                                    @else
                                        <span class="block text-gray-500 dark:text-gray-400 text-xs">Simple</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-sm font-medium">{{ $qty }}</span>
                                    @if(!$isSerialized)
                                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">Para cambiar: quitar y agregar de nuevo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">{{ number_format($precioUnit, 2) }}</td>
                                <td class="px-4 py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($subtotal, 2) }}</td>
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
            <div class="mt-4 flex justify-end items-center gap-4">
            <button type="button"
                        wire:click="enviarAFacturacion"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="enviarAFacturacion">
                        Facturar
                    </span>
                    <span wire:loading wire:target="enviarAFacturacion">
                        Procesando...
                    </span>
                </button>
                <button type="button"
                        wire:click="abrirModalCotizacion"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                    Guardar como cotización
                </button>
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-6 py-4 min-w-[200px]">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($this->carritoTotal, 2) }}</p>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">El carrito está vacío. Haz clic en «Agregar producto» para seleccionar los ítems a vender.</p>
        @endif
    </div>

    {{-- Modal: Guardar como cotización --}}
    @if($mostrarModalCotizacion)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity" wire:click="cerrarModalCotizacion"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Guardar como cotización</h3>

                        @if($errorCotizacion)
                            <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm">
                                {{ $errorCotizacion }}
                            </div>
                        @endif

                        <div class="space-y-4">
                            <div>
                                <label for="cotizacion-nota" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nota <span class="text-red-500">*</span></label>
                                <textarea id="cotizacion-nota"
                                          wire:model="notaCotizacion"
                                          rows="3"
                                          placeholder="Describe la cotización..."
                                          class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            <div>
                                <label for="cotizacion-vence" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de vencimiento (opcional)</label>
                                <input type="date" id="cotizacion-vence"
                                       wire:model="venceAtCotizacion"
                                       min="{{ date('Y-m-d') }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="cotizacion-cliente" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente (opcional)</label>
                                <select id="cotizacion-cliente"
                                        wire:model="customerIdCotizacion"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Sin cliente</option>
                                    @foreach($this->customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button"
                                    wire:click="cerrarModalCotizacion"
                                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                Cancelar
                            </button>
                            <button type="button"
                                    wire:click="guardarComoCotizacion"
                                    wire:target="guardarComoCotizacion"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50">
                                Guardar cotización
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <livewire:create-invoice-modal :store-id="$storeId" />
</div>
