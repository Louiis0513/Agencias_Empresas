<div x-on:open-modal.window="if ($event.detail === 'create-invoice') { $wire.resetFormulario(); }">
    <x-modal name="create-invoice" focusable maxWidth="4xl" contentClass="bg-slate-900 border border-slate-700 shadow-2xl">
        <form wire:submit.prevent="save" class="p-8 text-slate-200">
            {{-- Título con borde inferior para separar --}}
            <div class="border-b border-slate-700 pb-4 mb-6">
                <h2 class="text-2xl font-bold text-white tracking-tight">
                    {{ __('Crear Factura') }}
                </h2>
                <p class="text-sm text-slate-400 mt-1">Complete los detalles de la venta y gestione los pagos.</p>
            </div>

            <div class="space-y-8">
                {{-- Sección: Cliente --}}
                <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700">
                    <x-input-label value="{{ __('Cliente') }} *" class="text-slate-300 font-semibold mb-2" />
                    @if($clienteSeleccionado)
                        <div class="p-4 bg-slate-800 border-l-4 border-indigo-500 rounded-r-lg shadow-inner">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-indigo-400 text-lg">{{ $clienteSeleccionado['name'] }}</p>
                                    <div class="flex space-x-4 mt-1 text-sm text-slate-300">
                                        @if($clienteSeleccionado['document_number'])
                                            <span class="flex items-center"><svg class="w-4 h-4 mr-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg> {{ $clienteSeleccionado['document_number'] }}</span>
                                        @endif
                                        @if($clienteSeleccionado['phone'])
                                            <span class="flex items-center"><svg class="w-4 h-4 mr-1 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg> {{ $clienteSeleccionado['phone'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" wire:click="limpiarCliente" class="p-2 text-slate-400 hover:text-red-400 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>
                    @else
                        <button type="button" wire:click="abrirModalCliente" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg transition-all shadow-lg shadow-indigo-500/20 uppercase text-xs tracking-widest">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Seleccionar cliente
                        </button>
                    @endif
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-2 text-red-400" />
                </div>

                {{-- Sección: Productos --}}
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <x-input-label value="{{ __('Productos en la Factura') }}" class="text-slate-300 font-semibold" />
                        <button type="button" wire:click="abrirSelectorProducto" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-md transition-all uppercase tracking-widest shadow-lg shadow-emerald-500/20">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Agregar producto
                        </button>
                    </div>

                    @if(count($productosSeleccionados) > 0)
                        <div class="overflow-hidden rounded-xl border border-slate-700 bg-slate-800/30">
                            <table class="min-w-full divide-y divide-slate-700">
                                <thead class="bg-slate-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400">Producto</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400">Precio</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400">Cant.</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400">Subtotal</th>
                                        <th class="px-4 py-3 text-center text-xs font-bold uppercase text-slate-400">—</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700 bg-slate-900/40">
                                    @foreach($productosSeleccionados as $index => $producto)
                                        <tr class="hover:bg-slate-800/50 transition-colors">
                                            <td class="px-4 py-4">
                                                <div class="text-sm font-medium text-white">{{ $producto['name'] }}</div>
                                                @if(!empty($producto['variant_display_name']))
                                                    <div class="text-xs text-indigo-400 font-medium">{{ $producto['variant_display_name'] }}</div>
                                                @endif
                                                @if(!empty($producto['serial_numbers']) && is_array($producto['serial_numbers']))
                                                    <div class="text-[10px] text-slate-400 mt-1 uppercase leading-tight bg-slate-800 p-1 rounded">S/N: {{ implode(', ', $producto['serial_numbers']) }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-sm text-slate-300">${{ number_format($producto['price'], 2) }}</td>
                                            <td class="px-4 py-4">
                                                @if(($producto['type'] ?? 'simple') === 'serialized')
                                                    <span class="px-3 py-1 bg-slate-700 rounded text-white font-bold text-sm">{{ $producto['quantity'] }}</span>
                                                @else
                                                    <input type="number" wire:change="actualizarCantidad({{ $index }}, $event.target.value)" value="{{ $producto['quantity'] }}" min="1"
                                                        class="w-16 rounded bg-slate-800 border-slate-600 text-white focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-sm font-bold text-white">${{ number_format($producto['subtotal'], 2) }}</td>
                                            <td class="px-4 py-4 text-center">
                                                <button type="button" wire:click="eliminarProducto({{ $index }})" class="text-red-400 hover:text-red-300 p-1">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                         <div class="py-8 text-center bg-slate-800/20 border-2 border-dashed border-slate-700 rounded-xl">
                            <p class="text-slate-500 italic">No hay productos agregados a esta factura.</p>
                         </div>
                    @endif
                    <x-input-error :messages="$errors->get('productosSeleccionados')" class="mt-2 text-red-400" />
                </div>

                {{-- Totales y Descuento --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start pt-6 border-t border-slate-700">
                    <div class="grid grid-cols-2 gap-4 bg-slate-800/30 p-4 rounded-xl border border-slate-700">
                        <div class="col-span-1">
                            <x-input-label value="Tipo Descuento" class="text-slate-400 text-xs uppercase" />
                            <select wire:model.live="discountType" class="block mt-1 w-full rounded-md border-slate-600 bg-slate-800 text-white text-sm">
                                <option value="amount">Monto ($)</option>
                                <option value="percent">Porcentaje (%)</option>
                            </select>
                        </div>
                        <div class="col-span-1">
                            <x-input-label value="Valor" class="text-slate-400 text-xs uppercase" />
                            <x-text-input wire:model.live="discountValue" type="number" step="0.01" class="block mt-1 w-full border-slate-600 bg-slate-800 text-white text-sm" />
                        </div>
                    </div>

                    <div class="bg-indigo-900/20 p-6 rounded-xl border border-indigo-500/30">
                        <div class="space-y-2">
                            <div class="flex justify-between text-slate-400 uppercase text-xs font-bold tracking-wider">
                                <span>Subtotal</span>
                                <span>${{ number_format($subtotal, 2) }}</span>
                            </div>
                            @if($discount > 0)
                                <div class="flex justify-between text-red-400 font-medium">
                                    <span>Descuento</span>
                                    <span>-${{ number_format($discount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between border-t border-indigo-500/50 pt-3 mt-3">
                                <span class="text-xl font-bold text-white">TOTAL</span>
                                <span class="text-2xl font-black text-indigo-400">${{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('total')" class="mt-2 text-red-400" />
                    </div>
                </div>

                {{-- Estado y Pagos --}}
                <div class="space-y-4 pt-4">
                    <div>
                        <x-input-label for="status" value="Estado de la Factura" class="text-slate-300 font-semibold mb-2" />
                        <select wire:model.live="status" id="status" class="block w-full rounded-lg border-slate-600 bg-slate-800 text-white font-bold py-3">
                            <option value="PAID">✅ PAGADA (Registrar cobro)</option>
                            <option value="PENDING">⏳ PENDIENTE (Cuenta por cobrar)</option>
                        </select>
                    </div>

                    @if($status === 'PAID')
                        <div class="p-6 bg-slate-800/80 rounded-2xl border border-slate-600 shadow-xl animate-in fade-in zoom-in duration-300">
                            <div class="flex items-center justify-between mb-6">
                                <h4 class="text-white font-bold flex items-center">
                                    <span class="w-2 h-6 bg-indigo-500 rounded mr-3"></span>
                                    Desglose de Pagos
                                </h4>
                                <button type="button" wire:click="agregarPago" class="text-sm font-bold text-indigo-400 hover:text-indigo-300 bg-indigo-500/10 px-3 py-1 rounded-full transition-colors">
                                    + Añadir Método
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('paymentParts')" class="mb-4 text-red-400" />

                            <div class="space-y-4">
                                @foreach($paymentParts as $index => $part)
                                    @php
                                        $method = $part['method'] ?? 'CASH';
                                        $amt = (float)($part['amount'] ?? 0);
                                        $rec = (float)($part['recibido'] ?? 0);
                                        $vuelto = ($method === 'CASH' && $rec >= $amt && $amt > 0) ? round($rec - $amt, 2) : null;
                                    @endphp
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-slate-900/50 rounded-xl border border-slate-700 relative">
                                        <div class="md:col-span-1">
                                            <label class="text-[10px] uppercase font-black text-slate-500">Método</label>
                                            <select wire:model.live="paymentParts.{{ $index }}.method" wire:change="actualizarMetodoPago({{ $index }})"
                                                class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm focus:ring-indigo-500">
                                                <option value="CASH">Efectivo</option>
                                                <option value="CARD">Tarjeta</option>
                                                <option value="TRANSFER">Transferencia</option>
                                            </select>
                                        </div>
                                        <div class="md:col-span-1">
                                            <label class="text-[10px] uppercase font-black text-slate-500">Monto Cobrado</label>
                                            <input type="number" wire:model.blur="paymentParts.{{ $index }}.amount" step="0.01" min="0" placeholder="0.00"
                                                class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm font-bold">
                                            <x-input-error :messages="$errors->get('paymentParts.' . $index . '.amount')" class="mt-0.5 text-red-400 text-xs" />
                                        </div>
                                        <div class="md:col-span-1">
                                            <label class="text-[10px] uppercase font-black text-slate-500">Caja / Cuenta</label>
                                            <select wire:model="paymentParts.{{ $index }}.bolsillo_id" class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm">
                                                <option value="0">Seleccionar...</option>
                                                @foreach($this->bolsillosParaMetodo($part['method'] ?? 'CASH') as $b)
                                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('paymentParts.' . $index . '.bolsillo_id')" class="mt-0.5 text-red-400 text-xs" />
                                        </div>
                                        <div class="flex flex-col items-end justify-end gap-1 pb-1">
                                            @if($method === 'CASH')
                                                <div class="w-full">
                                                    <label class="text-[10px] uppercase font-black text-slate-500">Recibido (vuelto)</label>
                                                    <input type="number" wire:model.blur="paymentParts.{{ $index }}.recibido" step="0.01" min="0" placeholder="0.00"
                                                        class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm font-bold">
                                                    @if($vuelto !== null)
                                                        <p class="mt-0.5 text-xs font-bold text-emerald-400">Vuelto: ${{ number_format($vuelto, 2) }}</p>
                                                    @endif
                                                    <x-input-error :messages="$errors->get('paymentParts.' . $index . '.recibido')" class="mt-0.5 text-red-400 text-xs" />
                                                </div>
                                            @endif
                                            @if(count($paymentParts) > 1)
                                                <button type="button" wire:click="quitarPago({{ $index }})" class="text-red-500 hover:bg-red-500/10 p-2 rounded-lg transition-colors">
                                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Barra de cuadre de caja --}}
                            <div class="mt-6 p-4 bg-slate-900 rounded-xl flex items-center justify-between border border-slate-700">
                                <div class="text-sm font-bold text-slate-400 italic">Resumen de cuadre:</div>
                                <div class="flex space-x-6">
                                    <div class="text-center">
                                        <p class="text-[10px] text-slate-500 uppercase font-black">Pagado</p>
                                        <p class="text-lg font-bold text-white">${{ number_format($this->totalPagado, 2) }}</p>
                                    </div>
                                    <div class="text-center border-l border-slate-700 pl-6">
                                        <p class="text-[10px] text-slate-500 uppercase font-black">Estado</p>
                                        @if(abs($this->diferenciaPago) < 0.01)
                                            <p class="text-lg font-bold text-emerald-400 flex items-center">
                                                <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                Cuadrado
                                            </p>
                                        @else
                                            <p class="text-lg font-bold text-amber-500 animate-pulse">
                                                {{ $this->diferenciaPago > 0 ? 'Faltan' : 'Sobran' }} ${{ number_format(abs($this->diferenciaPago), 2) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Footer con Botones --}}
            <div class="mt-10 flex flex-col sm:flex-row justify-end items-center gap-4 border-t border-slate-700 pt-6">
                <button type="button" x-on:click="$dispatch('close-modal', 'create-invoice')" 
                    class="w-full sm:w-auto px-6 py-3 text-sm font-bold text-slate-400 hover:text-white transition-colors uppercase tracking-widest">
                    {{ __('Cancelar') }}
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="w-full sm:w-auto px-10 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-black rounded-xl transition-all shadow-xl shadow-indigo-600/30 uppercase tracking-widest disabled:opacity-50">
                    <span wire:loading.remove wire:target="save">{{ __('Emitir Factura') }}</span>
                    <span wire:loading wire:target="save" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Procesando...
                    </span>
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Modal: Buscar y seleccionar cliente --}}
    @if($mostrarModalCliente)
        <div class="fixed inset-0 overflow-y-auto" style="z-index: 100;" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/80 transition-opacity" wire:click="cerrarModalCliente"></div>
                <div class="relative bg-slate-800 rounded-2xl shadow-2xl border border-slate-600 max-w-2xl w-full max-h-[90vh] flex flex-col">
                    <div class="p-6 border-b border-slate-600">
                        <h3 class="text-lg font-bold text-white">Buscar cliente</h3>
                        <p class="text-sm text-slate-400 mt-1">Indica al menos un criterio (nombre, documento o teléfono) y pulsa Buscar.</p>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label for="modal-cliente-nombre" class="block text-xs font-bold text-slate-400 uppercase mb-1">Nombre</label>
                                <input type="text" id="modal-cliente-nombre" wire:model="filtroClienteNombre" placeholder="Ej: Juan Pérez"
                                    class="w-full rounded-lg border-slate-600 bg-slate-900 text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="modal-cliente-documento" class="block text-xs font-bold text-slate-400 uppercase mb-1">Documento</label>
                                <input type="text" id="modal-cliente-documento" wire:model="filtroClienteDocumento" placeholder="Ej: 12345678"
                                    class="w-full rounded-lg border-slate-600 bg-slate-900 text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="modal-cliente-telefono" class="block text-xs font-bold text-slate-400 uppercase mb-1">Teléfono</label>
                                <input type="text" id="modal-cliente-telefono" wire:model="filtroClienteTelefono" placeholder="Ej: 0991234567"
                                    class="w-full rounded-lg border-slate-600 bg-slate-900 text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="button" wire:click="buscarClientes"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg text-sm transition-colors">
                                Buscar
                            </button>
                        </div>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1">
                        @if(count($clientesEncontrados) > 0)
                            <table class="min-w-full divide-y divide-slate-600">
                                <thead class="bg-slate-900/50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400">Documento</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400">Teléfono</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-400">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-600 bg-slate-900/30">
                                    @foreach($clientesEncontrados as $cliente)
                                        <tr class="hover:bg-slate-700/50 transition-colors">
                                            <td class="px-4 py-3 text-sm text-white font-medium">{{ $cliente['name'] }}</td>
                                            <td class="px-4 py-3 text-sm text-slate-400">{{ $cliente['document_number'] ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-slate-400">{{ $cliente['phone'] ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-right">
                                                <button type="button" wire:click="seleccionarCliente({{ $cliente['id'] }})"
                                                    class="text-indigo-400 hover:text-indigo-300 font-bold text-sm">
                                                    Seleccionar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-sm text-slate-500 text-center py-8">
                                @if(!empty(trim($filtroClienteNombre)) || !empty(trim($filtroClienteDocumento)) || !empty(trim($filtroClienteTelefono)))
                                    No se encontraron clientes con los filtros indicados. Prueba con otros criterios.
                                @else
                                    Indica nombre, documento o teléfono y haz clic en «Buscar».
                                @endif
                            </p>
                        @endif
                    </div>
                    <div class="p-4 border-t border-slate-600 flex justify-end">
                        <button type="button" wire:click="cerrarModalCliente"
                            class="px-5 py-2.5 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 font-bold text-sm transition-colors">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>