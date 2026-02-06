<div>
    <x-modal name="add-initial-stock-serialized" focusable maxWidth="4xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Añadir stock inicial') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Agrega unidades serializadas con sus atributos, costo, precio y número de serie.') }}
            </p>

            @if($errors->has('general'))
                <div class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ $errors->first('general') }}</p>
                </div>
            @endif

            @php
                $product = $this->product;
            @endphp

            @if($product && $product->category)
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Unidades serializadas ({{ count($serializedItems) }})
                        </p>
                        <button type="button"
                                wire:click="addSerializedItem"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Agregar unidad
                        </button>
                    </div>

                    @if(empty($serializedItems))
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Haz clic en "Agregar unidad" para añadir la primera unidad serializada.
                        </p>
                    @endif

                    <div class="space-y-4 max-h-[60vh] overflow-y-auto">
                        @foreach($serializedItems as $index => $item)
                            <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-gray-800">
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Unidad {{ $index + 1 }}</span>
                                    <button type="button"
                                            wire:click="removeSerializedItem({{ $index }})"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm">
                                        Eliminar
                                    </button>
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

                                {{-- Atributos de la categoría --}}
                                @if($product->category->attributes->isNotEmpty())
                                    <div class="mb-4 space-y-3">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Atributos:</p>
                                        @foreach($product->category->attributes as $attr)
                                            @php
                                                $val = $item['attribute_values'][$attr->id] ?? ($attr->type === 'boolean' ? '0' : '');
                                            @endphp
                                            @if($attr->type === 'text')
                                                <div>
                                                    <x-input-label for="item-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                    <x-text-input wire:model="serializedItems.{{ $index }}.attribute_values.{{ $attr->id }}" 
                                                                  id="item-{{ $index }}-attr-{{ $attr->id }}" 
                                                                  class="block mt-1 w-full" 
                                                                  type="text" />
                                                </div>
                                            @elseif($attr->type === 'number')
                                                <div>
                                                    <x-input-label for="item-{{ $index }}-attr-{{ $attr->id }}" value="{{ $attr->name }}" />
                                                    <x-text-input wire:model="serializedItems.{{ $index }}.attribute_values.{{ $attr->id }}" 
                                                                  id="item-{{ $index }}-attr-{{ $attr->id }}" 
                                                                  class="block mt-1 w-full" 
                                                                  type="number" step="any" />
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
                                @endif

                                {{-- Costo y Precio --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="cost-{{ $index }}" value="{{ __('Costo') }}" />
                                        <x-text-input wire:model="serializedItems.{{ $index }}.cost" 
                                                      id="cost-{{ $index }}" 
                                                      class="block mt-1 w-full" 
                                                      type="number" 
                                                      step="0.01" 
                                                      min="0" 
                                                      placeholder="0.00" />
                                    </div>
                                    <div>
                                        <x-input-label for="price-{{ $index }}" value="{{ __('Precio') }}" />
                                        <x-text-input wire:model="serializedItems.{{ $index }}.price" 
                                                      id="price-{{ $index }}" 
                                                      class="block mt-1 w-full" 
                                                      type="number" 
                                                      step="0.01" 
                                                      min="0" 
                                                      placeholder="0.00" />
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
            @else
                <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        El producto no tiene categoría asignada. Asigna una categoría al producto primero.
                    </p>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'add-initial-stock-serialized')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                @if(!empty($serializedItems))
                    <x-primary-button type="submit" wire:loading.attr="disabled">
                        {{ __('Guardar stock inicial') }}
                    </x-primary-button>
                @endif
            </div>
        </form>
    </x-modal>
</div>
