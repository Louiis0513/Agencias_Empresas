<div x-on:open-edit-product-modal.window="$wire.loadProduct($event.detail.id || $event.detail)">
    <x-modal name="edit-product" focusable maxWidth="2xl">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Editar producto') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Modifica los datos del producto. Al cambiar la categoría, se actualizarán los campos de atributos disponibles.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="edit_name" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="name" id="edit_name" class="block mt-1 w-full" type="text" placeholder="Ej: Suéter azul, Leche entera 1L" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="edit_sku" value="{{ __('SKU') }}" />
                        <x-text-input wire:model="sku" id="edit_sku" class="block mt-1 w-full" type="text" placeholder="Ej: LEC-001" />
                        <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="edit_barcode" value="{{ __('Barcode') }}" />
                        <x-text-input wire:model="barcode" id="edit_barcode" class="block mt-1 w-full" type="text" placeholder="Ej: 8412345678901" />
                        <x-input-error :messages="$errors->get('barcode')" class="mt-1" />
                    </div>
                </div>

                @if($this->categoriesWithAttributes->isNotEmpty())
                    <div>
                        <x-input-label for="edit_category_id" value="{{ __('Categoría') }} (obligatoria)" />
                        <select wire:model.live="category_id"
                                id="edit_category_id"
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('Selecciona una categoría') }}</option>
                            @foreach($this->categoriesWithAttributes as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->attributes->count() }} atributo(s))</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Solo se muestran categorías con atributos asignados. Ve a Categorías → Atributos para configurarlas.
                        </p>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                    </div>
                @else
                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            No hay categorías con atributos. Crea categorías, asígnales atributos en <strong>Categorías → [categoría] → Atributos</strong>, y luego podrás editar productos.
                        </p>
                    </div>
                @endif

                @if($this->selectedCategory)
                    @php
                        $grouped = [];
                        foreach ($this->selectedCategory->attributes as $attr) {
                            $g = $attr->groups->first();
                            $gn = $g ? $g->name : 'Otros';
                            if (!isset($grouped[$gn])) {
                                $grouped[$gn] = [];
                            }
                            $grouped[$gn][] = $attr;
                        }
                    @endphp
                    <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 p-4">
                        <h3 class="text-sm font-medium text-indigo-900 dark:text-indigo-100 mb-3">
                            {{ __('Atributos de la categoría') }} «{{ $this->selectedCategory->name }}»
                        </h3>
                        <div class="space-y-4">
                            @foreach($grouped as $groupName => $attrs)
                                <div>
                                    <h4 class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase mb-2">{{ $groupName }}</h4>
                                    <div class="space-y-3">
                            @foreach($attrs as $attr)
                                @php
                                    $key = 'attribute_values.' . $attr->id;
                                    $required = (bool) ($attr->pivot->is_required ?? $attr->is_required);
                                @endphp
                                <div>
                                    <x-input-label :for="'edit_attr_' . $attr->id"
                                                  :value="$attr->name . ($required ? ' *' : '')" />
                                    @if($attr->type === 'text')
                                        <x-text-input wire:model="{{ $key }}"
                                                      :id="'edit_attr_' . $attr->id"
                                                      class="block mt-1 w-full"
                                                      type="text"
                                                      :placeholder="'Ej: ' . $attr->name" />
                                    @elseif($attr->type === 'number')
                                        <x-text-input wire:model="{{ $key }}"
                                                      :id="'edit_attr_' . $attr->id"
                                                      class="block mt-1 w-full"
                                                      type="number"
                                                      step="any" />
                                    @elseif($attr->type === 'select')
                                        <select wire:model="{{ $key }}"
                                                :id="'edit_attr_' . $attr->id"
                                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="">{{ $required ? 'Selecciona...' : '— Opcional —' }}</option>
                                            @foreach($attr->options as $opt)
                                                <option value="{{ $opt->value }}">{{ $opt->value }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($attr->type === 'boolean')
                                        <label class="inline-flex items-center mt-1">
                                            <input type="checkbox"
                                                   wire:model.live="{{ $key }}"
                                                   :id="'edit_attr_' . $attr->id"
                                                   value="1"
                                                   class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Sí</span>
                                        </label>
                                    @else
                                        <x-text-input wire:model="{{ $key }}"
                                                      :id="'edit_attr_' . $attr->id"
                                                      class="block mt-1 w-full"
                                                      type="text" />
                                    @endif
                                    <x-input-error :messages="$errors->get($key)" class="mt-1" />
                                </div>
                            @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="edit_price" value="{{ __('Precio (€)') }}" />
                        <x-text-input wire:model="price" id="edit_price" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="edit_cost" value="{{ __('Costo (€)') }}" />
                        <x-text-input wire:model="cost" id="edit_cost" class="block mt-1 w-full" type="number" step="0.01" min="0" placeholder="0.00" />
                        <x-input-error :messages="$errors->get('cost')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="edit_stock" value="{{ __('Stock') }}" />
                        <x-text-input wire:model="stock" id="edit_stock" class="block mt-1 w-full" type="number" min="0" placeholder="0" />
                        <x-input-error :messages="$errors->get('stock')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="edit_location" value="{{ __('Ubicación') }}" />
                        <x-text-input wire:model="location" id="edit_location" class="block mt-1 w-full" type="text" placeholder="Ej: Estantería A2" />
                        <x-input-error :messages="$errors->get('location')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="edit_type" value="{{ __('Estrategia de inventario') }}" />
                    <select wire:model="type"
                            id="edit_type"
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach($this->typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>

                <div class="flex items-center">
                    <input wire:model="is_active" id="edit_is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <x-input-label for="edit_is_active" value="{{ __('Activo') }}" class="ml-2" />
                    <x-input-error :messages="$errors->get('is_active')" class="ml-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                @if($this->categoriesWithAttributes->isNotEmpty())
                    <x-primary-button type="submit" wire:loading.attr="disabled">
                        {{ __('Actualizar producto') }}
                    </x-primary-button>
                @endif
            </div>
        </form>

        @if($errors->any())
            <div x-init="$dispatch('open-modal', 'edit-product')"></div>
        @endif
    </x-modal>
</div>
