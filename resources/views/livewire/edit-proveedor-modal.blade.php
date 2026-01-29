<div x-on:open-edit-proveedor-modal.window="$wire.loadProveedor($event.detail.id ?? $event.detail.proveedorId ?? $event.detail)">
    <x-modal name="edit-proveedor" focusable maxWidth="2xl">
        <form wire:submit="update" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Editar Proveedor') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Modifica los datos del proveedor.') }}
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="edit_nombre" value="{{ __('Nombre') }}" />
                    <x-text-input wire:model="nombre" id="edit_nombre" class="block mt-1 w-full" type="text" placeholder="Nombre del proveedor o empresa" required />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="edit_numero_celular" value="{{ __('Número Celular') }}" />
                        <x-text-input wire:model="numero_celular" id="edit_numero_celular" class="block mt-1 w-full" type="text" placeholder="+1234567890" />
                        <x-input-error :messages="$errors->get('numero_celular')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="edit_telefono" value="{{ __('Teléfono') }}" />
                        <x-text-input wire:model="telefono" id="edit_telefono" class="block mt-1 w-full" type="text" placeholder="Teléfono fijo" />
                        <x-input-error :messages="$errors->get('telefono')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="edit_email" value="{{ __('Email') }}" />
                        <x-text-input wire:model="email" id="edit_email" class="block mt-1 w-full" type="email" placeholder="correo@ejemplo.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="edit_nit" value="{{ __('NIT') }}" />
                        <x-text-input wire:model="nit" id="edit_nit" class="block mt-1 w-full" type="text" placeholder="Número de identificación tributaria" />
                        <x-input-error :messages="$errors->get('nit')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="edit_direccion" value="{{ __('Dirección') }}" />
                    <textarea wire:model="direccion" id="edit_direccion" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Dirección completa"></textarea>
                    <x-input-error :messages="$errors->get('direccion')" class="mt-1" />
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="estado" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Proveedor activo') }}</span>
                    </label>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-4">
                    <x-input-label value="{{ __('Productos que suministra este proveedor') }}" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 mb-3">
                        {{ __('Busca y agrega los productos que este proveedor puede suministrar.') }}
                    </p>

                    {{-- Buscador de productos --}}
                    <div class="flex gap-2 mb-3">
                        <input type="text"
                               wire:model="busquedaProducto"
                               class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="Buscar por nombre, SKU o código de barras">
                        <button type="button"
                                wire:click="buscarProductos"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Buscar
                        </button>
                    </div>

                    {{-- Resultados de búsqueda --}}
                    @if(count($productosEncontrados) > 0)
                        <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-md max-h-40 overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">SKU</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($productosEncontrados as $prod)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $prod['name'] }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $prod['sku'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-sm">
                                                <button type="button"
                                                        wire:click="agregarProducto({{ $prod['id'] }})"
                                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    Agregar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif(!empty($busquedaProducto))
                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">No se encontraron productos.</p>
                    @endif

                    {{-- Productos seleccionados --}}
                    @if($this->productosSeleccionados->isNotEmpty())
                        <div>
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">{{ __('Productos vinculados') }}:</p>
                            <div class="space-y-1 max-h-32 overflow-y-auto">
                                @foreach($this->productosSeleccionados as $prod)
                                    <div class="flex items-center justify-between py-1 px-2 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-600">
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $prod->name }}</span>
                                        <button type="button"
                                                wire:click="quitarProducto({{ $prod->id }})"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs">
                                            Quitar
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'edit-proveedor')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('Actualizar Proveedor') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
