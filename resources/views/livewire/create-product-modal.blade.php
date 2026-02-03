<div>
    @php
        $modalName = ($fromPurchase ?? false) ? 'create-product-from-compra' : 'create-product';
    @endphp
    <x-modal :name="$modalName" focusable maxWidth="2xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear producto') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Define el producto y su categoría (atributos). El ingreso a tienda se hace por Compras.') }}
            </p>

            <div class="mt-6 space-y-4">
                {{-- 1. Estrategia de inventario primero --}}
                <div>
                    <x-input-label for="type" value="{{ __('Estrategia de inventario') }}" />
                    <select wire:model="type"
                            id="type"
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($this->typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <strong>Serializado:</strong> cada unidad con número de serie. <strong>Por lotes:</strong> variantes (talla, etc.) por lote.
                    </p>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" placeholder="Ej: Suéter azul, Leche entera 1L" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="sku" value="{{ __('SKU') }}" />
                        <x-text-input wire:model="sku" id="sku" class="block mt-1 w-full" type="text" placeholder="Ej: LEC-001" />
                        <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="barcode" value="{{ __('Barcode') }}" />
                        <x-text-input wire:model="barcode" id="barcode" class="block mt-1 w-full" type="text" placeholder="Ej: 8412345678901" />
                        <x-input-error :messages="$errors->get('barcode')" class="mt-1" />
                    </div>
                </div>

                @if($this->categoriesWithAttributes->isNotEmpty())
                    <div>
                        <x-input-label for="category_id" value="{{ __('Categoría') }} (obligatoria)" />
                        <select wire:model.live="category_id"
                                id="category_id"
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('Selecciona una categoría') }}</option>
                            @foreach($this->categoriesWithAttributes as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->attributes->count() }} atributo(s))</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            La categoría define qué atributos tendrá el producto; los valores se asignan al dar entrada (seriales o lotes).
                        </p>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                    </div>
                @else
                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            No hay categorías con atributos. Crea categorías, asígnales atributos en <strong>Categorías → [categoría] → Atributos</strong>, y luego podrás crear productos.
                        </p>
                    </div>
                @endif

                <div>
                    <x-input-label for="location" value="{{ __('Ubicación') }}" />
                    <x-text-input wire:model="location" id="location" class="block mt-1 w-full" type="text" placeholder="Ej: Estantería A2" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        El precio se define al dar entrada (lote/serial) o en Editar producto.
                    </p>
                    <x-input-error :messages="$errors->get('location')" class="mt-1" />
                </div>

                <div class="flex items-center">
                    <input wire:model="is_active" id="is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <x-input-label for="is_active" value="{{ __('Activo') }}" class="ml-2" />
                    <x-input-error :messages="$errors->get('is_active')" class="ml-2" />
                </div>

            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                @if($this->categoriesWithAttributes->isNotEmpty())
                    <x-primary-button type="submit" wire:loading.attr="disabled">
                        {{ __('Crear producto') }}
                    </x-primary-button>
                @endif
            </div>
        </form>

        @php
            $productFormErrors = $errors->has('name') || $errors->has('category_id') || $errors->has('type');
        @endphp
        @if($errors->any() && (($fromPurchase ?? false) === false || $productFormErrors))
            <div x-init="$dispatch('open-modal', '{{ $modalName }}')"></div>
        @endif
    </x-modal>
</div>
