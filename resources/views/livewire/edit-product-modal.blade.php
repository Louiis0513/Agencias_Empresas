<div x-on:open-edit-product-modal.window="$wire.loadProduct($event.detail.id || $event.detail)">
    <x-modal name="edit-product" focusable maxWidth="md">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Editar producto') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Modifica nombre, precio y ubicación del producto.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="edit_name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="edit_name" class="block mt-1 w-full" type="text" placeholder="Ej: Suéter azul, Leche entera 1L" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="edit_price" value="{{ __('Precio (€)') }}" />
                    <x-text-input wire:model="price" id="edit_price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                    <x-input-error :messages="$errors->get('price')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="edit_location" value="{{ __('Ubicación') }}" />
                    <x-text-input wire:model="location" id="edit_location" class="block mt-1 w-full" type="text" placeholder="Ej: Estantería A2" />
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>
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
