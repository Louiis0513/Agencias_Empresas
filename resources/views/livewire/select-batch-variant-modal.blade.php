<div>
    <x-modal name="select-batch-variant" focusable maxWidth="2xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Seleccionar Variante
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Producto: <strong>{{ $productName }}</strong>
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Selecciona una de las variantes existentes de este producto.
            </p>

            <div class="mt-4">
                @forelse($existingVariants as $index => $variant)
                    @php
                        $variantKey = \App\Services\InventarioService::normalizeFeaturesForComparison($variant['features']);
                    @endphp
                    <label class="flex items-center p-3 border border-gray-200 dark:border-gray-700 rounded-lg mb-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <input type="radio" 
                               wire:model="selectedVariantId" 
                               value="{{ $variantKey }}"
                               class="mr-3 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $variant['display_name'] }}
                        </span>
                    </label>
                @empty
                    <div class="text-center py-8 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/30 rounded-lg border border-gray-200 dark:border-gray-700">
                        <p class="mb-2">Este producto aún no tiene variantes creadas.</p>
                        <p class="text-xs">Las variantes se crean automáticamente cuando agregas stock inicial al crear el producto o cuando registras movimientos de entrada.</p>
                    </div>
                @endforelse
                @error('selectedVariantId')
                    <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button"
                        x-on:click="$dispatch('close-modal', 'select-batch-variant')"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                    Cancelar
                </button>
                <button type="button"
                        wire:click="selectVariant"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Seleccionar Variante
                </button>
            </div>
        </div>
    </x-modal>
</div>
