<div x-on:open-edit-role-modal.window="$wire.loadRole($event.detail.id || $event.detail)">
    <x-modal name="edit-role" focusable maxWidth="lg">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Editar rol') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Modifica el nombre del rol.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="edit_role_name" value="{{ __('Nombre del rol') }}" />
                <x-text-input wire:model="name"
                              id="edit_role_name"
                              class="block mt-1 w-full"
                              type="text"
                              placeholder="Ej: Cajero, Vendedor..."
                              autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div class="mt-6 max-h-64 overflow-y-auto space-y-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Permisos del rol') }}</p>
                @foreach($this->permissionsByGroup as $groupName => $permissions)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ ucfirst($groupName) }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($permissions as $permission)
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox"
                                           wire:model="selectedPermissions"
                                           value="{{ $permission->id }}"
                                           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:checked:bg-indigo-600" />
                                    <span>{{ $permission->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    {{ __('Actualizar rol') }}
                </x-primary-button>
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'edit-role')"></div>
        @endif
    </x-modal>
</div>
