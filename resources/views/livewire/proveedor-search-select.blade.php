<div>
    @if($proveedorSeleccionado)
        <div class="p-4 bg-slate-800 dark:bg-gray-700/50 border-l-4 border-indigo-500 rounded-r-lg shadow-inner">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-indigo-400 dark:text-indigo-300 text-lg truncate">{{ $proveedorSeleccionado['nombre'] }}</p>
                    @if(!empty($proveedorSeleccionado['nit']))
                        <p class="mt-1 text-sm text-slate-300 dark:text-gray-400">{{ __('NIT') }}: {{ $proveedorSeleccionado['nit'] }}</p>
                    @endif
                </div>
                <button type="button" wire:click="limpiarProveedor" class="shrink-0 p-2 text-slate-400 hover:text-red-400 dark:hover:text-red-300 transition-colors" title="{{ __('Quitar') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
    @else
        <button type="button" wire:click="abrirModal"
                class="w-full inline-flex items-center justify-center px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg transition-all shadow-lg uppercase text-xs tracking-widest">
            <svg class="w-5 h-5 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
            {{ __('Buscar proveedor') }}
        </button>
    @endif

    @if($mostrarModal)
        <div class="fixed inset-0 overflow-y-auto z-[100]" aria-modal="true">
            <div class="flex min-h-full items-start justify-center p-4 pt-6">
                <div class="relative bg-slate-800 dark:bg-gray-800 rounded-2xl shadow-2xl border border-slate-600 dark:border-gray-700 max-w-2xl w-full max-h-[90vh] flex flex-col">
                    <div class="p-6 border-b border-slate-600 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-white dark:text-gray-100">{{ __('Buscar proveedor') }}</h3>
                        <p class="text-sm text-slate-400 dark:text-gray-400 mt-1">
                            {{ __('Escribe nombre, NIT, teléfono o email. Se muestran como máximo 50 coincidencias.') }}
                        </p>
                        <div class="mt-4">
                            <label for="proveedor-search-input" class="block text-xs font-bold text-slate-400 dark:text-gray-400 uppercase mb-1">{{ __('Buscar') }}</label>
                            <input type="search"
                                   id="proveedor-search-input"
                                   wire:model.live.debounce.300ms="filtroBusqueda"
                                   autocomplete="off"
                                   placeholder="{{ __('Nombre, NIT, teléfono…') }}"
                                   class="w-full rounded-lg border-slate-600 dark:border-gray-600 bg-slate-900 dark:bg-gray-900 text-white dark:text-gray-100 text-sm focus:ring-indigo-500 focus:border-indigo-500 py-2.5 px-3">
                        </div>
                    </div>
                    <div class="p-4 overflow-y-auto flex-1 min-h-0">
                        @if(strlen(trim($filtroBusqueda)) >= 1)
                            @if($this->resultados->isEmpty())
                                <p class="text-sm text-slate-500 dark:text-gray-500 text-center py-8">
                                    {{ __('No se encontraron proveedores para ":q".', ['q' => trim($filtroBusqueda)]) }}
                                </p>
                            @else
                                <table class="min-w-full divide-y divide-slate-600 dark:divide-gray-700">
                                    <thead class="bg-slate-900/50 dark:bg-gray-900/50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400 dark:text-gray-400">{{ __('Nombre') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-400 dark:text-gray-400">{{ __('NIT') }}</th>
                                            <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-400 dark:text-gray-400">{{ __('Acción') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-600 dark:divide-gray-700 bg-slate-900/30 dark:bg-gray-900/30">
                                        @foreach($this->resultados as $prov)
                                            <tr class="hover:bg-slate-700/50 dark:hover:bg-gray-700/50 transition-colors">
                                                <td class="px-4 py-3 text-sm text-white dark:text-gray-100 font-medium">{{ $prov->nombre }}</td>
                                                <td class="px-4 py-3 text-sm text-slate-400 dark:text-gray-400">{{ $prov->nit ?: '—' }}</td>
                                                <td class="px-4 py-3 text-sm text-right">
                                                    <button type="button" wire:click="seleccionarProveedor({{ $prov->id }})"
                                                            class="text-indigo-400 hover:text-indigo-300 dark:text-indigo-300 dark:hover:text-indigo-200 font-bold text-sm">
                                                        {{ __('Seleccionar') }}
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        @else
                            <p class="text-sm text-slate-500 dark:text-gray-500 text-center py-8">
                                {{ __('Escribe al menos un carácter para buscar.') }}
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
