<div>
    {{-- 1. BOTÓN ACTIVADOR --}}
    <button x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'create-store')"
            class="bg-brand text-white font-bold py-2 px-4 rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Crear Nueva Tienda
    </button>

    {{-- 2. VENTANA MODAL (teleport a body para que fixed cubra toda la pantalla, no el header con backdrop-blur) --}}
    @teleport('body')
    <x-modal name="create-store" focusable maxWidth="4xl">
        <div class="p-6 max-h-[85vh] overflow-y-auto">
            <h2 class="text-lg font-medium text-white">
                {{ __('Crear una nueva tienda') }}
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                {{ __('Configura los datos de tu negocio. Los campos con valores por defecto están preconfigurados para Colombia.') }}
            </p>

            {{-- Información del plan --}}
            <div class="mt-4 text-sm text-gray-400 p-3 bg-white/5 rounded-lg border border-white/10">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-300">
                        Plan Actual: <strong class="text-white">{{ $this->plan->name ?? 'Sin Plan' }}</strong>
                    </span>
                    @if($this->storeCount < $this->storeLimit)
                        <span class="text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold px-2 py-1 rounded-full">
                            Disponible
                        </span>
                    @else
                        <a href="#" class="text-brand hover:text-white hover:underline text-xs font-bold transition">
                            Mejorar Plan &rarr;
                        </a>
                    @endif
                </div>
                <div class="w-full bg-white/10 rounded-full h-2.5 overflow-hidden">
                    <div class="{{ $this->storeCount >= $this->storeLimit ? 'bg-red-500' : 'bg-brand' }} h-2.5 rounded-full transition-all duration-500"
                         style="width: {{ $this->progressPercent }}%">
                    </div>
                </div>
                <p class="mt-2 text-xs text-right text-gray-400">
                    Has usado <span class="font-bold text-white">{{ $this->storeCount }}</span>
                    de <span class="font-bold text-white">{{ $this->storeLimit }}</span> tiendas.
                </p>
            </div>

            <form wire:submit="save" class="mt-6 space-y-6">
                {{-- Datos básicos --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Datos básicos</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label for="name" value="{{ __('Nombre de la Tienda') }}" />
                            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" placeholder="Ej: Restaurante La Plaza" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="rut_nit" value="{{ __('RUT/NIT') }}" />
                            <x-text-input wire:model="rut_nit" id="rut_nit" class="block mt-1 w-full" type="text" placeholder="Número de identificación tributaria" />
                            <x-input-error :messages="$errors->get('rut_nit')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="currency" value="{{ __('Moneda') }}" />
                            <select wire:model="currency" id="currency" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                                <option value="COP">COP - Peso colombiano</option>
                                <option value="USD">USD - Dólar</option>
                                <option value="MXN">MXN - Peso mexicano</option>
                                <option value="ARS">ARS - Peso argentino</option>
                                <option value="CLP">CLP - Peso chileno</option>
                                <option value="PEN">PEN - Sol peruano</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="regimen" value="{{ __('Régimen') }}" />
                            <x-text-input wire:model="regimen" id="regimen" class="block mt-1 w-full" type="text" placeholder="Ej: Régimen simplificado" />
                        </div>
                        <div>
                            <x-input-label for="domain" value="{{ __('Dominio') }}" />
                            <x-text-input wire:model="domain" id="domain" class="block mt-1 w-full" type="text" placeholder="mitienda.com" />
                        </div>
                    </div>
                </div>

                {{-- Ubicación y zona horaria --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Ubicación y zona horaria</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="timezone" value="{{ __('Zona horaria') }}" />
                            <select wire:model="timezone" id="timezone" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                                <option value="America/Bogota">COT (Colombia) - UTC-05:00</option>
                                <option value="America/Mexico_City">CST (México) - UTC-06:00</option>
                                <option value="America/Argentina/Buenos_Aires">ART (Argentina) - UTC-03:00</option>
                                <option value="America/Lima">PET (Perú) - UTC-05:00</option>
                                <option value="America/Santiago">CLT (Chile) - UTC-04:00</option>
                                <option value="America/Caracas">VET (Venezuela) - UTC-04:00</option>
                                <option value="America/Guayaquil">ECT (Ecuador) - UTC-05:00</option>
                                <option value="Europe/Madrid">CET (España) - UTC+01:00</option>
                                <option value="America/New_York">EST (USA Este) - UTC-05:00</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="date_format" value="{{ __('Formato de fecha') }}" />
                            <select wire:model="date_format" id="date_format" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                                <option value="d-m-Y">d-MM-YYYY (31-12-2025)</option>
                                <option value="Y-m-d">YYYY-MM-dd (2025-12-31)</option>
                                <option value="m/d/Y">MM/dd/YYYY (12/31/2025)</option>
                                <option value="d/m/Y">dd/MM/YYYY (31/12/2025)</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="time_format" value="{{ __('Formato de hora') }}" />
                            <select wire:model="time_format" id="time_format" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                                <option value="24">24 horas</option>
                                <option value="12">12 horas (AM/PM)</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="country" value="{{ __('País') }}" />
                            <x-text-input wire:model="country" id="country" class="block mt-1 w-full" type="text" placeholder="Colombia" />
                        </div>
                        <div>
                            <x-input-label for="department" value="{{ __('Departamento/Provincia') }}" />
                            <x-text-input wire:model="department" id="department" class="block mt-1 w-full" type="text" placeholder="Antioquia" />
                        </div>
                        <div>
                            <x-input-label for="city" value="{{ __('Ciudad') }}" />
                            <x-text-input wire:model="city" id="city" class="block mt-1 w-full" type="text" placeholder="Medellín" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address" value="{{ __('Dirección') }}" />
                            <x-text-input wire:model="address" id="address" class="block mt-1 w-full" type="text" placeholder="Calle 123 #45-67" />
                        </div>
                    </div>
                </div>

                {{-- Contacto --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Contacto</h3>
                    <p class="text-sm text-gray-400 mb-4">Solo caracteres numéricos (incluyendo indicativo de país sin +).</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phone" value="{{ __('Teléfono') }}" />
                            <x-text-input wire:model="phone" id="phone" class="block mt-1 w-full" type="text" placeholder="573001234567" inputmode="numeric" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="mobile" value="{{ __('Celular') }}" />
                            <x-text-input wire:model="mobile" id="mobile" class="block mt-1 w-full" type="text" placeholder="573001234567" inputmode="numeric" />
                            <x-input-error :messages="$errors->get('mobile')" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- Logo --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Logo</h3>
                    <p class="text-sm text-gray-400 mb-4">Opcional. Se convertirá automáticamente a WebP.</p>
                    <input type="file" wire:model="logo" accept="image/*" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand file:text-white file:font-medium hover:file:opacity-90">
                    @if ($logo)
                        <p class="mt-2 text-sm text-emerald-400">Imagen seleccionada.</p>
                    @endif
                    <x-input-error :messages="$errors->get('logo')" class="mt-1" />
                </div>

                {{-- Botones --}}
                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Cancelar') }}
                    </x-secondary-button>
                    @if($this->storeCount >= $this->storeLimit)
                        <x-primary-button class="opacity-50 cursor-not-allowed" disabled>
                            {{ __('Límite Alcanzado') }}
                        </x-primary-button>
                    @else
                        <x-primary-button type="submit" wire:loading.attr="disabled">
                            {{ __('Crear Tienda') }}
                        </x-primary-button>
                    @endif
                </div>
            </form>

            @if($errors->any())
                <div x-init="$dispatch('open-modal', 'create-store')"></div>
            @endif
        </div>
    </x-modal>
    @endteleport
</div>
