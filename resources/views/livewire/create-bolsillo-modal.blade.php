<div x-on:open-modal.window="if ($event.detail === 'create-bolsillo') { $wire.resetForm(); }">
    <x-modal name="create-bolsillo" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear Bolsillo') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Efectivo, cuenta corriente, etc. Usa "Detalles" para nº de cuenta u otra información.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" placeholder="Ej: Efectivo, Cuenta corriente" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="detalles" value="{{ __('Detalles') }}" />
                    <textarea wire:model="detalles" id="detalles" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Ej: Nº cuenta corriente, datos del bolsillo..."></textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opcional. Datos que necesites para identificar este bolsillo.</p>
                    <x-input-error :messages="$errors->get('detalles')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="saldo" value="{{ __('Saldo inicial') }}" />
                    <x-text-input wire:model="saldo" id="saldo" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opcional. Si indica un monto, se creará un Comprobante de ingreso "Saldo inicial desde creación del bolsillo" para trazabilidad.</p>
                    <x-input-error :messages="$errors->get('saldo')" class="mt-1" />
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
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-bolsillo')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Crear Bolsillo') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
