<div>
    <x-modal name="select-item-compra" focusable maxWidth="4xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Seleccionar {{ $itemType === 'INVENTARIO' ? 'Producto (Inventario)' : 'Activo Fijo' }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Busca y selecciona un {{ $itemType === 'INVENTARIO' ? 'producto' : 'activo' }} de la tabla{{ $itemType === 'ACTIVO_FIJO' ? ', o crea uno nuevo si aún no existe' : '' }}.
            </p>

            <div class="mt-4 flex gap-3">
                <div class="flex-1">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Buscar por nombre, SKU o código (mín. 2 letras)..."
                           class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                @if($itemType === 'ACTIVO_FIJO')
                <div>
                    <button type="button"
                            wire:click="openCreateActivo"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                        Activo nuevo – Crear
                    </button>
                </div>
                @endif
            </div>

            <div class="mt-4 overflow-auto max-h-80 border border-gray-200 dark:border-gray-600 rounded-md">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $itemType === 'INVENTARIO' ? 'SKU' : 'Código' }}</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($results as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $item['name'] }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ $item['code'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button"
                                            wire:click="selectItem({{ $item['id'] }}, @js($item['name']), '{{ $item['type'] }}', @js($item['control_type'] ?? null), @js($item['product_type'] ?? null))"
                                            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                        Seleccionar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if(strlen(trim($search)) >= 2)
                                        No se encontraron resultados para "{{ $search }}".{{ $itemType === 'ACTIVO_FIJO' ? ' Prueba con otro término o crea uno nuevo.' : ' Prueba con otro término o crea el producto desde la vista de Productos.' }}
                                    @else
                                        Escribe al menos 2 letras para buscar{{ $itemType === 'ACTIVO_FIJO' ? ', o crea un activo nuevo' : '' }}.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button"
                        x-on:click="$dispatch('close-modal', 'select-item-compra')"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                    Cancelar
                </button>
            </div>
        </div>
    </x-modal>
</div>
