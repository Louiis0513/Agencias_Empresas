<div>
    @php
        $modalName = $fromPurchase ? 'create-activo-from-compra' : 'create-activo';
    @endphp
    <x-modal :name="$modalName" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Crear Activo
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @if($fromPurchase)
                    El activo se creará con cantidad 0. La compra actual inyectará el stock al aprobarse.
                @else
                    Se suma automáticamente al aprobar compras de tipo Activo Fijo.
                @endif
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="activo_name" value="Nombre *" />
                    <x-text-input wire:model="name" id="activo_name" class="block mt-1 w-full" type="text" placeholder="Ej: Computadora, Escritorio" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="activo_code" value="Código" />
                    <x-text-input wire:model="code" id="activo_code" class="block mt-1 w-full" type="text" placeholder="Ej: ACT-001" />
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="activo_description" value="Descripción" />
                    <textarea wire:model="description" id="activo_description" rows="2"
                              class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="Descripción opcional"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="activo_quantity" value="Cantidad *" />
                        <x-text-input wire:model="quantity" id="activo_quantity" class="block mt-1 w-full" type="number" min="0" step="1" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($fromPurchase)
                                Será 0. La compra inyectará el stock.
                            @else
                                Se suma automáticamente al aprobar compras.
                            @endif
                        </p>
                        <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="activo_unit_cost" value="Costo unitario" />
                        <x-text-input wire:model="unit_cost" id="activo_unit_cost" class="block mt-1 w-full" type="number" min="0" step="0.01" placeholder="0.00" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se actualiza automáticamente al aprobar compras.</p>
                        <x-input-error :messages="$errors->get('unit_cost')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="activo_location" value="Ubicación" />
                    <x-text-input wire:model="location" id="activo_location" class="block mt-1 w-full" type="text" placeholder="Ej: Escritorio, Almacén" />
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                <div class="flex items-center">
                    <input wire:model="is_active" id="activo_is_active" type="checkbox"
                           class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <x-input-label for="activo_is_active" value="Activo" class="ml-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button"
                                    x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                    Cancelar
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    Crear Activo
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
