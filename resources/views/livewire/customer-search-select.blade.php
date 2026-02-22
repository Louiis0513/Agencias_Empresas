<div>
    <x-input-label value="{{ __('Cliente') }} *" class="text-slate-300 dark:text-gray-400 font-semibold mb-2" />
    @if($clienteSeleccionado)
        <div class="p-4 bg-slate-800 dark:bg-gray-700/50 border-l-4 border-indigo-500 rounded-r-lg shadow-inner">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-bold text-indigo-400 dark:text-indigo-300 text-lg">{{ $clienteSeleccionado['name'] }}</p>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-slate-300 dark:text-gray-400">
                        @if(!empty($clienteSeleccionado['document_number']))
                            <span class="flex items-center">{{ $clienteSeleccionado['document_number'] }}</span>
                        @endif
                        @if(!empty($clienteSeleccionado['phone']))
                            <span class="flex items-center">{{ $clienteSeleccionado['phone'] }}</span>
                        @endif
                    </div>
                </div>
                <button type="button" wire:click="limpiarCliente" class="p-2 text-slate-400 hover:text-red-400 dark:hover:text-red-300 transition-colors" title="{{ __('Cambiar') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
    @else
        <button type="button" wire:click="abrirModal" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg transition-all shadow-lg uppercase text-xs tracking-widest">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            {{ __('Seleccionar cliente') }}
        </button>
    @endif

    @if($mostrarModal)
        <div class="fixed inset-0 overflow-y-auto z-[100]" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-900/80 dark:bg-gray-900/80 transition-opacity" wire:click="cerrarModal"></div>
                <div class="relative bg-slate-800 dark:bg-gray-800 rounded-2xl shadow-2xl border border-slate-600 dark:border-gray-700 max-w-2xl w-full max-h-[90vh] flex flex-col">
                    <div class="p-6 border-b border-slate-600 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-white dark:text-gray-100">{{ __('Buscar cliente') }}</h3>
                        <p class="text-sm text-slate-400 dark:text-gray-400 mt-1">{{ __('Indica al menos un criterio (nombre, documento o teléfono) y pulsa Buscar.') }}</p>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label for="customer-search-nombre" class="block text-xs font-bold text-slate-400 dark:text-gray-400 uppercase mb-1">{{ __('Nombre') }}</label>
                                <input type="text" id="customer-search-nombre" wire:model="filtroClienteNombre" placeholder="Ej: Juan Pérez"
                                    class="w-full rounded-lg border-slate-600 dark:border-gray-600 bg-slate-900 dark:bg-gray-900 text-white dark:text-gray-100 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="customer-search-documento" class="block text-xs font-bold text-slate-400 dark:text-gray-400 uppercase mb-1">{{ __('Documento') }}</label>
                                <input type="text" id="customer-search-documento" wire:model="filtroClienteDocumento" placeholder="Ej: 12345678"
                                    class="w-full rounded-lg border-slate-600 dark:border-gray-600 bg-slate-900 dark:bg-gray-900 text-white dark:text-gray-100 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="customer-search-telefono" class="block text-xs font-bold text-slate-400 dark:text-gray-400 uppercase mb-1">{{ __('Teléfono') }}</label>
                                <input type="text" id="customer-search-telefono" wire:model="filtroClienteTelefono" placeholder="Ej: 0991234567"
                                    class="w-full rounded-lg border-slate-600 dark:border-gray-600 bg-slate-900 dark:bg-gray-900 text-white dark:text-gray-100 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="button" wire:click="buscarClientes"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg text-sm transition-colors">
                                {{ __('Buscar') }}
                            </button>
                        </div>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1">
                        @if(count($clientesEncontrados) > 0)
                            <table class="min-w-full divide-y divide-slate-600 dark:divide-gray-700">
                                <thead class="bg-slate-900/50 dark:bg-gray-900/50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400 dark:text-gray-400">{{ __('Nombre') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400 dark:text-gray-400">{{ __('Documento') }}</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400 dark:text-gray-400">{{ __('Teléfono') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-400 dark:text-gray-400">{{ __('Acción') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-600 dark:divide-gray-700 bg-slate-900/30 dark:bg-gray-900/30">
                                    @foreach($clientesEncontrados as $cliente)
                                        <tr class="hover:bg-slate-700/50 dark:hover:bg-gray-700/50 transition-colors">
                                            <td class="px-4 py-3 text-sm text-white dark:text-gray-100 font-medium">{{ $cliente['name'] }}</td>
                                            <td class="px-4 py-3 text-sm text-slate-400 dark:text-gray-400">{{ $cliente['document_number'] ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-slate-400 dark:text-gray-400">{{ $cliente['phone'] ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-right">
                                                <button type="button" wire:click="seleccionarCliente({{ $cliente['id'] }})"
                                                    class="text-indigo-400 hover:text-indigo-300 dark:text-indigo-300 dark:hover:text-indigo-200 font-bold text-sm">
                                                    {{ __('Seleccionar') }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-sm text-slate-500 dark:text-gray-500 text-center py-8">
                                @if(trim($filtroClienteNombre) !== '' || trim($filtroClienteDocumento) !== '' || trim($filtroClienteTelefono) !== '')
                                    {{ __('No se encontraron clientes con los filtros indicados. Prueba con otros criterios.') }}
                                @else
                                    {{ __('Indica nombre, documento o teléfono y haz clic en «Buscar».') }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <div class="p-4 border-t border-slate-600 dark:border-gray-700 flex justify-end">
                        <button type="button" wire:click="cerrarModal"
                            class="px-5 py-2.5 border border-slate-600 dark:border-gray-600 rounded-lg text-slate-300 dark:text-gray-300 hover:bg-slate-700 dark:hover:bg-gray-700 font-bold text-sm transition-colors">
                            {{ __('Cerrar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
