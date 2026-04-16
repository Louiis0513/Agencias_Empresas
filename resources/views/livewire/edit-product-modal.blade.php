<div x-on:open-edit-product-modal.window="$wire.loadProduct($event.detail.id || $event.detail)">
    <x-modal name="edit-product" focusable maxWidth="md">
        <form wire:submit="update" class="p-6 bg-white dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-200 dark:border-gray-600">
                {{ __('Editar producto') }}
            </h2>
            <p class="mt-2 text-sm font-medium text-gray-300">
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
                <div class="pt-2 border-t border-gray-200 dark:border-gray-600 space-y-2">
                    <x-input-label value="{{ __('Imagen del producto') }}" />
                    @if(!empty($current_image_path))
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Imagen actual:') }}
                            <a href="{{ asset('storage/'.$current_image_path) }}" target="_blank" class="text-indigo-500 hover:text-indigo-400 underline">
                                {{ __('Ver imagen') }}
                            </a>
                        </p>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('No hay imagen cargada para este producto.') }}
                        </p>
                    @endif
                    <div class="space-y-2 mt-2">
                        <input type="file"
                               wire:model="image"
                               accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-sm text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4
                                      file:rounded-md file:border-0
                                      file:text-sm file:font-semibold
                                      file:bg-indigo-50 file:text-indigo-700
                                      hover:file:bg-indigo-100
                                      dark:file:bg-gray-700 dark:file:text-gray-100 dark:hover:file:bg-gray-600" />
                        <x-input-error :messages="$errors->get('image')" class="mt-1" />
                    </div>
                    @if(!empty($current_image_path))
                        <label class="inline-flex items-center mt-2 gap-2">
                            <input type="checkbox"
                                   wire:model="remove_image"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="text-xs text-gray-600 dark:text-gray-300">
                                {{ __('Eliminar imagen actual') }}
                            </span>
                        </label>
                    @endif
                    <p class="mt-1 text-xs text-gray-400">
                        {{ __('Formatos permitidos: JPEG, PNG, WebP. Tamaño máximo 5 MB.') }}
                    </p>
                </div>
                <div>
                    <x-input-label for="edit_price" value="{{ __('Precio de venta') }} ({{ currency_symbol($this->store?->currency ?? 'COP') }})" />
                    <x-money-input wire:model="price" :currency="$this->store?->currency ?? 'COP'" :value="$price" id="edit_price" />
                    <x-input-error :messages="$errors->get('price')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="edit_margin" value="{{ __('Margen (%)') }}" />
                    <x-text-input wire:model="margin" id="edit_margin" class="block mt-1 w-full" type="number" step="0.01" min="-999" max="99.99" placeholder="Ej: 20" />
                    <p class="mt-0.5 text-xs text-gray-400">{{ __('Ingresa precio o margen, no ambos.') }}</p>
                    <x-input-error :messages="$errors->get('margin')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="edit_quantity_mode" value="{{ __('Modo de cantidad') }}" />
                        <select wire:model.live="quantity_mode"
                                id="edit_quantity_mode"
                                class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                            <option value="unit">Unidad (entero)</option>
                            <option value="decimal">Peso/decimal</option>
                        </select>
                        <x-input-error :messages="$errors->get('quantity_mode')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="edit_quantity_step" value="{{ __('Paso de cantidad') }}" />
                        <x-text-input wire:model.live="quantity_step"
                                      id="edit_quantity_step"
                                      class="block mt-1 w-full"
                                      type="number"
                                      min="0.01"
                                      step="0.01"
                                      :readonly="$quantity_mode === 'unit'" />
                        <x-input-error :messages="$errors->get('quantity_step')" class="mt-1" />
                    </div>
                </div>

                <p class="text-xs text-gray-400 -mt-1">
                    Cambiar entre decimal y unidad no altera el stock guardado; solo cambia cómo se captura y se muestra la cantidad.
                </p>
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
                        <span class="text-sm font-medium text-gray-300">{{ __('Activo') }}</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-400">{{ __('Los productos inactivos no aparecerán en ventas, compras ni movimientos de inventario.') }}</p>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-1" />
                </div>
                <div>
                    <label class="flex items-center gap-2 mt-2">
                        <input type="checkbox" wire:model="in_showcase" value="1"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-300">{{ __('Mostrar en vitrina') }}</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-400">{{ __('Si está marcado, este producto simple podrá mostrarse en la vitrina pública.') }}</p>
                    <x-input-error :messages="$errors->get('in_showcase')" class="mt-1" />
                </div>

                @if($category && $category->attributes->isNotEmpty())
                    <div class="rounded-lg border-2 border-gray-300 dark:border-gray-600 p-5 bg-white dark:bg-gray-800 shadow-sm">
                        <p class="text-base font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b-2 border-gray-300 dark:border-gray-600">{{ __('Atributos de la categoría') }}</p>
                        <div class="space-y-4">
                            @foreach($category->attributes as $attr)
                                <div>
                                    <x-input-label for="edit-attr-{{ $attr->id }}" value="{{ $attr->name }}" class="dark:text-white font-semibold" />
                                    <x-text-input wire:model="attribute_values.{{ $attr->id }}" id="edit-attr-{{ $attr->id }}" class="block mt-1 w-full" type="text" />
                                    <x-input-error :messages="$errors->get('attribute_values.' . $attr->id)" class="mt-1" />
                                </div>
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
