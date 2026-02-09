<div x-on:open-edit-product-modal.window="$wire.loadProduct($event.detail.id || $event.detail)">
    <x-modal name="edit-product" focusable maxWidth="md">
        <form wire:submit="update" class="p-6 bg-white dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-200 dark:border-gray-600">
                {{ __('Editar producto') }}
            </h2>
            <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                @if($productType === 'simple')
                    {{ __('Modifica nombre, precio, ubicación y atributos del producto.') }}
                @else
                    {{ __('Modifica el nombre y la ubicación del producto. Los precios y atributos se gestionan por variante/unidad.') }}
                @endif
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="edit_name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="edit_name" class="block mt-1 w-full" type="text" placeholder="Ej: Suéter azul, Leche entera 1L" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                @if($productType === 'simple')
                <div>
                    <x-input-label for="edit_price" value="{{ __('Precio (€)') }}" />
                    <x-text-input wire:model="price" id="edit_price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                    <x-input-error :messages="$errors->get('price')" class="mt-1" />
                </div>
                @endif

                <div>
                    <x-input-label for="edit_location" value="{{ __('Ubicación') }}" />
                    <x-text-input wire:model="location" id="edit_location" class="block mt-1 w-full" type="text" placeholder="Ej: Estantería A2" />
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                @if($productType === 'simple')
                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="is_active" value="1"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Activo') }}</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Los productos inactivos no aparecerán en ventas, compras ni movimientos de inventario.') }}</p>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-1" />
                </div>

                @if($category && $category->attributes->isNotEmpty())
                    <div class="rounded-lg border-2 border-gray-300 dark:border-gray-600 p-5 bg-white dark:bg-gray-800 shadow-sm">
                        <p class="text-base font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b-2 border-gray-300 dark:border-gray-600">{{ __('Atributos de la categoría') }}</p>
                        <div class="space-y-4">
                            @foreach($category->attributes as $attr)
                                @php
                                    $val = $attribute_values[$attr->id] ?? ($attr->type === 'boolean' ? '0' : '');
                                @endphp
                                @if($attr->type === 'text')
                                    <div>
                                        <x-input-label for="edit-attr-{{ $attr->id }}" value="{{ $attr->name }}" class="dark:text-white font-semibold" />
                                        <x-text-input wire:model="attribute_values.{{ $attr->id }}" id="edit-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                        <x-input-error :messages="$errors->get('attribute_values.' . $attr->id)" class="mt-1" />
                                    </div>
                                @elseif($attr->type === 'number')
                                    <div>
                                        <x-input-label for="edit-attr-{{ $attr->id }}" value="{{ $attr->name }}" class="dark:text-white font-semibold" />
                                        <x-text-input wire:model="attribute_values.{{ $attr->id }}" id="edit-attr-{{ $attr->id }}" class="block mt-1 w-full" type="number" step="any" />
                                        <x-input-error :messages="$errors->get('attribute_values.' . $attr->id)" class="mt-1" />
                                    </div>
                                @elseif($attr->type === 'select')
                                    <div>
                                        <x-input-label for="edit-attr-{{ $attr->id }}" value="{{ $attr->name }}" class="dark:text-white font-semibold" />
                                        <select wire:model="attribute_values.{{ $attr->id }}"
                                                id="edit-attr-{{ $attr->id }}"
                                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="">{{ __('Selecciona') }}</option>
                                            @foreach($attr->options as $opt)
                                                <option value="{{ $opt->value }}">{{ $opt->value }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('attribute_values.' . $attr->id)" class="mt-1" />
                                    </div>
                                @elseif($attr->type === 'boolean')
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox"
                                                   wire:model.live="attribute_values.{{ $attr->id }}"
                                                   value="1"
                                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $attr->name }}</span>
                                        </label>
                                        <x-input-error :messages="$errors->get('attribute_values.' . $attr->id)" class="mt-1" />
                                    </div>
                                @else
                                    <div>
                                        <x-input-label for="edit-attr-{{ $attr->id }}" value="{{ $attr->name }}" class="dark:text-white font-semibold" />
                                        <x-text-input wire:model="attribute_values.{{ $attr->id }}" id="edit-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                        <x-input-error :messages="$errors->get('attribute_values.' . $attr->id)" class="mt-1" />
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    {{ __('Actualizar producto') }}
                </x-primary-button>
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'edit-product')"></div>
        @endif
    </x-modal>
</div>
