<div x-on:open-edit-attribute-group-modal.window="$wire.loadGroup($event.detail.id || $event.detail)">
    <x-modal name="edit-attribute-group" focusable maxWidth="lg">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Editar grupo de atributos') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Modifica el nombre del grupo de atributos.') }}
            </p>

            @if($errors->has('general'))
                <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ $errors->first('general') }}</p>
                </div>
            @endif

            <div class="mt-6">
                <x-input-label for="edit_group_name" value="{{ __('Nombre del grupo') }}" />
                <x-text-input wire:model="name"
                              id="edit_group_name"
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
                    {{ __('Actualizar grupo') }}
                </x-primary-button>
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'edit-attribute-group')"></div>
        @endif
    </x-modal>
</div>
