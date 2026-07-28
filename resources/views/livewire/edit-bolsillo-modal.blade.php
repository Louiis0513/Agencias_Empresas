<div x-on:open-edit-bolsillo-modal.window="$wire.loadBolsillo($event.detail?.id ?? $event.detail)">
    <x-modal name="edit-bolsillo" focusable maxWidth="2xl">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-white">
                {{ __('Editar Bolsillo') }}
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                {{ __('El saldo solo se modifica mediante movimientos. El tipo contable (caja/banco) lo define la cuenta PUC vinculada.') }}
            </p>

            <div class="mt-6 space-y-4">
                @if($cuentaCodigo)
                    <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm">
                        <span class="text-gray-400">Cuenta contable:</span>
                        <span class="font-mono text-brand ml-1">{{ $cuentaCodigo }}</span>
                        <span class="text-gray-300 ml-1">{{ $cuentaNombre }}</span>
                        <span class="text-xs text-gray-500 ml-2">{{ $isBankAccount ? 'Banco' : 'Efectivo' }}</span>
                    </div>
                @else
                    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
                        Sin cuenta contable vinculada. Ejecuta el vínculo desde Contabilidad o vuelve a crear el bolsillo tras importar el PUC.
                    </div>
                @endif

                <div>
                    <x-input-label for="edit_bolsillo_name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="edit_bolsillo_name" class="block mt-1 w-full" type="text" placeholder="Ej: Efectivo" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="edit_detalles" value="{{ __('Detalles') }}" />
                    <textarea wire:model="detalles" id="edit_detalles" class="block mt-1 w-full rounded-md border-white/10 bg-white/5 text-gray-100 focus:ring-brand focus:border-brand" rows="3" placeholder="Nº cuenta, etc."></textarea>
                    <x-input-error :messages="$errors->get('detalles')" class="mt-1" />
                </div>

                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Visible en operaciones</span>
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
