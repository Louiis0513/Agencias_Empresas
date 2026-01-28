<div x-on:open-modal.window="if ($event.detail === 'create-movimiento-inventario') { $wire.resetForm(); }">
    <x-modal name="create-movimiento-inventario" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Registrar movimiento de inventario') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Entrada o salida de productos (solo productos con type «producto»).') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="product_id" value="{{ __('Producto') }}" />
                    <select wire:model.live="product_id" id="product_id" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">Selecciona un producto</option>
                        @foreach($this->productos as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} {{ $p->sku ? "({$p->sku})" : '' }} — Stock: {{ $p->stock }}</option>
                        @endforeach
                    </select>
                    @if($this->productos->isEmpty())
                        <p class="mt-1 text-sm text-amber-600 dark:text-amber-400">No hay productos con type «producto» en esta tienda.</p>
                    @endif
                    <x-input-error :messages="$errors->get('product_id')" class="mt-1" />
                </div>

                @if($this->productoSeleccionado)
                    <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg text-sm text-gray-600 dark:text-gray-400">
                        Stock actual: <strong>{{ $this->productoSeleccionado->stock }}</strong>
                        @if($this->type === 'SALIDA' && (int) $this->quantity > 0 && (int) $this->quantity > $this->productoSeleccionado->stock)
                            <span class="block mt-1 text-red-600 dark:text-red-400">La cantidad supera el stock disponible.</span>
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

                <div>
                    <x-input-label for="unit_cost" value="{{ __('Costo unitario (opcional, para reportes)') }}" />
                    <x-text-input wire:model="unit_cost" id="unit_cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                    <x-input-error :messages="$errors->get('unit_cost')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="description" value="{{ __('Descripción') }}" />
                    <textarea wire:model="description" id="description" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="2" placeholder="Ej: Ajuste por conteo, Compra a proveedor"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-movimiento-inventario')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Registrar') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
