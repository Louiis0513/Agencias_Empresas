<div x-on:open-modal.window="if ($event.detail === 'create-movimiento-inventario') { $wire.resetForm(); }">
    <x-modal name="create-movimiento-inventario" focusable maxWidth="2xl">
        @if($wizardStep === 1)
            <div class="p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-medium text-white">
                        {{ __('Registrar movimiento de inventario') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-400">
                        {{ __('Primero indica si sumas stock (entrada) o lo descuentas (salida). Después elegirás el producto.') }}
                    </p>
                </div>
                <div>
                    <x-input-label for="type_step1" value="{{ __('Tipo de movimiento') }}" />
                    <select wire:model.live="type" id="type_step1" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                        <option value="ENTRADA">Entrada — suma al inventario</option>
                        <option value="SALIDA">Salida — descuenta del inventario</option>
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-movimiento-inventario')">
                        {{ __('Cancelar') }}
                    </x-secondary-button>
                    <x-primary-button type="button" wire:click="wizardContinue">
                        {{ __('Continuar') }}
                    </x-primary-button>
                </div>
            </div>
        @else
            <form wire:submit="save" class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-medium text-white">
                            {{ __('Registrar movimiento de inventario') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-400">
                            @if($type === 'ENTRADA')
                                <span class="text-emerald-400 font-medium">Entrada</span> — añades unidades al stock.
                            @else
                                <span class="text-amber-400 font-medium">Salida</span> — quitas unidades del stock.
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="wizardBack" class="text-sm px-3 py-2 rounded-lg border border-white/10 text-gray-300 hover:bg-white/10">
                        ← Cambiar tipo de movimiento
                    </button>
                </div>

                <div class="mt-6 space-y-4">
                    <div>
                        <x-input-label for="product_id" value="{{ __('Producto') }}" />
                        <div class="flex gap-2 items-center mt-1">
                            <span class="flex-1 px-3 py-2 rounded-md border border-white/10 bg-white/5 text-gray-100 text-sm min-h-[42px] flex items-center" wire:key="product-display">
                                @if($this->productoSeleccionado)
                                    {{ $this->productoSeleccionado->name }} {{ $this->productoSeleccionado->sku ? "({$this->productoSeleccionado->sku})" : '' }} — Stock: {{ \App\Support\Quantity::displayStockForProduct($this->productoSeleccionado, $this->productoSeleccionado->stock) }}
                                @else
                                    <span class="text-gray-500">Ningún producto seleccionado</span>
                                @endif
                            </span>
                            <button type="button" wire:click="abrirSelectorProducto" class="px-4 py-2 rounded-md border border-white/10 bg-brand/80 hover:bg-brand text-white text-sm font-medium">
                                {{ $this->productoSeleccionado ? 'Cambiar' : 'Seleccionar' }}
                            </button>
                            @if($this->productoSeleccionado)
                                <button type="button" wire:click="clearProduct" class="px-3 py-2 rounded-md border border-white/10 bg-white/5 text-gray-400 hover:text-gray-200 text-sm">
                                    Limpiar
                                </button>
                            @endif
                        </div>
                        <input type="hidden" wire:model="product_id" id="product_id">
                        <x-input-error :messages="$errors->get('product_id')" class="mt-1" />
                    </div>

                    @if($this->productoSeleccionado)
                        <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg text-sm text-gray-400 space-y-1">
                            <div>
                                Tipo de producto: <strong>
                                    @if($this->productoSeleccionado->isSerialized()) Serializado
                                    @elseif($this->productoSeleccionado->isBatch()) Por lotes (variantes)
                                    @else Simple
                                    @endif
                                </strong>
                            </div>
                            <div>Stock actual: <strong>{{ \App\Support\Quantity::displayStockForProduct($this->productoSeleccionado, $this->productoSeleccionado->stock) }}</strong></div>
                        </div>
                    @endif

                    @if($this->productoSeleccionado)
                        @if($this->productoSeleccionado->isSerialized())
                            @if($this->type === 'ENTRADA')
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="serial_reference" value="Referencia de compra/origen (obligatoria)" />
                                        <x-text-input wire:model="serial_reference" id="serial_reference" class="block mt-1 w-full" type="text" placeholder="Ej: Compra-123, Factura-X, INI-2025" />
                                        <p class="mt-1 text-xs text-gray-400">Si es carga inicial, usa INI-2025 o similar. Todo debe tener origen.</p>
                                        <x-input-error :messages="$errors->get('serial_reference')" class="mt-1" />
                                    </div>
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Unidades (cada una: serial + atributos + costo)</span>
                                            <button type="button" wire:click="addSerialItem" class="text-indigo-600 hover:underline text-sm">+ Agregar unidad</button>
                                        </div>
                                        @foreach($serial_items as $index => $item)
                                            <div class="border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Unidad #{{ $index + 1 }}</span>
                                                    @if(count($serial_items) > 1)
                                                        <button type="button" wire:click="removeSerialItem({{ $index }})" class="text-red-600 hover:underline text-sm">Eliminar</button>
                                                    @endif
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div>
                                                        <x-input-label value="Número de serie (IMEI, etc.)" />
                                                        <x-text-input wire:model="serial_items.{{ $index }}.serial_number" class="block mt-1 w-full" type="text" placeholder="Ej: IMEI-123456789" />
                                                    </div>
                                                    <div>
                                                        <x-input-label value="Costo de esta unidad ({{ currency_symbol($this->store?->currency ?? 'COP') }})" />
                                                        <x-money-input wire:model="serial_items.{{ $index }}.cost" :currency="$this->store?->currency ?? 'COP'" :value="$item['cost'] ?? ''" />
                                                    </div>
                                                </div>
                                                @if(!empty($categoryAttributes))
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        @foreach($categoryAttributes as $attr)
                                                            <div>
                                                                <x-input-label :value="$attr['name']" />
                                                                <x-text-input wire:model="serial_items.{{ $index }}.features.{{ $attr['id'] }}" class="block mt-1 w-full" type="text" :placeholder="'Ej: valor para ' . $attr['name']" />
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                        <x-input-error :messages="$errors->get('serial_items')" class="mt-1" />
                                    </div>
                                </div>
                            @else
                                <div>
                                    <x-input-label value="Selecciona los seriales a descontar" />
                                    @if(!empty($serials_selected))
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            @foreach($serials_selected as $sn)
                                                <span class="px-2 py-1 rounded-md bg-gray-700 text-gray-200 text-sm">{{ $sn }}</span>
                                            @endforeach
                                            <button type="button" wire:click="abrirModalSerialesMovimiento({{ $product_id }})" class="px-2 py-1 text-sm text-indigo-400 hover:text-indigo-300">Cambiar</button>
                                        </div>
                                    @else
                                        <button type="button" wire:click="abrirModalSerialesMovimiento({{ $product_id }})" class="mt-1 px-4 py-2 rounded-md border border-white/10 bg-brand/80 hover:bg-brand text-white text-sm font-medium">
                                            Seleccionar seriales
                                        </button>
                                    @endif
                                    <x-input-error :messages="$errors->get('serials_selected')" class="mt-1" />
                                </div>
                            @endif
                        @elseif($this->productoSeleccionado->isBatch())
                            @if($this->type === 'ENTRADA')
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="batch_variant" value="Variante seleccionada" />
                                        @if($product_variant_id && $selectedVariantDisplayName)
                                            <div class="flex gap-2 items-center mt-1">
                                                <span class="flex-1 px-3 py-2 rounded-md border border-white/10 bg-white/5 text-gray-100 text-sm">{{ $selectedVariantDisplayName }}</span>
                                                <button type="button" wire:click="abrirSelectorVarianteBatch" class="px-4 py-2 rounded-md border border-white/10 bg-brand/80 hover:bg-brand text-white text-sm font-medium">Cambiar</button>
                                            </div>
                                        @else
                                            <button type="button" wire:click="abrirSelectorVarianteBatch" class="mt-1 px-4 py-2 rounded-md border border-dashed border-white/30 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-gray-200 text-sm font-medium">
                                                Seleccionar variante
                                            </button>
                                        @endif
                                        <x-input-error :messages="$errors->get('product_variant_id')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="batch_reference" value="Referencia de compra/lote (obligatoria)" />
                                        <x-text-input wire:model="batch_reference" id="batch_reference" class="block mt-1 w-full" type="text" placeholder="Ej: Compra-123, Factura-X, INI-2025" />
                                        <p class="mt-1 text-xs text-gray-400">Todo ingreso debe tener origen. Si es carga inicial, usa INI-2025.</p>
                                        <x-input-error :messages="$errors->get('batch_reference')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="batch_expiration" value="Fecha de vencimiento (opcional)" />
                                        <x-text-input wire:model="batch_expiration" id="batch_expiration" class="block mt-1 w-full" type="date" />
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="quantity" value="Cantidad" />
                                            <x-text-input wire:model="quantity" id="quantity" class="block mt-1 w-full" type="number" min="0.01" step="any" placeholder="1" />
                                            <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="unit_cost" value="Costo unitario ({{ currency_symbol($this->store?->currency ?? 'COP') }})" />
                                            <x-money-input wire:model="unit_cost" :currency="$this->store?->currency ?? 'COP'" :value="$unit_cost" id="unit_cost" />
                                            <x-input-error :messages="$errors->get('unit_cost')" class="mt-1" />
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="batch_variant_salida" value="Variante a descontar (stock por variante)" />
                                        @php
                                            $selectedVar = $product_variant_id ? collect($batch_items_available)->firstWhere('product_variant_id', (int) $product_variant_id) : null;
                                            $dispVar = $selectedVar ? (int) ($selectedVar['quantity'] ?? 0) : null;
                                        @endphp

                                        @if($product_variant_id && $selectedVariantDisplayName)
                                            <div class="flex gap-2 items-center mt-1">
                                                <span class="flex-1 px-3 py-2 rounded-md border border-white/10 bg-white/5 text-gray-100 text-sm">
                                                    {{ $selectedVariantDisplayName }}
                                                    @if($dispVar !== null)
                                                        <span class="text-gray-400">— Disp: {{ $dispVar }}</span>
                                                    @endif
                                                </span>
                                                <button type="button" wire:click="abrirSelectorVarianteBatch" class="px-4 py-2 rounded-md border border-white/10 bg-brand/80 hover:bg-brand text-white text-sm font-medium">
                                                    Cambiar
                                                </button>
                                            </div>
                                        @else
                                            @if(empty($batch_items_available))
                                                <p class="text-sm text-amber-600 dark:text-amber-400 mt-1">No hay variantes con stock disponible.</p>
                                            @endif
                                            <button type="button" wire:click="abrirSelectorVarianteBatch" class="mt-1 px-4 py-2 rounded-md border border-dashed border-white/30 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-gray-200 text-sm font-medium">
                                                Seleccionar variante
                                            </button>
                                        @endif
                                        <x-input-error :messages="$errors->get('product_variant_id')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="quantity" value="Cantidad a descontar" />
                                        <x-text-input wire:model="quantity" id="quantity" class="block mt-1 w-full" type="number" min="1" placeholder="1" />
                                        <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                                    </div>
                                </div>
                            @endif
                        @else
                            {{-- Producto simple --}}
                            @if($this->type === 'ENTRADA')
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="simple_ref_in" value="Referencia de origen (obligatoria)" />
                                        <x-text-input wire:model="batch_reference" id="simple_ref_in" class="block mt-1 w-full" type="text" placeholder="Ej: Compra-123, INI-2025, Ajuste" />
                                        <p class="mt-1 text-xs text-gray-400">Usa la misma referencia que documentaría el ingreso (compra, nota interna, etc.).</p>
                                        <x-input-error :messages="$errors->get('batch_reference')" class="mt-1" />
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="quantity_simple_in" value="Cantidad" />
                                            <x-text-input wire:model="quantity" id="quantity_simple_in" class="block mt-1 w-full" type="number" min="0.01" step="any" placeholder="1" />
                                            <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="unit_cost_simple" value="Costo unitario ({{ currency_symbol($this->store?->currency ?? 'COP') }})" />
                                            <x-money-input wire:model="unit_cost" :currency="$this->store?->currency ?? 'COP'" :value="$unit_cost" id="unit_cost_simple" />
                                            <x-input-error :messages="$errors->get('unit_cost')" class="mt-1" />
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">Los productos simples no usan variantes ni fecha de vencimiento en este formulario.</p>
                                </div>
                            @else
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="quantity_simple_out" value="Cantidad a descontar" />
                                        <x-text-input wire:model="quantity" id="quantity_simple_out" class="block mt-1 w-full" type="number" min="0.01" step="any" placeholder="1" />
                                        <p class="mt-1 text-xs text-gray-400">La salida aplica FIFO sobre los lotes internos del producto, sin elegir variante.</p>
                                        <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endif

                    <div>
                        <x-input-label for="description" value="{{ __('Descripción') }}" />
                        <textarea wire:model="description" id="description" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand" rows="2" placeholder="Ej: Ajuste por conteo, merma"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-movimiento-inventario')">
                        {{ __('Cancelar') }}
                    </x-secondary-button>
                    <x-primary-button>
                        {{ __('Registrar') }}
                    </x-primary-button>
                </div>
            </form>
        @endif
    </x-modal>

    @if($productoSerializadoIdMov !== null)
        @php
            $totalUnidades = $unidadesDisponiblesTotalMov;
            $perPage = $unidadesDisponiblesPerPageMov ?: 15;
            $maxPage = $totalUnidades > 0 ? (int) ceil($totalUnidades / $perPage) : 1;
            $from = $totalUnidades === 0 ? 0 : ($unidadesDisponiblesPageMov - 1) * $perPage + 1;
            $to = min($unidadesDisponiblesPageMov * $perPage, $totalUnidades);
        @endphp
        <div class="fixed inset-0 overflow-y-auto" style="z-index: 200;" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/80 transition-opacity" wire:click="cerrarModalSerialesMovimiento"></div>
                <div class="relative bg-slate-800 rounded-2xl shadow-2xl border border-slate-600 max-w-lg w-full max-h-[90vh] flex flex-col">
                    <div class="p-4 border-b border-slate-600">
                        <h3 class="text-lg font-bold text-white">Seleccionar seriales — {{ $productoSerializadoNombreMov }}</h3>
                        <p class="text-sm text-slate-400 mt-1">Elige los ítems que salen del inventario.</p>
                        <div class="mt-3">
                            <input type="text"
                                   wire:model.live.debounce.400ms="unidadesDisponiblesSearchMov"
                                   placeholder="Buscar por número de serie..."
                                   class="w-full rounded-md border-slate-600 bg-slate-900 text-white text-sm focus:ring-brand focus:border-brand">
                        </div>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1">
                        @if(count($unidadesDisponiblesMov) > 0)
                            <ul class="space-y-2">
                                @foreach($unidadesDisponiblesMov as $unit)
                                    <li class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-700/50">
                                        <input type="checkbox"
                                               id="serial-mov-{{ $unit['id'] }}"
                                               wire:model.live="serialesSeleccionadosMov"
                                               value="{{ $unit['serial_number'] }}"
                                               class="rounded border-slate-600 text-brand focus:ring-brand bg-slate-800">
                                        <label for="serial-mov-{{ $unit['id'] }}" class="flex-1 text-sm text-slate-200 cursor-pointer">
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
                                @if(!empty(trim($unidadesDisponiblesSearchMov)))
                                    No hay unidades con ese número de serie.
                                @else
                                    No hay unidades disponibles.
                                @endif
                            </p>
                        @endif
                    </div>
                    @if($totalUnidades > 0)
                        <div class="px-4 py-2 border-t border-slate-600 flex items-center justify-between gap-2 flex-wrap">
                            <p class="text-xs text-slate-500">Mostrando {{ $from }}-{{ $to }} de {{ $totalUnidades }}</p>
                            <div class="flex gap-1">
                                <button type="button"
                                        wire:click="irAPaginaUnidadesMovimiento({{ $unidadesDisponiblesPageMov - 1 }})"
                                        @if($unidadesDisponiblesPageMov <= 1) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-slate-600 text-slate-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-700">Anterior</button>
                                @for($p = max(1, $unidadesDisponiblesPageMov - 2); $p <= min($maxPage, $unidadesDisponiblesPageMov + 2); $p++)
                                    <button type="button"
                                            wire:click="irAPaginaUnidadesMovimiento({{ $p }})"
                                            class="px-2 py-1 text-sm rounded {{ $p === $unidadesDisponiblesPageMov ? 'bg-brand text-white' : 'border border-slate-600 text-slate-300 hover:bg-slate-700' }}">{{ $p }}</button>
                                @endfor
                                <button type="button"
                                        wire:click="irAPaginaUnidadesMovimiento({{ $unidadesDisponiblesPageMov + 1 }})"
                                        @if($unidadesDisponiblesPageMov >= $maxPage) disabled @endif
                                        class="px-2 py-1 text-sm rounded border border-slate-600 text-slate-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-slate-700">Siguiente</button>
                            </div>
                        </div>
                    @endif
                    <div class="p-4 border-t border-slate-600 flex justify-end gap-2">
                        <button type="button" wire:click="cerrarModalSerialesMovimiento" class="px-4 py-2 border border-slate-600 rounded-lg text-slate-300 hover:bg-slate-700 font-bold text-sm">Cerrar</button>
                        <button type="button"
                                wire:click="confirmarSerialesMovimiento"
                                class="px-4 py-2 bg-brand text-white rounded-lg font-bold text-sm hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                                @if(empty($serialesSeleccionadosMov)) disabled @endif>
                            Confirmar ({{ count($serialesSeleccionadosMov) }})
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
