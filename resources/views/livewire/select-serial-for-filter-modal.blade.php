<div>
    <x-modal name="select-serial-for-filter" focusable maxWidth="2xl" :zIndex="100">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                Seleccionar unidad — {{ $productName }}
            </h2>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                Elige el ítem para filtrar los movimientos.
            </p>

            <div class="mt-4">
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="Buscar por número de serie..."
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 focus:ring-brand focus:border-brand">
            </div>

            <div class="mt-4 overflow-auto max-h-80 border border-gray-200 dark:border-gray-600 rounded-md">
                @if(count($units) > 0)
                    <ul class="divide-y divide-gray-200 dark:divide-gray-600">
                        @foreach($units as $unit)
                            <li class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <div class="flex-1 text-sm text-gray-900 dark:text-gray-100">
                                    <span class="font-medium">{{ $unit['serial_number'] }}</span>
                                    @if(!empty($unit['features']) && is_array($unit['features']))
                                        <span class="text-gray-500 dark:text-gray-400 ml-2">— {{ implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($unit['features']), $unit['features'])) }}</span>
                                    @endif
                                </div>
                                <button type="button"
                                        wire:click="selectUnit({{ $unit['id'] }}, @js($unit['serial_number']))"
                                        class="px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    Seleccionar
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        @if(!empty(trim($search)))
                            No hay unidades con ese número de serie.
                        @else
                            No hay unidades para este producto.
                        @endif
                    </p>
                @endif
            </div>

            @if($totalUnits > 0)
                @php
                    $maxPage = (int) max(1, ceil($totalUnits / $perPage));
                    $from = ($page - 1) * $perPage + 1;
                    $to = min($page * $perPage, $totalUnits);
                @endphp
                <div class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Mostrando {{ $from }}-{{ $to }} de {{ $totalUnits }}</p>
                    <div class="flex gap-1">
                        <button type="button"
                                wire:click="goToPage({{ $page - 1 }})"
                                @if($page <= 1) disabled @endif
                                class="px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700">Anterior</button>
                        @for($p = max(1, $page - 2); $p <= min($maxPage, $page + 2); $p++)
                            <button type="button"
                                    wire:click="goToPage({{ $p }})"
                                    class="px-2 py-1 text-sm rounded {{ $p === $page ? 'bg-indigo-600 text-white' : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">{{ $p }}</button>
                        @endfor
                        <button type="button"
                                wire:click="goToPage({{ $page + 1 }})"
                                @if($page >= $maxPage) disabled @endif
                                class="px-2 py-1 text-sm rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100 dark:hover:bg-gray-700">Siguiente</button>
                    </div>
                </div>
            @endif

            <div class="mt-4 flex justify-end">
                <button type="button"
                        wire:click="close"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                    Cancelar
                </button>
            </div>
        </div>
    </x-modal>
</div>
