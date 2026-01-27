<div>
    <x-modal name="create-attribute-group" focusable maxWidth="lg">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear grupo de atributos') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Ej: Talla, Marca, Detalles. Luego añade atributos a cada grupo e indica si son requeridos.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="name" value="{{ __('Nombre del grupo') }}" />
                <x-text-input wire:model="name"
                              id="name"
                              class="block mt-1 w-full"
                              type="text"
                              placeholder="Ej: Talla, Marca, Especificaciones..."
                              autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    {{ __('Crear grupo') }}
                </x-primary-button>
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'create-attribute-group')"></div>
        @endif
    </x-modal>
</div>
