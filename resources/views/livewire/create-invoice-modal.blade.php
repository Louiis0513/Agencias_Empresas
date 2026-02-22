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
                {{-- Sección: Cliente (componente reutilizable CustomerSearchSelect) --}}
                <div class="bg-slate-800/50 p-4 rounded-xl border border-slate-700">
                    <livewire:customer-search-select :store-id="$storeId" :selected-customer-id="$customer_id" emit-event-name="customer-selected" />
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-2 text-red-400" />

                    @if($mostrarCheckVencida && $cotizacion_id)
                        <div class="mt-4 p-4 rounded-xl bg-amber-900/20 border border-amber-600/40">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="confirmarVencida" class="rounded border-amber-600 text-amber-600 focus:ring-amber-500">
                                <span class="text-amber-200 text-sm">Confirmo que deseo facturar esta cotización aunque esté vencida.</span>
                            </label>
                        </div>
                    @endif

                    @if($mostrarEleccionPrecio && $cotizacion_id)
                        <div class="mt-4 p-4 rounded-xl bg-amber-900/30 border border-amber-600/50">
                            <p class="text-amber-200 font-medium mb-2">Los precios han cambiado desde la cotización. Elija cómo facturar:</p>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" wire:click="aplicarPrecioCotizado"
                                    class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-bold">
                                    Mantener precio cotizado
                                </button>
                                <button type="button" wire:click="aplicarPrecioActual"
                                    wire:target="aplicarPrecioActual"
                                    class="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg text-sm font-bold">
                                    Usar precio actual
                                </button>
                            </div>
                            <p class="text-slate-400 text-xs mt-2">Luego vuelva a pulsar «Emitir Factura».</p>
                        </div>
                    @endif
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

                    @if($errorStock)
                        <div class="p-4 rounded-xl bg-red-900/20 border border-red-800/50 text-red-300 text-sm">
                            {{ $errorStock }}
                        </div>
                    @endif

                    {{-- Pendiente: cantidad para producto simple --}}
                    @if($pendienteSimple)
                        <div class="p-4 rounded-xl border border-slate-600 bg-slate-800/50">
                            <p class="text-sm font-medium text-slate-300 mb-2">Cantidad para <strong class="text-white">{{ $pendienteSimple['name'] }}</strong> (máx. {{ $pendienteSimple['stock'] }})</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="number"
                                       wire:model="cantidadSimple"
                                       min="1"
                                       placeholder="Cantidad"
                                       class="w-24 rounded-md border-slate-600 bg-slate-900 text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <button type="button"
                                        wire:click="confirmarAgregarSimpleFactura"
                                        wire:target="confirmarAgregarSimpleFactura"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 text-sm font-bold">
                                    Añadir a la factura
                                </button>
                                <button type="button"
                                        wire:click="cancelarPendienteSimple"
                                        class="px-4 py-2 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 text-sm font-medium">
                                    Cancelar
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Precio unit.: ${{ number_format($pendienteSimple['price'] ?? 0, 2) }}</p>
                        </div>
                    @endif

                    {{-- Pendiente: cantidad para variante (lote) --}}
                    @if($pendienteBatch)
                        <div class="p-4 rounded-xl border border-slate-600 bg-slate-800/50">
                            <p class="text-sm font-medium text-slate-300 mb-2">
                                Cantidad para <strong class="text-white">{{ $pendienteBatch['name'] }}</strong> — {{ $pendienteBatch['variant_display_name'] }} (máx. {{ $pendienteBatch['stock'] }})
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="number"
                                       wire:model="cantidadBatch"
                                       min="1"
                                       placeholder="Cantidad"
                                       class="w-24 rounded-md border-slate-600 bg-slate-900 text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <button type="button"
                                        wire:click="confirmarAgregarVarianteFactura"
                                        wire:target="confirmarAgregarVarianteFactura"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 text-sm font-bold">
                                    Añadir a la factura
                                </button>
                                <button type="button"
                                        wire:click="cancelarPendienteBatch"
                                        class="px-4 py-2 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 text-sm font-medium">
                                    Cancelar
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Precio unit.: ${{ number_format($pendienteBatch['price'] ?? 0, 2) }}</p>
                        </div>
                    @endif

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
                                            <td class="px-4 py-4 text-sm text-slate-300">
                                                @if($producto['precio_bloqueado'] ?? false)
                                                    <span class="inline-flex items-center gap-1" title="Precio fijado por cotización (no editable)">
                                                        ${{ number_format($producto['price'], 2) }}
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                                    </span>
                                                @else
                                                    ${{ number_format($producto['price'], 2) }}
                                                @endif
                                            </td>
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
                                        $bid = (int)($part['bolsillo_id'] ?? 0);
                                        // Usamos el array que preparamos en PHP para saber si es banco
                                        $isBank = in_array($bid, $this->bolsillosBancariosIds);
                                        
                                        $amt = (float)($part['amount'] ?? 0);
                                        $rec = (float)($part['recibido'] ?? 0);
                                        // Vuelto solo aplica si NO es banco, se recibió algo y cubre el monto
                                        $vuelto = (!$isBank && $rec >= $amt && $amt > 0) ? ($rec - $amt) : null;
                                    @endphp

                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 p-4 bg-slate-900/50 rounded-xl border border-slate-700 relative">
                                        
                                        {{-- 1. SELECCIÓN DE BOLSILLO (Reemplaza al selector de Método) --}}
                                        <div class="md:col-span-5">
                                            <label class="text-[10px] uppercase font-black text-slate-500">Destino (Caja/Banco)</label>
                                            <select wire:model.live="paymentParts.{{ $index }}.bolsillo_id" 
                                                    class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm focus:ring-indigo-500">
                                                <option value="">Seleccionar destino...</option>
                                                @foreach($todosLosBolsillos as $b)
                                                    <option value="{{ $b['id'] }}">
                                                        {{ $b['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('paymentParts.' . $index . '.bolsillo_id')" class="mt-0.5 text-red-400 text-xs" />
                                        </div>

                                        {{-- 2. CAMPO DINÁMICO: REFERENCIA O RECIBIDO --}}
                                        @if($isBank)
                                            {{-- Si es Banco: Mostramos Referencia --}}
                                            <div class="md:col-span-4">
                                                <label class="text-[10px] uppercase font-black text-slate-500">Referencia / Voucher</label>
                                                <input type="text" wire:model="paymentParts.{{ $index }}.reference" placeholder="# Transacción"
                                                       class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm">
                                            </div>
                                        @else
                                            {{-- Si es Efectivo: Mostramos Recibido para calcular cambio --}}
                                            <div class="md:col-span-4">
                                                <label class="text-[10px] uppercase font-black text-slate-500">Dinero Recibido</label>
                                                <div class="relative">
                                                    <input type="number" wire:model.live="paymentParts.{{ $index }}.recibido" step="0.01" min="0" placeholder="0.00"
                                                           class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm font-bold">
                                                    @if($vuelto !== null)
                                                        <div class="absolute right-0 -bottom-5 text-xs font-bold text-emerald-400">
                                                            Cambio: ${{ number_format($vuelto, 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        {{-- 3. MONTO A COBRAR --}}
                                        <div class="md:col-span-3">
                                            <label class="text-[10px] uppercase font-black text-slate-500">Monto</label>
                                            <input type="number" wire:model.blur="paymentParts.{{ $index }}.amount" step="0.01" min="0" placeholder="0.00"
                                                   class="w-full mt-1 rounded bg-slate-800 border-slate-600 text-white text-sm font-bold text-right">
                                            <x-input-error :messages="$errors->get('paymentParts.' . $index . '.amount')" class="mt-0.5 text-red-400 text-xs" />
                                        </div>

                                        {{-- Botón Eliminar --}}
                                        @if(count($paymentParts) > 1)
                                            <button type="button" wire:click="quitarPago({{ $index }})" 
                                                    class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-1 shadow-lg hover:bg-red-500 transition-colors"
                                                    title="Quitar este pago">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Barra de Resumen (Sin cambios, solo la mantenemos) --}}
                            <div class="mt-8 p-4 bg-slate-900 rounded-xl flex items-center justify-between border border-slate-700">
                                <div class="text-sm font-bold text-slate-400 italic">Resumen de pagos:</div>
                                <div class="flex space-x-8">
                                    <div class="text-center">
                                        <p class="text-[10px] text-slate-500 uppercase font-black">Total Pagado</p>
                                        <p class="text-lg font-bold text-white">${{ number_format($this->totalPagado, 2) }}</p>
                                    </div>
                                    <div class="text-center border-l border-slate-700 pl-8">
                                        <p class="text-[10px] text-slate-500 uppercase font-black">Pendiente</p>
                                        @if(abs($this->diferenciaPago) < 0.01)
                                            <p class="text-lg font-bold text-emerald-400 flex items-center justify-center">
                                                <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                ¡Completo!
                                            </p>
                                        @else
                                            <p class="text-lg font-bold text-amber-500 animate-pulse">
                                                {{ $this->diferenciaPago > 0 ? 'Falta' : 'Sobra' }} ${{ number_format(abs($this->diferenciaPago), 2) }}
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

    {{-- Modal: Unidades disponibles (producto serializado) --}}
    @if($productoSerializadoId !== null)
        @php
            $totalUnidades = $unidadesDisponiblesTotal;
            $perPage = $unidadesDisponiblesPerPage ?: 15;
            $maxPage = $totalUnidades > 0 ? (int) ceil($totalUnidades / $perPage) : 1;
            $from = $totalUnidades === 0 ? 0 : ($unidadesDisponiblesPage - 1) * $perPage + 1;
            $to = min($unidadesDisponiblesPage * $perPage, $totalUnidades);
        @endphp
        <div class="fixed inset-0 overflow-y-auto" style="z-index: 200;" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/80 transition-opacity" wire:click="cerrarModalUnidadesFactura"></div>
                <div class="relative bg-slate-800 rounded-2xl shadow-2xl border border-slate-600 max-w-lg w-full max-h-[90vh] flex flex-col">
                    <div class="p-4 border-b border-slate-600">
                        <h3 class="text-lg font-bold text-white">Seleccionar unidades — {{ $productoSerializadoNombre }}</h3>
                        <p class="text-sm text-slate-400 mt-1">Elige los ítems disponibles que quieres agregar a la factura.</p>
                        <div class="mt-3">
                            <label for="modal-buscar-serie-factura" class="sr-only">Buscar por número de serie</label>
                            <input type="text"
                                   id="modal-buscar-serie-factura"
                                   wire:model.live.debounce.400ms="unidadesDisponiblesSearch"
                                   placeholder="Buscar por número de serie..."
                                   class="w-full rounded-md border-slate-600 bg-slate-900 text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1">
                        @if(count($unidadesDisponibles) > 0)
                            <ul class="space-y-2">
                                @foreach($unidadesDisponibles as $unit)
                                    <li class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-700/50">
                                        <input type="checkbox"
                                               id="serial-factura-{{ $unit['id'] }}"
                                               wire:model.live="serialesSeleccionados"
                                               value="{{ $unit['serial_number'] }}"
                                               class="rounded border-slate-600 text-indigo-500 focus:ring-indigo-500 bg-slate-800">
                                        <label for="serial-factura-{{ $unit['id'] }}" class="flex-1 text-sm text-slate-200 cursor-pointer">
                                            <span class="font-medium">{{ $unit['serial_number'] }}</span>
                                            @if(!empty($unit['features']) && is_array($unit['features']))
                                                <span class="text-slate-500 ml-2">— {{ implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($unit['features']), $unit['features'])) }}</span>
                                            @endif
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500">
                                @if(!empty(trim($unidadesDisponiblesSearch)))
                                    No hay unidades con ese número de serie.
                                @else
                                    No hay unidades disponibles en este momento.
                                @endif
                            </p>
                        @endif
                    </div>
                    @if($totalUnidades > 0)
                        <div class="px-4 py-2 border-t border-slate-600 flex items-center justify-between gap-2 flex-wrap">
                            <p class="text-xs text-slate-500">
                                Mostrando {{ $from }}-{{ $to }} de {{ $totalUnidades }}
                            </p>
                            <div class="flex items-center gap-1">
                                <button type="button"
                                        wire:click="irAPaginaUnidadesFactura({{ $unidadesDisponiblesPage - 1 }})"
                                        @if($unidadesDisponiblesPage <= 1) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-slate-600 text-slate-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-700">
                                    Anterior
                                </button>
                                @for($p = max(1, $unidadesDisponiblesPage - 2); $p <= min($maxPage, $unidadesDisponiblesPage + 2); $p++)
                                    <button type="button"
                                            wire:click="irAPaginaUnidadesFactura({{ $p }})"
                                            class="px-2 py-1 text-sm rounded {{ $p === $unidadesDisponiblesPage ? 'bg-indigo-600 text-white' : 'border border-slate-600 text-slate-300 hover:bg-slate-700' }}">
                                        {{ $p }}
                                    </button>
                                @endfor
                                <button type="button"
                                        wire:click="irAPaginaUnidadesFactura({{ $unidadesDisponiblesPage + 1 }})"
                                        @if($unidadesDisponiblesPage >= $maxPage) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-slate-600 text-slate-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-700">
                                    Siguiente
                                </button>
                            </div>
                        </div>
                    @endif
                    @if($errorStock)
                        <div class="px-4 py-2 bg-red-900/30 border-t border-red-800 text-red-300 text-sm">
                            {{ $errorStock }}
                        </div>
                    @endif
                    <div class="p-4 border-t border-slate-600 flex justify-end gap-2">
                        <button type="button"
                                wire:click="cerrarModalUnidadesFactura"
                                class="px-4 py-2 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 font-bold text-sm">
                            Cerrar
                        </button>
                        <button type="button"
                                wire:click="agregarSerializadosAFactura"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                @if(empty($serialesSeleccionados)) disabled @endif>
                            Agregar seleccionados ({{ count($serialesSeleccionados) }})
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>