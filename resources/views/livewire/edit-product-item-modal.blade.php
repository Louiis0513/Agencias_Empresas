<div x-on:open-edit-product-item-modal.window="$wire.loadProductItem($event.detail?.id ?? $event.detail)">
    <x-modal name="edit-product-item" focusable maxWidth="md">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Modificar unidad serializada') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
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
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Dejar vacío si aún no tiene precio de venta asignado.</p>
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

                <div>
                    <x-input-label for="item_features" value="{{ __('Atributos') }}" />
                    <textarea wire:model="featuresText"
                              id="item_features"
                              rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                              placeholder="Una línea por atributo:&#10;color: Rojo&#10;memoria: 128GB"></textarea>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Formato: clave: valor (una por línea).</p>
                    <x-input-error :messages="$errors->get('featuresText')" class="mt-1" />
                </div>
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
