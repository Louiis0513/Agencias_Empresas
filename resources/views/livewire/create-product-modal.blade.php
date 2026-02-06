<div>
    @php
        $modalName = ($fromPurchase ?? false) ? 'create-product-from-compra' : 'create-product';
    @endphp
    <x-modal :name="$modalName" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear producto') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Define el producto y su categoría (atributos). El ingreso a tienda se hace por Compras.') }}
            </p>

            <div class="mt-6 space-y-4">
                {{-- Tipo de producto (siempre visible) --}}
                <div>
                    <x-input-label for="type" value="{{ __('Tipo de producto') }}" />
                    <select wire:model.live="type"
                            id="type"
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($this->typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>

                {{-- ========== FORMULARIO TIPO SIMPLE (por defecto) ========== --}}
                @if($type === 'simple')
                    <div>
                        <x-input-label for="name" value="{{ __('Nombre') }}" />
                        <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" placeholder="Ej: Leche entera 1L" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="sku" value="{{ __('SKU') }}" />
                            <x-text-input wire:model="sku" id="sku" class="block mt-1 w-full" type="text" placeholder="Ej: LEC-001" />
                            <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="barcode" value="{{ __('Barcode') }}" />
                            <x-text-input wire:model="barcode" id="barcode" class="block mt-1 w-full" type="text" placeholder="Ej: 8412345678901" />
                            <x-input-error :messages="$errors->get('barcode')" class="mt-1" />
                        </div>
                    </div>

                    @if($this->categoriesWithAttributes->isNotEmpty())
                        <div>
                            <x-input-label for="category_id" value="{{ __('Categoría') }}" />
                            <select wire:model.live="category_id"
                                    id="category_id"
                                    class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">{{ __('Selecciona una categoría') }}</option>
                                @foreach($this->categoriesWithAttributes as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->attributes->count() }} atributo(s))</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                        </div>

                        {{-- Atributos de la categoría (se muestran al seleccionar categoría) --}}
                        @if($this->selectedCategory && $this->selectedCategory->attributes->isNotEmpty())
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 bg-gray-50 dark:bg-gray-900/40">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Atributos de la categoría') }}</p>
                                <div class="space-y-4">
                                    @foreach($this->selectedCategory->attributes as $attr)
                                        <div>
                                            @php
                                                $val = $attribute_values[$attr->id] ?? ($attr->type === 'boolean' ? '0' : '');
                                            @endphp
                                            @if($attr->type === 'text')
                                                <x-input-label for="attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                <x-text-input wire:model="attribute_values.{{ $attr->id }}" id="attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                            @elseif($attr->type === 'number')
                                                <x-input-label for="attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                <x-text-input wire:model="attribute_values.{{ $attr->id }}" id="attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" />
                                            @elseif($attr->type === 'select')
                                                <x-input-label for="attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                <select wire:model="attribute_values.{{ $attr->id }}"
                                                        id="attr-{{ $attr->id }}"
                                                        class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">{{ __('Selecciona') }}</option>
                                                    @foreach($attr->options as $opt)
                                                        <option value="{{ $opt->value }}">{{ $opt->value }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($attr->type === 'boolean')
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox"
                                                           wire:model.live="attribute_values.{{ $attr->id }}"
                                                           value="1"
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $attr->name }}</span>
                                                </label>
                                            @else
                                                <x-input-label for="attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                <x-text-input wire:model="attribute_values.{{ $attr->id }}" id="attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4">
                            <p class="text-sm text-amber-800 dark:text-amber-200">
                                No hay categorías con atributos. Crea categorías y asígnales grupos de atributos en <strong>Categorías → [categoría] → Atributos</strong>.
                            </p>
                        </div>
                    @endif

                    <div>
                        <x-input-label for="location" value="{{ __('Ubicación') }}" />
                        <x-text-input wire:model="location" id="location" class="block mt-1 w-full" type="text" placeholder="Ej: Estantería A2" />
                        <x-input-error :messages="$errors->get('location')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="price" value="{{ __('Precio') }}" />
                        <x-text-input wire:model="price" id="price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                    </div>

                    <div class="flex items-center">
                        <input wire:model.live="has_initial_stock" id="has_initial_stock" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <x-input-label for="has_initial_stock" value="{{ __('Tiene stock inicial') }}" class="ml-2" />
                    </div>

                    <div x-show="$wire.has_initial_stock"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cost" value="{{ __('Costo') }}" />
                            <x-text-input wire:model="cost" id="cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                            <x-input-error :messages="$errors->get('cost')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="stock" value="{{ __('Stock inicial') }}" />
                            <x-text-input wire:model="stock" id="stock" class="block mt-1 w-full" type="number" min="0" placeholder="0" />
                            <x-input-error :messages="$errors->get('stock')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <x-input-label for="is_active" value="{{ __('Activo') }}" class="ml-2" />
                        <x-input-error :messages="$errors->get('is_active')" class="ml-2" />
                    </div>
                @endif

                {{-- ========== FORMULARIO TIPO LOTE ========== --}}
                @if($type === 'batch')
                    <div>
                        <x-input-label for="name_batch" value="{{ __('Nombre') }}" />
                        <x-text-input wire:model="name" id="name_batch" class="block mt-1 w-full" type="text" placeholder="Ej: Suéter azul" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="sku_batch" value="{{ __('SKU') }}" />
                            <x-text-input wire:model="sku" id="sku_batch" class="block mt-1 w-full" type="text" placeholder="Ej: LEC-001" />
                            <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="barcode_batch" value="{{ __('Barcode') }}" />
                            <x-text-input wire:model="barcode" id="barcode_batch" class="block mt-1 w-full" type="text" placeholder="Ej: 8412345678901" />
                            <x-input-error :messages="$errors->get('barcode')" class="mt-1" />
                        </div>
                    </div>

                    @if($this->categoriesWithAttributes->isNotEmpty())
                        <div>
                            <x-input-label for="category_id_batch" value="{{ __('Categoría') }}" />
                            <select wire:model.live="category_id"
                                    id="category_id_batch"
                                    class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">{{ __('Selecciona una categoría') }}</option>
                                @foreach($this->categoriesWithAttributes as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->attributes->count() }} atributo(s))</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                        </div>

                        {{-- Variantes del producto --}}
                        @if($this->selectedCategory && $this->selectedCategory->attributes->isNotEmpty())
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 bg-gray-50 dark:bg-gray-900/40">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Variantes de este producto') }}</p>
                                    <button type="button"
                                            wire:click="addVariant"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Agregar variante
                                    </button>
                                </div>

                                @if(empty($variants))
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                        Selecciona una categoría para que aparezca automáticamente la primera variante.
                                    </p>
                                @endif

                                <div class="space-y-4">
                                    @foreach($variants as $index => $variant)
                                        <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-gray-800">
                                            <div class="flex items-center justify-between mb-3">
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Variante {{ $index + 1 }}</span>
                                                @if(count($variants) > 1)
                                                    <button type="button"
                                                            wire:click="removeVariant({{ $index }})"
                                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm">
                                                        Eliminar
                                                    </button>
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">Debe haber al menos una variante</span>
                                                @endif
                                            </div>

                                            {{-- Atributos de la variante --}}
                                            <div class="space-y-3 mb-4">
                                                @foreach($this->selectedCategory->attributes as $attr)
                                                    @php
                                                        $val = $variant['attribute_values'][$attr->id] ?? ($attr->type === 'boolean' ? '0' : '');
                                                    @endphp
                                                    @if($attr->type === 'text')
                                                        <div>
                                                            <x-input-label for="variant-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                            <x-text-input wire:model="variants.{{ $index }}.attribute_values.{{ $attr->id }}" id="variant-{{ $index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                                        </div>
                                                    @elseif($attr->type === 'number')
                                                        <div>
                                                            <x-input-label for="variant-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                            <x-text-input wire:model="variants.{{ $index }}.attribute_values.{{ $attr->id }}" id="variant-{{ $index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" />
                                                        </div>
                                                    @elseif($attr->type === 'select')
                                                        <div>
                                                            <x-input-label for="variant-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                            <select wire:model="variants.{{ $index }}.attribute_values.{{ $attr->id }}"
                                                                    id="variant-{{ $index }}-attr-{{ $attr->id }}"
                                                                    class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                                <option value="">{{ __('Selecciona') }}</option>
                                                                @foreach($attr->options as $opt)
                                                                    <option value="{{ $opt->value }}">{{ $opt->value }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @elseif($attr->type === 'boolean')
                                                        <label class="flex items-center gap-2">
                                                            <input type="checkbox"
                                                                   wire:model.live="variants.{{ $index }}.attribute_values.{{ $attr->id }}"
                                                                   value="1"
                                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $attr->name }}</span>
                                                        </label>
                                                    @endif
                                                @endforeach
                                            </div>

                                            {{-- Precio (siempre visible) --}}
                                            <div class="mb-4">
                                                <x-input-label for="variant-{{ $index }}-price" value="{{ __('Precio') }}" />
                                                <x-text-input wire:model="variants.{{ $index }}.price" id="variant-{{ $index }}-price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                            </div>

                                            {{-- Checkbox para stock inicial --}}
                                            <div class="mb-4">
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox"
                                                           wire:model.live="variants.{{ $index }}.has_stock"
                                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tiene stock inicial</span>
                                                </label>
                                            </div>

                                            {{-- Costo y Stock inicial (solo si tiene stock) --}}
                                            <div x-show="$wire.variants[{{ $index }}].has_stock ?? false"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0"
                                                 x-transition:enter-end="opacity-100"
                                                 x-transition:leave="transition ease-in duration-150"
                                                 x-transition:leave-start="opacity-100"
                                                 x-transition:leave-end="opacity-0"
                                                 class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                                <div>
                                                    <x-input-label for="variant-{{ $index }}-cost" value="{{ __('Costo') }}" />
                                                    <x-text-input wire:model="variants.{{ $index }}.cost" id="variant-{{ $index }}-cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                                </div>
                                                <div>
                                                    <x-input-label for="variant-{{ $index }}-stock" value="{{ __('Stock inicial') }}" />
                                                    <x-text-input wire:model.live="variants.{{ $index }}.stock_initial" id="variant-{{ $index }}-stock" class="block mt-1 w-full" type="number" min="0" placeholder="0" />
                                                </div>
                                            </div>

                                            {{-- Número de lote y Fecha de vencimiento (solo si hay stock inicial) --}}
                                            <div x-show="($wire.variants[{{ $index }}].has_stock ?? false) && ($wire.variants[{{ $index }}].stock_initial > 0)"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0"
                                                 x-transition:enter-end="opacity-100"
                                                 x-transition:leave="transition ease-in duration-150"
                                                 x-transition:leave-start="opacity-100"
                                                 x-transition:leave-end="opacity-0"
                                                 class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                                                <div>
                                                    <x-input-label for="variant-{{ $index }}-batch" value="{{ __('Número de lote') }}" />
                                                    <x-text-input wire:model="variants.{{ $index }}.batch_number" id="variant-{{ $index }}-batch" class="block mt-1 w-full" type="text" placeholder="Ej: L-001" />
                                                </div>
                                                <div>
                                                    <x-input-label for="variant-{{ $index }}-expiration" value="{{ __('Fecha de vencimiento') }}" />
                                                    <x-text-input wire:model="variants.{{ $index }}.expiration_date" id="variant-{{ $index }}-expiration" class="block mt-1 w-full" type="date" />
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    <div>
                        <x-input-label for="location_batch" value="{{ __('Ubicación') }}" />
                        <x-text-input wire:model="location" id="location_batch" class="block mt-1 w-full" type="text" placeholder="Ej: Estantería A2" />
                        <x-input-error :messages="$errors->get('location')" class="mt-1" />
                    </div>

                    <div class="flex items-center">
                        <input wire:model="is_active" id="is_active_batch" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <x-input-label for="is_active_batch" value="{{ __('Activo') }}" class="ml-2" />
                    </div>
                @endif

                {{-- ========== FORMULARIO TIPO SERIALIZADO ========== --}}
                @if($type === 'serialized')
                    <div>
                        <x-input-label for="name_serial" value="{{ __('Nombre') }}" />
                        <x-text-input wire:model="name" id="name_serial" class="block mt-1 w-full" type="text" placeholder="Ej: iPhone 15 Pro" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="sku_serial" value="{{ __('SKU') }}" />
                            <x-text-input wire:model="sku" id="sku_serial" class="block mt-1 w-full" type="text" placeholder="Ej: IPH-001" />
                            <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="barcode_serial" value="{{ __('Barcode') }}" />
                            <x-text-input wire:model="barcode" id="barcode_serial" class="block mt-1 w-full" type="text" placeholder="Ej: 8412345678901" />
                            <x-input-error :messages="$errors->get('barcode')" class="mt-1" />
                        </div>
                    </div>

                    @if($this->categoriesWithAttributes->isNotEmpty())
                        <div>
                            <x-input-label for="category_id_serial" value="{{ __('Categoría') }}" />
                            <select wire:model.live="category_id"
                                    id="category_id_serial"
                                    class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">{{ __('Selecciona una categoría') }}</option>
                                @foreach($this->categoriesWithAttributes as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->attributes->count() }} atributo(s))</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                        </div>

                        {{-- Unidades serializadas --}}
                        @if($this->selectedCategory && $this->selectedCategory->attributes->isNotEmpty())
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 bg-gray-50 dark:bg-gray-900/40">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Stock inicial') }}</p>
                                    <button type="button"
                                            wire:click="addSerializedItem"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Añadir stock
                                    </button>
                                </div>

                                @if(empty($serializedItems))
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                        Selecciona una categoría para que aparezca automáticamente la primera unidad.
                                    </p>
                                @endif

                                <div class="space-y-4 max-h-[60vh] overflow-y-auto">
                                    @foreach($serializedItems as $index => $item)
                                        <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-gray-800">
                                            <div class="flex items-center justify-between mb-3">
                                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Unidad {{ $index + 1 }}</span>
                                                @if(count($serializedItems) > 1)
                                                    <button type="button"
                                                            wire:click="removeSerializedItem({{ $index }})"
                                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm">
                                                        Eliminar
                                                    </button>
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">Debe haber al menos una unidad</span>
                                                @endif
                                            </div>

                                            {{-- Número de serie --}}
                                            <div class="mb-4">
                                                <x-input-label for="serial-{{ $index }}" value="{{ __('Número de serie') }} *" />
                                                <x-text-input wire:model="serializedItems.{{ $index }}.serial_number" 
                                                              id="serial-{{ $index }}" 
                                                              class="block mt-1 w-full" 
                                                              type="text" 
                                                              placeholder="Ej: IMEI-123456789" />
                                                <x-input-error :messages="$errors->get('serializedItems.' . $index . '.serial_number')" class="mt-1" />
                                            </div>

                                            {{-- Atributos de la unidad --}}
                                            <div class="space-y-3 mb-4">
                                                @foreach($this->selectedCategory->attributes as $attr)
                                                    @php
                                                        $val = $item['attribute_values'][$attr->id] ?? ($attr->type === 'boolean' ? '0' : '');
                                                    @endphp
                                                    @if($attr->type === 'text')
                                                        <div>
                                                            <x-input-label for="item-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                            <x-text-input wire:model="serializedItems.{{ $index }}.attribute_values.{{ $attr->id }}" id="item-{{ $index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                                        </div>
                                                    @elseif($attr->type === 'number')
                                                        <div>
                                                            <x-input-label for="item-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                            <x-text-input wire:model="serializedItems.{{ $index }}.attribute_values.{{ $attr->id }}" id="item-{{ $index }}-attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" />
                                                        </div>
                                                    @elseif($attr->type === 'select')
                                                        <div>
                                                            <x-input-label for="item-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                            <select wire:model="serializedItems.{{ $index }}.attribute_values.{{ $attr->id }}"
                                                                    id="item-{{ $index }}-attr-{{ $attr->id }}"
                                                                    class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                                                <option value="">{{ __('Selecciona') }}</option>
                                                                @foreach($attr->options as $opt)
                                                                    <option value="{{ $opt->value }}">{{ $opt->value }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @elseif($attr->type === 'boolean')
                                                        <label class="flex items-center gap-2">
                                                            <input type="checkbox"
                                                                   wire:model.live="serializedItems.{{ $index }}.attribute_values.{{ $attr->id }}"
                                                                   value="1"
                                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $attr->name }}</span>
                                                        </label>
                                                    @endif
                                                @endforeach
                                            </div>

                                            {{-- Costo y Precio --}}
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label for="cost-serial-{{ $index }}" value="{{ __('Costo') }}" />
                                                    <x-text-input wire:model="serializedItems.{{ $index }}.cost" id="cost-serial-{{ $index }}" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                                </div>
                                                <div>
                                                    <x-input-label for="price-serial-{{ $index }}" value="{{ __('Precio') }}" />
                                                    <x-text-input wire:model="serializedItems.{{ $index }}.price" id="price-serial-{{ $index }}" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if(!empty($serializedItems))
                                    <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg">
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            <strong>Cantidad de stock inicial:</strong> {{ count($serializedItems) }} unidad(es)
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif

                    <div>
                        <x-input-label for="location_serial" value="{{ __('Ubicación') }}" />
                        <x-text-input wire:model="location" id="location_serial" class="block mt-1 w-full" type="text" placeholder="Ej: Estantería A2" />
                        <x-input-error :messages="$errors->get('location')" class="mt-1" />
                    </div>

                    <div class="flex items-center">
                        <input wire:model="is_active" id="is_active_serial" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <x-input-label for="is_active_serial" value="{{ __('Activo') }}" class="ml-2" />
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                @if($this->categoriesWithAttributes->isNotEmpty())
                    <x-primary-button type="submit" wire:loading.attr="disabled">
                        {{ __('Crear producto') }}
                    </x-primary-button>
                @endif
            </div>
        </form>

        @php
            $productFormErrors = $errors->has('name') || $errors->has('category_id') || $errors->has('type');
        @endphp
        @if($errors->any() && (($fromPurchase ?? false) === false || $productFormErrors))
            <div x-init="$dispatch('open-modal', '{{ $modalName }}')"></div>
        @endif
    </x-modal>
</div>
