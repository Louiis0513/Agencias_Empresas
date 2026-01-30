<div x-on:open-modal.window="if ($event.detail === 'create-movimiento-activo') { $wire.resetForm(); }">
    <x-modal name="create-movimiento-activo" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Registrar movimiento de activo') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Entrada (compra, donación) o salida (baja, venta, pérdida) de activos fijos.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="activo_id" value="{{ __('Activo') }}" />
                    <select wire:model.live="activo_id" id="activo_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">Selecciona un activo</option>
                        @foreach($this->activos as $a)
                            <option value="{{ $a->id }}">{{ $a->name }} {{ $a->code ? "({$a->code})" : '' }} — Cantidad: {{ $a->quantity }}</option>
                        @endforeach
                    </select>
                    @if($this->activos->isEmpty())
                        <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">No hay activos en esta tienda.</p>
                    @endif
                    <x-input-error :messages="$errors->get('activo_id')" class="mt-1" />
                </div>

                @if($this->activoSeleccionado)
                    <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg text-sm text-gray-600 dark:text-gray-400">
                        Cantidad actual: <strong>{{ $this->activoSeleccionado->quantity }}</strong>
                        @if($this->type === 'SALIDA' && (int) $this->quantity > 0 && (int) $this->quantity > $this->activoSeleccionado->quantity)
                            <span class="block mt-1 text-red-600 dark:text-red-400">La cantidad supera la disponible.</span>
                        @endif
                    </div>
                @endif

                <div>
                    <x-input-label for="type" value="{{ __('Tipo') }}" />
                    <select wire:model.live="type" id="type" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="ENTRADA">Entrada</option>
                        <option value="SALIDA">Salida</option>
                    </select>
                </div>

                <div>
                    <x-input-label for="quantity" value="{{ __('Cantidad') }}" />
                    <x-text-input wire:model="quantity" id="quantity" class="block mt-1 w-full" type="number" min="1" placeholder="1" required />
                    <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                </div>

                @if($this->type === 'ENTRADA')
                    <div>
                        <x-input-label for="unit_cost" value="{{ __('Costo unitario (para trazabilidad)') }}" />
                        <x-text-input wire:model="unit_cost" id="unit_cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ej: 4 sillas a $50 c/u en enero, 2 sillas a $65 c/u en marzo.</p>
                        <x-input-error :messages="$errors->get('unit_cost')" class="mt-1" />
                    </div>
                @endif

                <div>
                    <x-input-label for="description" value="{{ __('Descripción') }}" />
                    <textarea wire:model="description" id="description" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="2" placeholder="Ej: Compra a proveedor, Baja por obsolescencia"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-movimiento-activo')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Registrar') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
