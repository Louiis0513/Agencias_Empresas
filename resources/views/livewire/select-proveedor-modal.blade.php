<div>
    <x-modal name="select-proveedor" focusable maxWidth="2xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Seleccionar proveedor
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Busca por nombre, NIT, email o teléfono. Selecciona el proveedor al que vas a pagar.
            </p>

            <div class="mt-4">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Buscar proveedor (mín. 1 letra)..."
                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="mt-4 overflow-auto max-h-80 border border-gray-200 dark:border-gray-600 rounded-md">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->results as $prov)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $prov->nombre }}</td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button"
                                            wire:click="selectProveedor({{ $prov->id }}, @js($prov->nombre))"
                                            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                        Seleccionar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if(strlen(trim($search)) >= 1)
                                        No se encontraron proveedores para "{{ $search }}".
                                    @else
                                        Escribe al menos 1 letra para buscar.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button"
                        x-on:click="$dispatch('close-modal', 'select-proveedor')"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                    Cancelar
                </button>
            </div>
        </div>
    </x-modal>
</div>
