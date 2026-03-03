<div x-on:open-edit-product-item-modal.window="$wire.loadProductItem($event.detail?.id ?? $event.detail)">
    <x-modal name="edit-product-item" focusable maxWidth="md">
        <form wire:submit="update" class="p-6 bg-white dark:bg-gray-800">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 pb-2 border-b border-gray-200 dark:border-gray-600">
                {{ __('Modificar unidad serializada') }}
            </h2>
            <p class="mt-2 text-sm font-medium text-gray-300">
                {{ __('Edita precio de venta, estado, número de serie o atributos.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="item_serial_number" value="{{ __('Número de serie') }}" />
                    <x-text-input wire:model="serial_number" id="item_serial_number" class="block mt-1 w-full" type="text" placeholder="Ej: SN12345" />
                    <x-input-error :messages="$errors->get('serial_number')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="item_price" value="{{ __('Precio de venta') }}" />
                    <x-text-input wire:model="price" id="item_price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00 (vacío = sin asignar)" />
                    <p class="mt-0.5 text-xs text-gray-400">Dejar vacío si aún no tiene precio de venta asignado.</p>
                    <x-input-error :messages="$errors->get('price')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="item_status" value="{{ __('Estado') }}" />
                    <select wire:model="status" id="item_status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach(\App\Models\ProductItem::estadosDisponibles() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-1" />
                </div>

                <div class="pt-2 border-t border-gray-200 dark:border-gray-600 space-y-2">
                    <x-input-label value="{{ __('Imagen de la unidad') }}" />
                    @if($current_image_path)
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Imagen actual:') }}
                            <a href="{{ asset('storage/'.$current_image_path) }}" target="_blank" class="text-indigo-500 hover:text-indigo-400 underline">
                                {{ __('Ver imagen') }}
                            </a>
                        </p>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('No hay imagen cargada para esta unidad.') }}
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
                    @if($current_image_path)
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
                    <label class="flex items-center gap-2 mt-2">
                        <input type="checkbox"
                               wire:model="in_showcase"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-300">
                            {{ __('Mostrar en vitrina') }}
                        </span>
                    </label>
                    <p class="mt-1 text-xs text-gray-400">
                        {{ __('Si está marcado, esta unidad serializada podrá mostrarse como producto en la vitrina pública.') }}
                    </p>
                    <x-input-error :messages="$errors->get('in_showcase')" class="mt-1" />
                </div>

                @if($attributes->isNotEmpty())
                    <div>
                        <x-input-label value="{{ __('Atributos') }}" />
                        <div class="mt-2 space-y-3">
                            @foreach($attributes as $attribute)
                                <div>
                                    <x-input-label for="attr_{{ $attribute->id }}" value="{{ $attribute->name }}" />
                                    <x-text-input wire:model="attributeValues.{{ $attribute->id }}" 
                                                  id="attr_{{ $attribute->id }}" 
                                                  class="block mt-1 w-full" 
                                                  type="text" 
                                                  placeholder="{{ __('Valor del atributo') }}" />
                                    <x-input-error :messages="$errors->get('attributeValues.' . $attribute->id)" class="mt-1" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'edit-product-item')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    {{ __('Guardar cambios') }}
                </x-primary-button>
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'edit-product-item')"></div>
        @endif
    </x-modal>
</div>
