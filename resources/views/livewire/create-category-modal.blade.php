<div>
    <x-modal name="create-category" focusable maxWidth="lg">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear categoría') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Organiza tus productos con categorías. Puedes crear subcategorías seleccionando una categoría padre.') }}
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

                @if($this->store && $this->store->categories->isNotEmpty())
                    <div>
                        <x-input-label for="parent_id" value="{{ __('Categoría padre (opcional)') }}" />
                        <select wire:model="parent_id" 
                                id="parent_id" 
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('Sin categoría padre (categoría raíz)') }}</option>
                            @foreach($this->store->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Deja vacío para crear una categoría principal, o selecciona una categoría existente para crear una subcategoría.') }}
                        </p>
                        <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
                    </div>
                @endif
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
