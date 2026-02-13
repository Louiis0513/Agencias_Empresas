<div x-on:open-modal.window="if ($event.detail === 'create-invoice') { $wire.resetFormulario(); }">
    <x-modal name="create-invoice" focusable maxWidth="4xl">
        <form wire:submit.prevent="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear Factura') }}
            </h2>

            <div class="mt-6 space-y-6">
                {{-- Cliente (Obligatorio) - Buscador --}}
                <div>
                    <x-input-label for="busquedaCliente" value="{{ __('Cliente') }} *" />
                    
                    {{-- Cliente Seleccionado --}}
                    @if($clienteSeleccionado)
                        <div class="mt-2 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-md">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-green-800 dark:text-green-200">{{ $clienteSeleccionado['name'] }}</p>
                                    <p class="text-sm text-green-600 dark:text-green-400">
                                        @if($clienteSeleccionado['document_number'])
                                            <span>Cédula: {{ $clienteSeleccionado['document_number'] }}</span>
                                        @endif
                                        @if($clienteSeleccionado['phone'])
                                            <span class="ml-2">Tel: {{ $clienteSeleccionado['phone'] }}</span>
                                        @endif
                                    </p>
                                </div>
                                <button type="button" 
                                        wire:click="limpiarCliente"
                                        class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @else
                        {{-- Buscador de Clientes --}}
                        <div class="mt-1 flex gap-2">
                            <input type="text" 
                                   wire:model="busquedaCliente" 
                                   id="busquedaCliente" 
                                   class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                                   placeholder="Buscar por nombre, cédula, email o teléfono">
                            <button type="button" 
                                    wire:click="buscarClientes" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Buscar
                            </button>
                        </div>

                        {{-- Resultados de búsqueda de clientes --}}
                        @if(count($clientesEncontrados) > 0)
                            <div class="mt-2 border border-gray-200 dark:border-gray-700 rounded-md max-h-48 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cédula</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Teléfono</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($clientesEncontrados as $cliente)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" wire:click="seleccionarCliente({{ $cliente['id'] }})">
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $cliente['name'] }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $cliente['document_number'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $cliente['phone'] ?? '-' }}</td>
                                                <td class="px-3 py-2 text-sm">
                                                    <button type="button" 
                                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                        Seleccionar
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif(!empty($busquedaCliente) && count($clientesEncontrados) === 0)
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontraron clientes con ese criterio.</p>
                        @endif
                    @endif
                    
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
                </div>

                {{-- Productos: selector por modal (simple / lote / serializado) --}}
                <div>
                    <x-input-label value="{{ __('Productos en la Factura') }}" />
                    <button type="button"
                            wire:click="abrirSelectorProducto"
                            class="mt-1 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Agregar producto
                    </button>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Abre el selector para elegir producto (simple, por variante o por serie).</p>
                </div>

                {{-- Productos Seleccionados --}}
                @if(count($productosSeleccionados) > 0)
                    <div>
                        <x-input-label value="{{ __('Productos en la Factura') }}" />
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Precio Unit.</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cantidad</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Subtotal</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($productosSeleccionados as $index => $producto)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $producto['name'] }}
                                                @if(!empty($producto['variant_display_name']))
                                                    <span class="text-gray-500 dark:text-gray-400"> — {{ $producto['variant_display_name'] }}</span>
                                                @endif
                                                @if(!empty($producto['serial_numbers']) && is_array($producto['serial_numbers']))
                                                    <span class="text-gray-500 dark:text-gray-400 block text-xs mt-0.5">Serie(s): {{ implode(', ', $producto['serial_numbers']) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">${{ number_format($producto['price'], 2) }}</td>
                                            <td class="px-3 py-2 text-sm">
                                                @if(($producto['type'] ?? 'simple') === 'serialized')
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $producto['quantity'] }}</span>
                                                @else
                                                    <input type="number"
                                                           wire:change="actualizarCantidad({{ $index }}, $event.target.value)"
                                                           value="{{ $producto['quantity'] }}"
                                                           min="1"
                                                           class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                ${{ number_format($producto['subtotal'], 2) }}
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <button type="button" 
                                                        wire:click="eliminarProducto({{ $index }})"
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('productosSeleccionados')" class="mt-1" />
                    </div>
                @endif

                {{-- Descuentos --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="discountType" value="{{ __('Tipo de Descuento') }}" />
                        <select wire:model.live="discountType" 
                                id="discountType" 
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="amount">Monto Fijo</option>
                            <option value="percent">Porcentaje</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="discountValue" value="{{ __('Valor del Descuento') }}" />
                        <x-text-input wire:model.live="discountValue" 
                                      id="discountValue" 
                                      type="number" 
                                      step="0.01" 
                                      min="0" 
                                      class="block mt-1 w-full" 
                                      placeholder="{{ $discountType === 'percent' ? 'Ej: 10' : 'Ej: 50.00' }}">
                        </x-text-input>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($discountType === 'percent')
                                Porcentaje (ej: 10 = 10%)
                            @else
                                Monto fijo (ej: 50.00)
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Totales --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="flex justify-end">
                        <div class="w-64 space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            @if($discount > 0)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Descuento:</span>
                                    <span class="font-semibold text-red-600 dark:text-red-400">-${{ number_format($discount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2">
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total:</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">${{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('total')" class="mt-1" />
                </div>

                {{-- Estado --}}
                <div>
                    <x-input-label for="status" value="{{ __('Estado') }}" />
                    <select wire:model.live="status" 
                            id="status" 
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="PAID">Pagada</option>
                        <option value="PENDING">Pendiente</option>
                    </select>
                    @if($status === 'PENDING')
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">La factura se guardará como pendiente de pago. No se registrará movimiento en caja.</p>
                    @endif
                </div>

                @if($status === 'PAID')
                    {{-- Partes del pago (puede ser mixto: efectivo + transferencia, etc.) --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <x-input-label value="{{ __('Pagos (total a cobrar: $') }}{{ number_format($total, 2) }})" />
                            <button type="button" wire:click="agregarPago" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                + Agregar otro pago
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('paymentParts')" class="mt-1" />

                        @foreach($paymentParts as $index => $part)
                            @php
                                $method = $part['method'] ?? 'CASH';
                                $bolsillos = $this->bolsillosParaMetodo($method);
                                $amt = (float)($part['amount'] ?? 0);
                                $rec = (float)($part['recibido'] ?? 0);
                                $vuelto = ($method === 'CASH' && $rec >= $amt && $amt > 0) ? round($rec - $amt, 2) : null;
                                $maxMonto = $this->maxMontoPago($index);
                            @endphp
                            <div wire:key="pago-{{ $part['id'] ?? $index }}" class="mt-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900/50 space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Pago {{ $index + 1 }}</span>
                                    @if(count($paymentParts) > 1)
                                        <button type="button" wire:click="quitarPago({{ $index }})" class="text-red-600 dark:text-red-400 hover:underline text-sm">Quitar</button>
                                    @endif
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Método</label>
                                        <select wire:model.live="paymentParts.{{ $index }}.method"
                                                wire:change="actualizarMetodoPago({{ $index }})"
                                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                            <option value="CASH">Efectivo</option>
                                            <option value="CARD">Tarjeta</option>
                                            <option value="TRANSFER">Transferencia</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Monto</label>
                                        <input type="number"
                                               wire:model.blur="paymentParts.{{ $index }}.amount"
                                               step="0.01"
                                               min="0"
                                               max="{{ $maxMonto }}"
                                               placeholder="0.00"
                                               class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            Máximo: ${{ number_format($maxMonto, 2) }}
                                        </p>
                                        <x-input-error :messages="$errors->get('paymentParts.' . $index . '.amount')" class="mt-0.5" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Bolsillo</label>
                                        <select wire:model="paymentParts.{{ $index }}.bolsillo_id"
                                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                            <option value="0">Selecciona</option>
                                            @foreach($bolsillos as $b)
                                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                                            @endforeach
                                        </select>
                                        @if($bolsillos->isEmpty())
                                            <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                                                {{ $method === 'CASH' ? 'Sin bolsillos de efectivo.' : 'Sin cuentas bancarias.' }}
                                            </p>
                                        @endif
                                        <x-input-error :messages="$errors->get('paymentParts.' . $index . '.bolsillo_id')" class="mt-0.5" />
                                    </div>
                                    @if($method === 'CASH')
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Recibido (ayuda visual)</label>
                                            <input type="number"
                                                   wire:model.blur="paymentParts.{{ $index }}.recibido"
                                                   step="0.01"
                                                   min="{{ max(0, $amt) }}"
                                                   placeholder="0.00"
                                                   class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                            <x-input-error :messages="$errors->get('paymentParts.' . $index . '.recibido')" class="mt-0.5" />
                                            @if($vuelto !== null)
                                                <p class="mt-0.5 text-xs font-semibold text-green-600 dark:text-green-400">Vuelto: ${{ number_format($vuelto, 2) }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-3 flex flex-wrap gap-4 text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Total pagos: <strong>${{ number_format($this->totalPagado, 2) }}</strong></span>
                            @if(abs($this->diferenciaPago) >= 0.01)
                                @if($this->diferenciaPago > 0)
                                    <span class="text-amber-600 dark:text-amber-400">Falta: ${{ number_format($this->diferenciaPago, 2) }}</span>
                                @else
                                    <span class="text-amber-600 dark:text-amber-400">Sobra: ${{ number_format(abs($this->diferenciaPago), 2) }}</span>
                                @endif
                            @else
                                <span class="text-green-600 dark:text-green-400">✓ Cuadra</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-invoice')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button type="submit"
                                 wire:loading.attr="disabled"
                                 wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ __('Crear Factura') }}</span>
                    <span wire:loading wire:target="save">{{ __('Guardando...') }}</span>
                </x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Modal: Unidades disponibles (producto serializado) — solo status AVAILABLE --}}
    @if($productoSerializadoId !== null)
        @php
            $totalUnidades = $unidadesDisponiblesTotal;
            $perPage = $unidadesDisponiblesPerPage ?: 15;
            $maxPage = $totalUnidades > 0 ? (int) ceil($totalUnidades / $perPage) : 1;
            $from = $totalUnidades === 0 ? 0 : ($unidadesDisponiblesPage - 1) * $perPage + 1;
            $to = min($unidadesDisponiblesPage * $perPage, $totalUnidades);
        @endphp
        <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity" wire:click="cerrarModalUnidadesFactura"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] flex flex-col">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Seleccionar unidades — {{ $productoSerializadoNombre }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Solo se muestran unidades con estado <strong>Disponible</strong>. Elige las que quieras agregar a la factura.</p>
                        <div class="mt-3">
                            <label for="modal-factura-buscar-serie" class="sr-only">Buscar por número de serie</label>
                            <input type="text"
                                   id="modal-factura-buscar-serie"
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
                                               id="factura-serial-{{ $unit['id'] }}"
                                               wire:model.live="serialesSeleccionados"
                                               value="{{ $unit['serial_number'] }}"
                                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                                        <label for="factura-serial-{{ $unit['id'] }}" class="flex-1 text-sm text-gray-900 dark:text-gray-100 cursor-pointer">
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
                                    No hay unidades disponibles (estado Disponible) en este momento.
                                @endif
                            </p>
                        @endif
                    </div>
                    @if($totalUnidades > 0)
                        <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between gap-2 flex-wrap">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Mostrando {{ $from }}-{{ $to }} de {{ $totalUnidades }}
                            </p>
                            <div class="flex items-center gap-1">
                                <button type="button"
                                        wire:click="irAPaginaUnidadesFactura({{ $unidadesDisponiblesPage - 1 }})"
                                        @if($unidadesDisponiblesPage <= 1) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Anterior
                                </button>
                                @for($p = max(1, $unidadesDisponiblesPage - 2); $p <= min($maxPage, $unidadesDisponiblesPage + 2); $p++)
                                    <button type="button"
                                            wire:click="irAPaginaUnidadesFactura({{ $p }})"
                                            class="px-2 py-1 text-sm rounded {{ $p === $unidadesDisponiblesPage ? 'bg-indigo-600 text-white' : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                        {{ $p }}
                                    </button>
                                @endfor
                                <button type="button"
                                        wire:click="irAPaginaUnidadesFactura({{ $unidadesDisponiblesPage + 1 }})"
                                        @if($unidadesDisponiblesPage >= $maxPage) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Siguiente
                                </button>
                            </div>
                        </div>
                    @endif
                    <div class="p-4 border-t border-gray-200 dark:border-gray-600 flex justify-end gap-2">
                        <button type="button"
                                wire:click="cerrarModalUnidadesFactura"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Cerrar
                        </button>
                        <button type="button"
                                wire:click="agregarSerializadosAFactura"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                @if(empty($serialesSeleccionados)) disabled @endif>
                            Agregar a la factura ({{ count($serialesSeleccionados) }})
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
