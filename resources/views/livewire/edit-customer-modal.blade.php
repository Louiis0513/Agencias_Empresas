<div x-on:open-edit-customer-modal.window="$wire.loadCustomer($event.detail.id || $event.detail.customerId || $event.detail)">
    <x-modal name="edit-customer" focusable maxWidth="2xl">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Editar Cliente') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('El cliente se vinculará automáticamente a un usuario si existe uno con el mismo email.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="edit_name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="edit_name" class="block mt-1 w-full" type="text" placeholder="Nombre completo del cliente" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="edit_email" value="{{ __('Email') }}" />
                    <x-text-input wire:model="email" id="edit_email" class="block mt-1 w-full" type="email" placeholder="correo@ejemplo.com" required />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Si existe un usuario con este email, se vinculará automáticamente.
                    </p>
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="edit_phone" value="{{ __('Teléfono') }}" />
                        <x-text-input wire:model="phone" id="edit_phone" class="block mt-1 w-full" type="text" placeholder="+1234567890" required />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="edit_document_number" value="{{ __('Número de Documento') }}" />
                        <x-text-input wire:model="document_number" id="edit_document_number" class="block mt-1 w-full" type="text" placeholder="DNI, Cédula, Pasaporte" required />
                        <x-input-error :messages="$errors->get('document_number')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="edit_address" value="{{ __('Dirección') }}" />
                    <textarea wire:model="address" id="edit_address" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Dirección completa"></textarea>
                    <x-input-error :messages="$errors->get('address')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'edit-customer')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Actualizar Cliente') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
