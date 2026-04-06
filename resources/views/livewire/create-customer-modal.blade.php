<div>
    <x-modal name="create-customer" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-white">
                {{ __('Crear Cliente') }}
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                {{ __('El cliente se vinculará automáticamente a un usuario si existe uno con el mismo email.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" placeholder="Nombre completo del cliente" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="email" value="{{ __('Email') }}" />
                    <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" placeholder="correo@ejemplo.com" required />
                    <p class="mt-1 text-xs text-gray-400">
                        Si existe un usuario con este email, se vinculará automáticamente.
                    </p>
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="min-w-0">
                        <x-input-label for="phone" value="{{ __('Teléfono') }}" />
                        <p class="text-xs text-gray-400 mt-1">Indicativo (ej. 57) y número. Solo dígitos.</p>
                        <div class="mt-1 grid grid-cols-[auto,5rem,minmax(0,1fr)] items-center gap-2">
                            <span class="text-white font-medium">+</span>
                            <input type="text" wire:model="phone_country_code" placeholder="57" maxlength="4" inputmode="numeric" pattern="[0-9]*"
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand text-center">
                            <x-text-input wire:model="phone" id="phone" class="block w-full min-w-0" type="text" placeholder="3001234567" inputmode="numeric" required />
                        </div>
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                        <x-input-error :messages="$errors->get('phone_country_code')" class="mt-1" />
                    </div>
                    <div class="min-w-0">
                        <x-input-label for="document_number" value="{{ __('Número de Documento') }}" />
                        <x-text-input wire:model="document_number" id="document_number" class="block mt-1 w-full min-w-0" type="text" placeholder="DNI, Cédula, Pasaporte" required />
                        <x-input-error :messages="$errors->get('document_number')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="address" value="{{ __('Dirección') }}" />
                    <textarea wire:model="address" id="address" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand" rows="3" placeholder="Dirección completa"></textarea>
                    <x-input-error :messages="$errors->get('address')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="gender" value="{{ __('Género') }}" />
                        <select wire:model="gender" id="gender" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                            <option value="">— Seleccionar —</option>
                            <option value="M">M</option>
                            <option value="F">F</option>
                            <option value="NN">NN</option>
                        </select>
                        <x-input-error :messages="$errors->get('gender')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="blood_type" value="{{ __('Tipo de sangre') }}" />
                        <x-text-input wire:model="blood_type" id="blood_type" class="block mt-1 w-full" type="text" placeholder="Ej. O+, A-" />
                        <x-input-error :messages="$errors->get('blood_type')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="eps" value="{{ __('EPS') }}" />
                        <x-text-input wire:model="eps" id="eps" class="block mt-1 w-full" type="text" placeholder="Entidad promotora de salud" />
                        <x-input-error :messages="$errors->get('eps')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="birth_date" value="{{ __('Fecha de nacimiento') }}" />
                        <x-text-input wire:model="birth_date" id="birth_date" class="block mt-1 w-full" type="date" />
                        <x-input-error :messages="$errors->get('birth_date')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="emergency_contact_name" value="{{ __('Nombre contacto emergencia') }}" />
                        <x-text-input wire:model="emergency_contact_name" id="emergency_contact_name" class="block mt-1 w-full" type="text" placeholder="Nombre completo" />
                        <x-input-error :messages="$errors->get('emergency_contact_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="emergency_contact_phone" value="{{ __('Número contacto emergencia') }}" />
                        <p class="text-xs text-gray-400 mt-1">Solo números.</p>
                        <x-text-input wire:model="emergency_contact_phone" id="emergency_contact_phone" class="block mt-1 w-full" type="text" placeholder="3001234567" inputmode="numeric" />
                        <x-input-error :messages="$errors->get('emergency_contact_phone')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-customer')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Crear Cliente') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
