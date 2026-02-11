<div x-data
     x-on:open-create-category.window="$wire.clearParentId().then(() => $dispatch('open-modal', 'create-category'))"
     x-on:open-create-subcategory.window="$wire.setParentId($event.detail.parentId).then(() => $dispatch('open-modal', 'create-category'))">
    <x-modal name="create-category" focusable maxWidth="lg">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ $parent_id ? __('Crear subcategoría') : __('Crear categoría') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @if($parent_id)
                    {{ __('Se creará dentro de:') }} <strong>{{ $this->getParentCategoryName() }}</strong>
                @else
                    {{ __('Organiza tus productos con categorías.') }}
                @endif
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="name" value="{{ __('Nombre de la categoría') }}" />
                    <x-text-input wire:model="name" 
                                  id="name" 
                                  class="block mt-1 w-full" 
                                  type="text" 
                                  placeholder="Ej: Electrónica, Ropa, Alimentos..."
                                  autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    {{ __('Crear categoría') }}
                </x-primary-button>
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'create-category')"></div>
        @endif
    </x-modal>
</div>
