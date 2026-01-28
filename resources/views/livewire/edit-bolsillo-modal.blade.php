<div x-on:open-edit-bolsillo-modal.window="$wire.loadBolsillo($event.detail?.id ?? $event.detail)">
    <x-modal name="edit-bolsillo" focusable maxWidth="2xl">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Editar Bolsillo') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('El saldo solo se modifica mediante movimientos.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="edit_bolsillo_name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="edit_bolsillo_name" class="block mt-1 w-full" type="text" placeholder="Ej: Efectivo" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="edit_detalles" value="{{ __('Detalles') }}" />
                    <textarea wire:model="detalles" id="edit_detalles" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Nº cuenta, etc."></textarea>
                    <x-input-error :messages="$errors->get('detalles')" class="mt-1" />
                </div>

                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="is_bank_account" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Es cuenta bancaria</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Activo</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'edit-bolsillo')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Guardar') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
