<div>
    <x-modal name="select-batch-variant" focusable maxWidth="2xl" :zIndex="100">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                Seleccionar Variante
            </h2>
            <p class="mt-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                Producto: <strong class="text-gray-900 dark:text-white">{{ $productName }}</strong>
            </p>
            <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">
                Selecciona una de las variantes existentes de este producto.
            </p>

            <div class="mt-4">
                @forelse($existingVariants as $index => $variant)
                    @php
                        $yaEnCarrito = in_array($variant['variant_key'] ?? '', $variantKeysInCart ?? [], true);
                    @endphp
                    <label class="flex items-center p-4 border-2 rounded-lg mb-2 transition-colors {{ $yaEnCarrito ? 'border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-800/50 cursor-not-allowed opacity-75' : 'border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:border-indigo-300 dark:hover:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/30 has-[:checked]:border-indigo-500 dark:has-[:checked]:border-indigo-400' }}">
                        <input type="radio"
                               wire:model="selectedVariantId"
                               value="{{ $index }}"
                               @disabled($yaEnCarrito)
                               class="mr-3 text-indigo-600 focus:ring-indigo-500 {{ $yaEnCarrito ? 'cursor-not-allowed' : '' }}">
                        <span class="text-base font-medium {{ $yaEnCarrito ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ $variant['display_name'] }}
                            <span class="text-sm font-normal text-gray-600 dark:text-gray-400"> — {{ $variant['quantity'] ?? 0 }} uds</span>
                            @if($yaEnCarrito)
                                <span class="ml-2 text-xs font-medium text-amber-600 dark:text-amber-400">(Ya en carrito)</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <div class="text-center py-8 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-600">
                        <p class="mb-2 font-medium text-gray-800 dark:text-gray-200">Este producto aún no tiene variantes creadas.</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">Las variantes se crean automáticamente cuando agregas stock inicial al crear el producto o cuando registras movimientos de entrada.</p>
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
