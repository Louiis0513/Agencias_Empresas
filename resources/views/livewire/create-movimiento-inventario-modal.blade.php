<div x-on:open-modal.window="if ($event.detail === 'create-movimiento-inventario') { $wire.resetForm(); }">
    <x-modal name="create-movimiento-inventario" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Registrar movimiento de inventario') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Selecciona un producto ya creado. La estrategia (serializado / lote) se detecta automáticamente.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="product_id" value="{{ __('Producto') }}" />
                    <select wire:model.live="product_id" id="product_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">Selecciona un producto</option>
                        @foreach($this->productos as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} {{ $p->sku ? "({$p->sku})" : '' }} — Stock: {{ $p->stock }}</option>
                        @endforeach
                    </select>
                    @if($this->productos->isEmpty())
                        <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">No hay productos con type «producto» en esta tienda.</p>
                    @endif
                    <x-input-error :messages="$errors->get('product_id')" class="mt-1" />
                </div>

                @if($this->productoSeleccionado)
                    <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <div>
                            Tipo: <strong>
                                @if($this->productoSeleccionado->type === 'serialized') Serializado
                                @else Por lotes
                                @endif
                            </strong>
                        </div>
                        <div>Stock actual: <strong>{{ $this->productoSeleccionado->stock }}</strong></div>
                    </div>
                @endif

                <div>
                    <x-input-label for="type" value="{{ __('Tipo') }}" />
                    <select wire:model.live="type" id="type" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="ENTRADA">Entrada</option>
                        <option value="SALIDA">Salida</option>
                    </select>
                </div>

                @if($this->productoSeleccionado)
                    @if($this->productoSeleccionado->type === 'serialized')
                        @if($this->type === 'ENTRADA')
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="serial_reference" value="Referencia de compra/origen (obligatoria)" />
                                    <x-text-input wire:model="serial_reference" id="serial_reference" class="block mt-1 w-full" type="text" placeholder="Ej: Compra-123, Factura-X, INI-2025" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si es carga inicial, usa INI-2025 o similar. Todo debe tener origen.</p>
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
                                                    <x-input-label value="Costo de esta unidad (€)" />
                                                    <x-text-input wire:model="serial_items.{{ $index }}.cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
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
                                @if(empty($serials_available))
                                    <p class="text-sm text-amber-600 dark:text-amber-400">No hay seriales disponibles.</p>
                                @else
                                    <select multiple wire:model="serials_selected" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm" size="6">
                                        @foreach($serials_available as $serial)
                                            <option value="{{ $serial }}">{{ $serial }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Selecciona uno o más seriales. La cantidad se calcula automáticamente.</p>
                                @endif
                                <x-input-error :messages="$errors->get('serials_selected')" class="mt-1" />
                            </div>
                        @endif
                    @else
                        @if($this->type === 'ENTRADA')
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="batch_reference" value="Referencia de compra/lote (obligatoria)" />
                                    <x-text-input wire:model="batch_reference" id="batch_reference" class="block mt-1 w-full" type="text" placeholder="Ej: Compra-123, Factura-X, INI-2025" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Todo ingreso debe tener origen. Si es carga inicial, usa INI-2025.</p>
                                    <x-input-error :messages="$errors->get('batch_reference')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="batch_expiration" value="Fecha de vencimiento (opcional)" />
                                    <x-text-input wire:model="batch_expiration" id="batch_expiration" class="block mt-1" type="date" />
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Variantes del lote</span>
                                        <button type="button" wire:click="addBatchItem" class="text-indigo-600 hover:underline text-sm">+ Agregar variante</button>
                                    </div>
                                    @foreach($batch_items as $index => $item)
                                        <div class="border rounded-lg p-4 space-y-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Variante #{{ $index + 1 }}</span>
                                                @if(count($batch_items) > 1)
                                                    <button type="button" wire:click="removeBatchItem({{ $index }})" class="text-red-600 hover:underline text-sm">Eliminar</button>
                                                @endif
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label value="Cantidad" />
                                                    <x-text-input wire:model="batch_items.{{ $index }}.quantity" class="block mt-1 w-full" type="number" min="1" placeholder="0" />
                                                </div>
                                                <div>
                                                    <x-input-label value="Costo unitario" />
                                                    <x-text-input wire:model="batch_items.{{ $index }}.unit_cost" class="block mt-1 w-full" type="number" min="0" step="0.01" placeholder="0.00" />
                                                </div>
                                            </div>
                                            @if(!empty($categoryAttributes))
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    @foreach($categoryAttributes as $attr)
                                                        <div>
                                                            <x-input-label :value="$attr['name']" />
                                                            <x-text-input wire:model="batch_items.{{ $index }}.features.{{ $attr['id'] }}" class="block mt-1 w-full" type="text" placeholder="Valor" />
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Sin atributos configurados para esta categoría.</p>
                                            @endif
                                        </div>
                                    @endforeach
                                    <x-input-error :messages="$errors->get('batch_items')" class="mt-1" />
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">La cantidad total se calculará sumando las variantes.</p>
                            </div>
                        @else
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="batch_item_id" value="Lote / variante disponible" />
                                    @if(empty($batch_items_available))
                                        <p class="text-sm text-amber-600 dark:text-amber-400">No hay lotes con stock disponible.</p>
                                    @else
                                        <select wire:model="batch_item_id" id="batch_item_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                                            <option value="">Selecciona un lote</option>
                                            @foreach($batch_items_available as $item)
                                                <option value="{{ $item['id'] }}">
                                                    {{ $item['reference'] }} — Disp: {{ $item['quantity'] }}
                                                    @if(!empty($item['features']))
                                                        ({{ collect($item['features'])->map(fn($v, $k) => "$k: $v")->implode(', ') }})
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <x-input-error :messages="$errors->get('batch_item_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="quantity" value="Cantidad a descontar" />
                                    <x-text-input wire:model="quantity" id="quantity" class="block mt-1 w-full" type="number" min="1" placeholder="1" />
                                    <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                                </div>
                            </div>
                        @endif
                    @endif
                @endif

                <div>
                    <x-input-label for="description" value="{{ __('Descripción') }}" />
                    <textarea wire:model="description" id="description" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="2" placeholder="Ej: Ajuste por conteo, Compra a proveedor"></textarea>
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
    </x-modal>
</div>
