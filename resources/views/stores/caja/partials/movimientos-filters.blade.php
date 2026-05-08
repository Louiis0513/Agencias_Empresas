{{--
    Filtros unificados Movimientos: barra + drawer (bolsillos, empleados, cliente/proveedor Livewire).
    Variables: $store, $tab, $mf, $movimientosBolsillos, $movimientosEmpleados, $movCustomerLabel, $movProveedorNombre
--}}
@php
    $movimientosFiltrosAlpine = [
        'bolsilloIds' => $mf['bolsillo_ids'],
        'empleadoUserIds' => $mf['empleado_user_ids'],
        'empleados' => $movimientosEmpleados->values()->all(),
        'movCustomerLabel' => $movCustomerLabel ?? '',
        'movProveedorNombre' => $movProveedorNombre ?? '',
        'labels' => [
            'todosEmpleados' => __('Todos los empleados'),
            'seleccionados' => __('Seleccionados'),
            'todosProveedores' => __('Todos los proveedores'),
            'todosClientes' => __('Todos los clientes'),
        ],
        'clearUrl' => route('stores.cajas.movimientos', ['store' => $store, 'tab' => $tab]),
    ];
@endphp

<div
    class="space-y-4"
    x-data="movimientosFiltros(@js($movimientosFiltrosAlpine))"
    @movimientos-customer-chosen.window="handleCustomerChosen($event)"
    @movimientos-customer-clear.window="handleCustomerClear()"
    @movimientos-proveedor-chosen.window="handleProveedorChosen($event)"
    @movimientos-proveedor-clear.window="handleProveedorClear()"
>
    <form method="GET" action="{{ route('stores.cajas.movimientos', $store) }}" id="movimientos-filter-form" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <input type="hidden" name="mov_customer_id" id="mov_customer_id_hidden" value="{{ $mf['customer_id'] ?? '' }}">
        <input type="hidden" name="mov_proveedor_id" id="mov_proveedor_id_hidden" value="{{ $mf['proveedor_id'] ?? '' }}">

        <template x-for="bid in selectedBolsillos" :key="'bolsillo-' + bid">
            <input type="hidden" name="bolsillo_ids[]" :value="bid">
        </template>
        <template x-for="uid in selectedEmpleadoUserIds" :key="'emp-' + uid">
            <input type="hidden" name="empleado_user_ids[]" :value="uid">
        </template>

        <div class="min-w-[11rem] shrink-0">
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Día') }}</label>
            <input type="date" name="mov_fecha" value="{{ $mf['fecha_dia'] ?? '' }}"
                   title="{{ __('Opcional: filtra ingresos y egresos a ese día calendario.') }}"
                   class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Buscar concepto') }}</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="search" name="mov_search" value="{{ $mf['search'] }}"
                       placeholder="{{ __('Notas, referencias…') }}"
                       class="w-full pl-10 rounded-lg border border-white/10 bg-white/5 text-gray-100 placeholder:text-gray-500 text-sm">
            </div>
        </div>

        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-white/10 text-white text-sm font-semibold hover:bg-white/15">
            {{ __('Aplicar') }}
        </button>
        <button type="button"
                @click="drawerOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-brand text-white text-sm font-semibold hover:opacity-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            {{ __('Filtros') }}
        </button>
    </form>

    {{-- Overlay --}}
    <div x-show="drawerOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/60 lg:bg-black/40" style="display: none;"
         @keydown.escape.window="drawerOpen = false"></div>

    {{-- Drawer --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition transform duration-200 ease-out"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition transform duration-150 ease-in"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 z-50 w-full max-w-md flex flex-col bg-[#f8f9fb] dark:bg-gray-900 border-l border-gray-200 dark:border-white/10 shadow-2xl"
         style="display: none;"
         @click.outside="drawerOpen = false">
        <div class="flex items-center justify-between px-4 py-4 border-b border-gray-200 dark:border-white/10">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Filtros') }}</h3>
            <button type="button" @click="drawerOpen = false" class="rounded-full p-2 text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-white/10">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
            @if($tab === 'por-cobrar')
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('Estado cuenta') }}</label>
                    <select name="pc_status" form="movimientos-filter-form"
                            class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-gray-100 text-sm py-2.5 px-3">
                        <option value="">{{ __('Todos') }}</option>
                        <option value="PENDIENTE" {{ request('pc_status') == 'PENDIENTE' ? 'selected' : '' }}>{{ __('Pendientes') }}</option>
                        <option value="PARCIAL" {{ request('pc_status') == 'PARCIAL' ? 'selected' : '' }}>{{ __('Parcial') }}</option>
                        <option value="PAGADO" {{ request('pc_status') == 'PAGADO' ? 'selected' : '' }}>{{ __('Cobrados') }}</option>
                    </select>
                </div>
            @endif

            @if($tab === 'por-pagar')
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('Estado cuenta') }}</label>
                    <select name="pp_status" form="movimientos-filter-form"
                            class="w-full rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-gray-100 text-sm py-2.5 px-3">
                        <option value="">{{ __('Todos') }}</option>
                        <option value="pendientes" {{ request('pp_status') == 'pendientes' ? 'selected' : '' }}>{{ __('Pendientes') }}</option>
                        <option value="PAGADO" {{ request('pp_status') == 'PAGADO' ? 'selected' : '' }}>{{ __('Pagados') }}</option>
                    </select>
                </div>
            @endif

            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('Medios de pago') }} ({{ __('bolsillos') }})</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($movimientosBolsillos as $bol)
                        <button type="button"
                                @click.prevent="toggleBolsillo({{ $bol->id }})"
                                :class="bolsilloChipClass({{ $bol->id }})"
                                class="px-3 py-1.5 rounded-full text-sm font-medium border transition">
                            {{ $bol->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Empleados --}}
            <div>
                <button type="button" @click="panelEmpleados = true"
                        class="w-full flex items-center gap-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/10 transition">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-500">{{ __('Empleados') }}</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" x-text="empleadosSummary()"></p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            {{-- Cliente (mismo modal que facturas; un solo cliente) --}}
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 space-y-2">
                <p class="text-xs font-medium text-gray-500">{{ __('Cliente') }} · <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="clienteSummary()"></span></p>
                <livewire:customer-search-select
                    :store-id="$store->id"
                    :selected-customer-id="$mf['customer_id']"
                    emit-event-name="movimientos-customer-chosen"
                    emit-clear-event-name="movimientos-customer-clear"
                    :show-consumidor-final-button="false"
                    wire:key="movimientos-customer-{{ $store->id }}-{{ $tab }}-{{ $mf['customer_id'] ?? 'x' }}"
                />
            </div>

            {{-- Proveedor: búsqueda en servidor (máx. 50), mismo patrón que cliente --}}
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 space-y-2">
                <p class="text-xs font-medium text-gray-500">{{ __('Proveedor') }} · <span class="font-semibold text-gray-800 dark:text-gray-200" x-text="proveedorSummary()"></span></p>
                <livewire:proveedor-search-select
                    :store-id="$store->id"
                    :selected-proveedor-id="$mf['proveedor_id']"
                    emit-event-name="movimientos-proveedor-chosen"
                    emit-clear-event-name="movimientos-proveedor-clear"
                    wire:key="movimientos-proveedor-{{ $store->id }}-{{ $tab }}-{{ $mf['proveedor_id'] ?? 'x' }}"
                />
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 px-4 py-4 border-t border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900">
            <button type="button" @click="limpiarTodo()" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                {{ __('Limpiar filtros') }}
            </button>
            <button type="submit" form="movimientos-filter-form" @click="drawerOpen = false"
                    class="inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-gray-900 dark:bg-brand text-white text-sm font-semibold hover:opacity-95">
                {{ __('Filtrar') }}
            </button>
        </div>
    </div>

    {{-- Panel empleados --}}
    <div x-show="panelEmpleados" class="fixed inset-0 z-[60] flex justify-end" style="display: none;">
        <div class="absolute inset-0 bg-black/40" @click="panelEmpleados = false"></div>
        <div class="relative h-full w-full max-w-md bg-white dark:bg-gray-900 shadow-xl flex flex-col border-l border-gray-200 dark:border-white/10">
            <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-white/10">
                <button type="button" @click="panelEmpleados = false" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h4 class="flex-1 text-center font-semibold text-gray-900 dark:text-white">{{ __('Seleccionar empleados') }}</h4>
                <button type="button" @click="panelEmpleados = false" class="rounded-full p-2 bg-gray-900 text-white hover:bg-gray-800">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-4 py-3">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></span>
                    <input type="search" x-model="empleadoSearch" placeholder="{{ __('Buscar empleado…') }}"
                           class="w-full pl-10 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 text-gray-900 dark:text-gray-100 text-sm py-2.5">
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-2 pb-4 space-y-1">
                <template x-for="emp in filteredEmpleados()" :key="'w-' + emp.user_id">
                    <label class="flex items-start gap-3 px-3 py-3 rounded-xl hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer"
                           @click.prevent="toggleEmpleado(emp.user_id)">
                        <input type="checkbox" class="mt-1 rounded border-gray-300 text-brand focus:ring-brand pointer-events-none"
                               :checked="isEmpOn(emp.user_id)" tabindex="-1">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="emp.name"></p>
                            <p class="text-xs text-gray-500" x-text="emp.subtitle"></p>
                        </div>
                    </label>
                </template>
            </div>
            <div class="p-4 border-t border-gray-200 dark:border-white/10">
                <button type="button" @click="panelEmpleados = false"
                        class="w-full py-3 rounded-xl bg-gray-900 dark:bg-brand text-white font-semibold text-sm">{{ __('Confirmar') }}</button>
            </div>
        </div>
    </div>
</div>
