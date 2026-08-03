<div x-on:open-modal.window="if ($event.detail === 'create-bolsillo') { $wire.resetForm(); }">
    <x-modal name="create-bolsillo" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-white">
                {{ __('Crear Bolsillo') }}
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                Dinero del <span class="text-gray-200">Disponible (11)</span>: efectivo o bancos.
                Se crea automáticamente la cuenta auxiliar en el plan de cuentas.
            </p>
            <div class="mt-4">
                @include('stores.partials.flujo-bolsillo-incompleto')
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" placeholder="Ej: Efectivo mostrador, Bancolombia" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="tipo_disponible" value="{{ __('Tipo') }}" />
                    <select wire:model.live="tipo_disponible" id="tipo_disponible"
                            class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand">
                        <option value="efectivo">Efectivo y equivalentes (caja) — 110505</option>
                        <option value="corriente_cop">Cuenta corriente en pesos — 111005</option>
                        <option value="ahorro">Cuenta de ahorro — 112005</option>
                        <option value="divisas">Cuenta en moneda extranjera — 111010</option>
                    </select>
                    <x-input-error :messages="$errors->get('tipo_disponible')" class="mt-1" />
                    @if($this->codigoPreview)
                        <p class="mt-1 text-xs text-gray-400">
                            Cuenta auxiliar que se creará:
                            <span class="font-mono text-brand">{{ $this->codigoPreview }}</span>
                        </p>
                    @elseif($this->codigoPadre)
                        <p class="mt-1 text-xs text-amber-400">
                            No existe la cuenta padre <span class="font-mono">{{ $this->codigoPadre }}</span>.
                            Importa el PUC base en Contabilidad → Plan de cuentas.
                        </p>
                    @endif
                </div>

                <div>
                    <x-input-label for="detalles" value="{{ __('Detalles') }}" />
                    <textarea wire:model="detalles" id="detalles" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand" rows="3" placeholder="Ej: Nº de cuenta, banco, notas..."></textarea>
                    <p class="mt-1 text-xs text-gray-400">Opcional. Datos para identificar este bolsillo.</p>
                    <x-input-error :messages="$errors->get('detalles')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="saldo" value="{{ __('Saldo inicial') }} ({{ currency_symbol($this->store?->currency ?? 'COP') }})" />
                    <x-money-input wire:model="saldo" :currency="$this->store?->currency ?? 'COP'" :value="$saldo" id="saldo" />
                    <p class="mt-1 text-xs text-gray-400">Opcional. Si indica un monto, se creará un Comprobante de ingreso por el saldo inicial.</p>
                    <x-input-error :messages="$errors->get('saldo')" class="mt-1" />
                </div>

                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Visible en operaciones</span>
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
