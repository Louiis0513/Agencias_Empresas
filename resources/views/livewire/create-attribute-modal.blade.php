<div x-data
     x-on:open-create-attribute.window="$wire.setGroupId($event.detail.groupId).then(() => $dispatch('open-modal', 'create-attribute'))">
    <x-modal name="create-attribute" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear atributo') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @if($attribute_group_id)
                    {{ __('Se añadirá al grupo:') }} <strong>{{ $this->getGroupName() }}</strong>
                @else
                    {{ __('Cada atributo debe pertenecer a un grupo. Indica si es requerido u opcional dentro del grupo.') }}
                @endif
            </p>

            <div class="mt-6 space-y-4">
                @if($this->groups->isNotEmpty() && !$attribute_group_id)
                    <div>
                        <x-input-label for="attribute_group_id" value="{{ __('Grupo de atributos') }} *" />
                        <select wire:model="attribute_group_id"
                                id="attribute_group_id"
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('Selecciona un grupo') }}</option>
                            @foreach($this->groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('attribute_group_id')" class="mt-1" />
                    </div>
                @endif
                @if($this->groups->isEmpty())
                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            Crea primero un <strong>grupo de atributos</strong> en la página Grupos de atributos.
                        </p>
                    </div>
                @endif

                <div>
                    <x-input-label for="name" value="{{ __('Nombre del atributo') }}" />
                    <x-text-input wire:model="name" 
                                  id="name" 
                                  class="block mt-1 w-full" 
                                  type="text" 
                                  placeholder="Ej: Talla, Color, Material..."
                                  autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="code" value="{{ __('Código (opcional)') }}" />
                    <x-text-input wire:model="code" 
                                  id="code" 
                                  class="block mt-1 w-full" 
                                  type="text" 
                                  placeholder="Ej: size, color, material (se genera automáticamente si se deja vacío)" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Código único para identificar el atributo. Si se deja vacío, se generará automáticamente desde el nombre.
                    </p>
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="type" value="{{ __('Tipo de atributo') }}" />
                    <select wire:model.live="type" 
                            id="type" 
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="text">Texto</option>
                        <option value="number">Número</option>
                        <option value="select">Selección (con opciones predefinidas)</option>
                        <option value="boolean">Sí/No (Booleano)</option>
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>

                @if($this->type === 'select')
                    <div>
                        <x-input-label value="{{ __('Opciones del atributo') }}" />
                        <p class="mt-1 mb-3 text-xs text-gray-500 dark:text-gray-400">
                            Define las opciones disponibles para este atributo (ej: S, M, L, XL para Talla).
                        </p>
                        <div class="space-y-2">
                            @foreach($this->options as $index => $option)
                                <div class="flex items-center space-x-2">
                                    <x-text-input wire:model="options.{{ $index }}" 
                                                  class="block flex-1" 
                                                  type="text" 
                                                  placeholder="Ej: S, M, L, XL..." />
                                    @if(count($this->options) > 1)
                                        <button type="button" 
                                                wire:click="removeOption({{ $index }})"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                            <button type="button" 
                                    wire:click="addOption"
                                    class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                + Añadir otra opción
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('options')" class="mt-1" />
                        <x-input-error :messages="$errors->get('options.*')" class="mt-1" />
                    </div>
                @endif

                <div class="flex items-center">
                    <input wire:model="is_required"
                           id="is_required"
                           type="checkbox"
                           class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <x-input-label for="is_required" value="{{ __('Requerido en el grupo') }}" class="ml-2" />
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
                        {{ __('Crear atributo') }}
                    </x-primary-button>
                @endif
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'create-attribute')"></div>
        @endif
    </x-modal>
</div>
