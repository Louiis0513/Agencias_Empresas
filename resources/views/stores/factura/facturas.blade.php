<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Facturas - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition shrink-0">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO', 'rowId' => 'factura'])
    @livewire('select-batch-variant-modal', ['storeId' => $store->id])
    <livewire:create-invoice-modal :store-id="$store->id" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes de éxito/error --}}
            @if(session('success'))
                <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-4 sm:p-6">
                    @php
                        $fieldClass = 'w-full min-h-[42px] rounded-lg border border-white/15 bg-zinc-950/70 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-500 shadow-sm focus:border-brand/60 focus:outline-none focus:ring-2 focus:ring-brand/25 transition';
                        $labelClass = 'block text-xs font-medium text-gray-400 mb-1.5';
                    @endphp

                    {{-- Botón crear factura --}}
                    <div class="mb-6 flex flex-wrap justify-end gap-2">
                        <button type="button"
                                x-data=""
                                x-on:click="Livewire.dispatch('open-create-invoice-fresh')"
                                class="inline-flex items-center justify-center gap-2 min-h-[42px] px-4 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/40 transition">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear factura
                        </button>
                    </div>

                    {{-- Filtros y búsqueda --}}
                    <form method="GET" action="{{ route('stores.invoices', $store) }}" class="mb-6 space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-200 mb-3">Filtros</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <div class="min-w-0 sm:col-span-2 lg:col-span-2">
                                    <span class="{{ $labelClass }}">Rango de fechas</span>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2 min-w-0">
                                        <input type="date"
                                               name="fecha_desde"
                                               value="{{ request('fecha_desde', $rangoFechas['fecha_desde']->format('Y-m-d')) }}"
                                               class="{{ $fieldClass }} sm:flex-1 sm:min-w-0">
                                        <span class="hidden shrink-0 text-xs text-gray-500 sm:inline self-center">al</span>
                                        <input type="date"
                                               name="fecha_hasta"
                                               value="{{ request('fecha_hasta', $rangoFechas['fecha_hasta']->format('Y-m-d')) }}"
                                               class="{{ $fieldClass }} sm:flex-1 sm:min-w-0">
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <label for="filter_invoice_customer" class="{{ $labelClass }}">Cliente</label>
                                    <select id="filter_invoice_customer" name="customer_id" class="{{ $fieldClass }}">
                                        <option value="">Todos</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="min-w-0">
                                    <label for="filter_invoice_status" class="{{ $labelClass }}">Estado</label>
                                    <select id="filter_invoice_status" name="status" class="{{ $fieldClass }}">
                                        <option value="">Todos</option>
                                        <option value="PAID" {{ request('status') == 'PAID' ? 'selected' : '' }}>Pagada</option>
                                        <option value="PENDING" {{ request('status') == 'PENDING' ? 'selected' : '' }}>Pendiente</option>
                                        <option value="VOID" {{ request('status') == 'VOID' ? 'selected' : '' }}>Anulada</option>
                                    </select>
                                </div>

                                <div class="min-w-0">
                                    <label for="filter_invoice_payment" class="{{ $labelClass }}">Método de pago</label>
                                    <select id="filter_invoice_payment" name="payment_method" class="{{ $fieldClass }}">
                                        <option value="">Todos</option>
                                        <option value="SIN_METODO" {{ request('payment_method') == 'SIN_METODO' ? 'selected' : '' }}>Sin método de pago</option>
                                        <option value="CASH" {{ request('payment_method') == 'CASH' ? 'selected' : '' }}>Efectivo</option>
                                        <option value="TRANSFER" {{ request('payment_method') == 'TRANSFER' ? 'selected' : '' }}>Transferencia</option>
                                        <option value="MIXED" {{ request('payment_method') == 'MIXED' ? 'selected' : '' }}>Mixto</option>
                                    </select>
                                </div>

                                @if(isset($bolsillos) && $bolsillos->isNotEmpty())
                                <div class="min-w-0">
                                    <label for="filter_invoice_bolsillo" class="{{ $labelClass }}">Bolsillo</label>
                                    <select id="filter_invoice_bolsillo" name="bolsillo_id" class="{{ $fieldClass }}">
                                        <option value="">Todos</option>
                                        @foreach($bolsillos as $b)
                                            <option value="{{ $b->id }}" {{ request('bolsillo_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4 sm:p-5">
                            <h3 class="text-sm font-semibold text-gray-200 mb-3">Búsqueda</h3>
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                <div class="min-w-0 flex-1 sm:min-w-[200px]">
                                    <label for="filter_invoice_search" class="{{ $labelClass }}">ID o total</label>
                                    <input id="filter_invoice_search" type="text"
                                           name="search"
                                           value="{{ request('search') }}"
                                           placeholder="Ej. número de factura o monto…"
                                           class="{{ $fieldClass }}">
                                </div>
                                <div class="flex flex-col sm:flex-row gap-2 sm:items-end sm:pt-0 pt-1">
                                    <button type="submit"
                                            class="inline-flex items-center justify-center min-h-[42px] px-5 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/40 transition">
                                        Aplicar filtros
                                    </button>
                                    @if(request()->anyFilled(['search', 'customer_id', 'status', 'payment_method', 'bolsillo_id', 'fecha_desde', 'fecha_hasta']))
                                        <a href="{{ route('stores.invoices', $store) }}"
                                           class="inline-flex items-center justify-center min-h-[42px] px-4 rounded-lg border border-white/15 text-sm text-gray-300 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-white/20 transition">
                                            Limpiar
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </form>

                    {{-- Tabla de facturas --}}
                    @if($invoices->count() > 0)
                        <div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 touch-pan-x">
                            <table class="min-w-[640px] w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Método de Pago</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase w-[1%] whitespace-nowrap">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5" x-data="{ actionsOpenId: null }">
                                    @foreach($invoices as $invoice)
                                        <tr class="hover:bg-white/[0.02]">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">
                                                {{ $invoice->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">
                                                {{ $invoice->customer ? $invoice->customer->name : 'Cliente Genérico' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($invoice->status === 'PAID')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Pagada</span>
                                                @elseif($invoice->status === 'PENDING')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Anulada</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-100">
                                                {{ money($invoice->total, $store->currency ?? 'COP') }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">
                                                @if($invoice->payment_method === null)
                                                    <span class="text-gray-400">Sin método de pago asociado</span>
                                                @elseif($invoice->payment_method == 'CASH')
                                                    Efectivo
                                                @elseif($invoice->payment_method == 'CARD')
                                                    Tarjeta
                                                @elseif($invoice->payment_method == 'TRANSFER')
                                                    Transferencia
                                                @else
                                                    Mixto
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                                <button type="button"
                                                        class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm font-medium text-gray-200 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-brand/30 transition"
                                                        :aria-expanded="actionsOpenId === {{ $invoice->id }}"
                                                        @click="actionsOpenId = actionsOpenId === {{ $invoice->id }} ? null : {{ $invoice->id }}">
                                                    <span>Opciones</span>
                                                    <svg class="h-4 w-4 text-gray-400 transition-transform shrink-0" :class="actionsOpenId === {{ $invoice->id }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr x-show="actionsOpenId === {{ $invoice->id }}" class="bg-zinc-900/40" style="display: none;">
                                            <td colspan="6" class="px-4 py-3 border-t border-white/5">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
                                                    <a href="{{ route('stores.invoices.show', [$store, $invoice]) }}"
                                                       class="inline-flex justify-center rounded-lg border border-brand/35 bg-brand/15 px-3 py-2 text-sm font-medium text-brand hover:bg-brand/25 transition">
                                                        Ver detalle
                                                    </a>
                                                    <button type="button"
                                                            disabled
                                                            class="inline-flex justify-center rounded-lg border border-white/10 px-3 py-2 text-sm text-gray-500 cursor-not-allowed"
                                                            title="Próximamente">
                                                        Editar
                                                    </button>
                                                    <button type="button"
                                                            disabled
                                                            class="inline-flex justify-center rounded-lg border border-white/10 px-3 py-2 text-sm text-gray-500 cursor-not-allowed"
                                                            title="Próximamente">
                                                        Anular
                                                    </button>
                                                    @if($invoice->status === 'PAID')
                                                        <a href="{{ route('stores.invoices.printReceipt', [$store, $invoice]) }}"
                                                           target="_blank" rel="noopener"
                                                           class="inline-flex justify-center rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-sm font-medium text-gray-100 hover:bg-white/10 transition">
                                                            Imprimir tira
                                                        </a>
                                                    @else
                                                        <span class="inline-flex justify-center rounded-lg border border-white/10 px-3 py-2 text-sm text-gray-500 cursor-not-allowed" title="Solo facturas pagadas">Imprimir</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Paginación --}}
                        <div class="mt-4">
                            {{ $invoices->links() }}
                        </div>
                    @else
                        <p class="text-gray-400 text-center py-8">
                            @if(request()->anyFilled(['search', 'customer_id', 'status', 'payment_method', 'bolsillo_id', 'fecha_desde', 'fecha_hasta']))
                                No se encontraron facturas con los filtros aplicados.
                            @else
                                No hay facturas en los últimos 31 días.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
