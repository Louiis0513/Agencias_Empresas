<div x-on:open-modal.window="if ($event.detail === 'create-invoice') { $wire.resetFormulario(); }">
    <x-modal name="create-invoice" focusable maxWidth="4xl">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear Factura') }}
            </h2>

            <div class="mt-6 space-y-6">
                {{-- Cliente (Obligatorio) --}}
                <div>
                    <x-input-label for="customer_id" value="{{ __('Cliente') }} *" />
                    <select wire:model="customer_id" 
                            id="customer_id" 
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                            required>
                        <option value="">Selecciona un cliente</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
                </div>

                {{-- Búsqueda de Productos --}}
                <div>
                    <x-input-label for="busquedaProducto" value="{{ __('Buscar Producto') }}" />
                    <div class="mt-1 flex gap-2">
                        <input type="text" 
                               wire:model="busquedaProducto" 
                               id="busquedaProducto" 
                               class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                               placeholder="ID, nombre o código de barras">
                        <button type="button" 
                                wire:click="buscarProductos" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Buscar
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('busquedaProducto')" class="mt-1" />

                    {{-- Resultados de búsqueda --}}
                    @if(count($productosEncontrados) > 0)
                        <div class="mt-2 border border-gray-200 dark:border-gray-700 rounded-md max-h-48 overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Precio</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Stock</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($productosEncontrados as $producto)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $producto['name'] }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">${{ number_format($producto['price'], 2) }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $producto['stock'] ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 text-sm">
                                                <button type="button" 
                                                        wire:click="agregarProducto({{ $producto['id'] }})"
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
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No se encontraron productos.</p>
                    @endif
                </div>

                {{-- Productos Seleccionados --}}
                @if(count($productosSeleccionados) > 0)
                    <div>
                        <x-input-label value="{{ __('Productos en la Factura') }}" />
                        <div class="mt-2 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Precio Unit.</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cantidad</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Subtotal</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($productosSeleccionados as $index => $producto)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $producto['name'] }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">${{ number_format($producto['price'], 2) }}</td>
                                            <td class="px-3 py-2 text-sm">
                                                <input type="number" 
                                                       wire:change="actualizarCantidad({{ $index }}, $event.target.value)"
                                                       value="{{ $producto['quantity'] }}" 
                                                       min="1" 
                                                       class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                            </td>
                                            <td class="px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                ${{ number_format($producto['subtotal'], 2) }}
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <button type="button" 
                                                        wire:click="eliminarProducto({{ $index }})"
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-input-error :messages="$errors->get('productosSeleccionados')" class="mt-1" />
                    </div>
                @endif

                {{-- Descuentos --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="discountType" value="{{ __('Tipo de Descuento') }}" />
                        <select wire:model.live="discountType" 
                                id="discountType" 
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="amount">Monto Fijo</option>
                            <option value="percent">Porcentaje</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="discountValue" value="{{ __('Valor del Descuento') }}" />
                        <x-text-input wire:model.live="discountValue" 
                                      id="discountValue" 
                                      type="number" 
                                      step="0.01" 
                                      min="0" 
                                      class="block mt-1 w-full" 
                                      placeholder="{{ $discountType === 'percent' ? 'Ej: 10' : 'Ej: 50.00' }}">
                        </x-text-input>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($discountType === 'percent')
                                Porcentaje (ej: 10 = 10%)
                            @else
                                Monto fijo (ej: 50.00)
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Totales --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="flex justify-end">
                        <div class="w-64 space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            @if($discount > 0)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Descuento:</span>
                                    <span class="font-semibold text-red-600 dark:text-red-400">-${{ number_format($discount, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2">
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total:</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-gray-100">${{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('total')" class="mt-1" />
                </div>

                {{-- Estado y Método de Pago --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="status" value="{{ __('Estado') }}" />
                        <select wire:model="status" 
                                id="status" 
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="PAID">Pagada</option>
                            <option value="PENDING">Pendiente</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="payment_method" value="{{ __('Método de Pago') }}" />
                        <select wire:model="payment_method" 
                                id="payment_method" 
                                class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="CASH">Efectivo</option>
                            <option value="CARD">Tarjeta</option>
                            <option value="TRANSFER">Transferencia</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'create-invoice')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    {{ __('Crear Factura') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
