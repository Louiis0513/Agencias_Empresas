<div x-on:open-modal.window="if ($event.detail === 'create-movimiento') { $wire.resetForm(); }">
    <x-modal name="create-movimiento" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Registrar movimiento') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Ingreso (entrada de dinero) o Egreso (salida).') }}
            </p>

            <div class="mt-6 space-y-4">
                @if($bolsilloId > 0)
                    <input type="hidden" wire:model="bolsillo_id" />
                    <p class="text-sm text-gray-600 dark:text-gray-400">Bolsillo: <strong>{{ $this->bolsillosActivos->firstWhere('id', $bolsilloId)?->name ?? '—' }}</strong></p>
                @else
                    <div>
                        <x-input-label for="bolsillo_id" value="{{ __('Bolsillo') }}" />
                        <select wire:model="bolsillo_id" id="bolsillo_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                            <option value="">Selecciona un bolsillo</option>
                            @foreach($this->bolsillosActivos as $b)
                                <option value="{{ $b->id }}">{{ $b->name }} — Saldo: ${{ number_format($b->saldo, 2) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('bolsillo_id')" class="mt-1" />
                    </div>
                @endif

                <div>
                    <x-input-label for="type" value="{{ __('Tipo') }}" />
                    <select wire:model="type" id="type" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="INCOME">Ingreso</option>
                        <option value="EXPENSE">Egreso</option>
                    </select>
                </div>

                <div>
                    <x-input-label for="amount" value="{{ __('Monto') }}" />
                    <x-text-input wire:model="amount" id="amount" class="block mt-1 w-full" type="number" step="0.01" min="0.01" placeholder="0.00" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="description" value="{{ __('Descripción') }}" />
                    <textarea wire:model="description" id="description" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="2" placeholder="Ej: Apertura de caja, Pago proveedor"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-movimiento')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Registrar') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
