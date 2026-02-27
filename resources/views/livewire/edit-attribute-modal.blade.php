<div x-on:open-edit-attribute-modal.window="$wire.loadAttribute($event.detail.id || $event.detail)">
    <x-modal name="edit-attribute" focusable maxWidth="2xl">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-white">
                {{ __('Editar atributo') }}
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                {{ __('Modifica los datos del atributo. Puedes cambiar el nombre, grupo y si es requerido.') }}
            </p>

            @if($errors->has('general'))
                <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ $errors->first('general') }}</p>
                </div>
            @endif

            <div class="mt-6 space-y-4">
                @if($this->groups->isNotEmpty())
                    <div>
                        <x-input-label for="edit_attribute_group_id" value="{{ __('Grupo de atributos') }} *" />
                        <select wire:model="attribute_group_id"
                                id="edit_attribute_group_id"
                                class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                            <option value="">{{ __('Selecciona un grupo') }}</option>
                            @foreach($this->groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('attribute_group_id')" class="mt-1" />
                    </div>
                @else
                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            Crea primero un <strong>grupo de atributos</strong> en la página Grupos de atributos.
                        </p>
                    </div>
                @endif

                <div>
                    <x-input-label for="edit_attribute_name" value="{{ __('Nombre del atributo') }}" />
                    <x-text-input wire:model="name" 
                                  id="edit_attribute_name" 
                                  class="block mt-1 w-full" 
                                  type="text" 
                                  placeholder="Ej: Talla, Color, Material..."
                                  autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="flex items-center">
                    <input wire:model="is_required"
                           id="edit_is_required"
                           type="checkbox"
                           class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <x-input-label for="edit_is_required" value="{{ __('Requerido en el grupo') }}" class="ml-2" />
                    <p class="ml-2 text-xs text-gray-500">Si está activo, este atributo será obligatorio al usarlo en categorías.</p>
                    <x-input-error :messages="$errors->get('is_required')" class="ml-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                @if($this->groups->isNotEmpty())
                    <x-primary-button type="submit" wire:loading.attr="disabled">
                        {{ __('Actualizar atributo') }}
                    </x-primary-button>
                @endif
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'edit-attribute')"></div>
        @endif
    </x-modal>
</div>
