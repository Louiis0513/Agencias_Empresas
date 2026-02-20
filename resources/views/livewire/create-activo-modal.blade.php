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
                Un activo es un ítem único identificado por su número de serie (único en la tienda).
                @if($fromPurchase)
                    Al aprobar la compra podrás indicar más seriales si la cantidad es mayor a 1.
                @endif
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="activo_name" value="Nombre *" />
                    <x-text-input wire:model="name" id="activo_name" class="block mt-1 w-full" type="text" placeholder="Ej: Computadora, Escritorio" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="activo_serial_number" value="Número de serie *" />
                    <x-text-input wire:model="serial_number" id="activo_serial_number" class="block mt-1 w-full" type="text" placeholder="Ej: SN123456" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Único en esta tienda.</p>
                    <x-input-error :messages="$errors->get('serial_number')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="activo_code" value="Código" />
                    <x-text-input wire:model="code" id="activo_code" class="block mt-1 w-full" type="text" placeholder="Ej: ACT-001" />
                    <x-input-error :messages="$errors->get('code')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="activo_model" value="Modelo" />
                        <x-text-input wire:model="model" id="activo_model" class="block mt-1 w-full" type="text" placeholder="Ej: XPS 15" />
                        <x-input-error :messages="$errors->get('model')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="activo_brand" value="Marca" />
                        <x-text-input wire:model="brand" id="activo_brand" class="block mt-1 w-full" type="text" placeholder="Ej: Dell" />
                        <x-input-error :messages="$errors->get('brand')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="activo_description" value="Descripción" />
                    <textarea wire:model="description" id="activo_description" rows="2"
                              class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                              placeholder="Descripción opcional"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                @if(!$fromPurchase)
                <div>
                    <x-input-label for="activo_unit_cost" value="Costo unitario *" />
                    <x-text-input wire:model="unit_cost" id="activo_unit_cost" class="block mt-1 w-full" type="number" min="0" step="0.01" placeholder="0.00" />
                    <x-input-error :messages="$errors->get('unit_cost')" class="mt-1" />
                </div>
                @endif

                <div>
                    <x-input-label for="activo_location" value="Ubicación" />
                    <x-text-input wire:model="location" id="activo_location" class="block mt-1 w-full" type="text" placeholder="Ej: Escritorio, Almacén" />
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                @if(!$fromPurchase)
                <div>
                    <x-input-label for="activo_purchase_date" value="Fecha de compra" />
                    <x-text-input wire:model="purchase_date" id="activo_purchase_date" class="block mt-1 w-full" type="date" />
                    <x-input-error :messages="$errors->get('purchase_date')" class="mt-1" />
                </div>
                @endif

                <div>
                    <x-input-label for="activo_assigned_to" value="Custodia (quién lo tiene)" />
                    <select wire:model="assigned_to_user_id" id="activo_assigned_to"
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">— Sin asignar</option>
                        @foreach($workers ?? [] as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('assigned_to_user_id')" class="mt-1" />
                </div>

                @if(!$fromPurchase)
                <div>
                    <x-input-label for="activo_warranty_expiry" value="Fin de garantía" />
                    <x-text-input wire:model="warranty_expiry" id="activo_warranty_expiry" class="block mt-1 w-full" type="date" />
                    <x-input-error :messages="$errors->get('warranty_expiry')" class="mt-1" />
                </div>
                @endif

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
