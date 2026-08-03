<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3"
             x-data="{
                 exportModalOpen: false,
                 exportMes: {{ json_encode($movExportMesDefault ?? now()->format('Y-m')) }},
                 exportRoute: {{ json_encode(route('stores.cajas.movimientos.export-excel', $store)) }},
                 exportBaseParams: {{ json_encode(request()->except(['page', 'bolsillo_page'])) }},
                 exportHref() {
                     const p = new URLSearchParams();
                     Object.entries(this.exportBaseParams).forEach(([k, v]) => {
                         if (v === null || v === undefined || v === '') return;
                         if (Array.isArray(v)) {
                             v.forEach(item => p.append(k + '[]', item));
                         } else if (typeof v === 'boolean') {
                             p.append(k, v ? '1' : '0');
                         } else {
                             p.append(k, String(v));
                         }
                     });
                     p.set('export_mes', this.exportMes);
                     return this.exportRoute + '?' + p.toString();
                 }
             }">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ __('Movimientos') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                @if($sesionAbierta ?? null)
                    @storeCan($store, 'caja.sesiones.cerrar')
                        <a href="{{ route('stores.cajas.cerrar', $store) }}"
                           wire:navigate
                           class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-amber-600 text-white hover:bg-amber-700 transition shadow-sm">
                            {{ __('Cerrar caja') }}
                        </a>
                    @endstoreCan
                @else
                    @storeCan($store, 'caja.sesiones.abrir')
                        <a href="{{ route('stores.cajas.apertura', $store) }}"
                           wire:navigate
                           class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-green-600 text-white hover:bg-green-700 transition shadow-sm">
                            {{ __('Abrir caja') }}
                        </a>
                    @endstoreCan
                @endif
                @if(($tab ?? 'ingresos') === 'ingresos')
                    @storeCan($store, 'comprobantes-ingreso.create')
                        <a href="{{ route('stores.comprobantes-ingreso.create', $store) }}"
                           wire:navigate
                           class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-brand text-white shadow-[0_0_15px_rgba(34,114,255,0.25)] hover:shadow-[0_0_20px_rgba(34,114,255,0.35)] transition">
                            {{ __('Nuevo ingreso') }}
                        </a>
                    @endstoreCan
                @endif
                <button type="button"
                        @click="exportModalOpen = true"
                        class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold border border-white/20 bg-white/10 text-gray-100 hover:bg-white/15 hover:border-brand/40 transition">
                    {{ __('Descargar reporte') }}
                </button>

                <div x-show="exportModalOpen"
                     x-transition.opacity
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
                     style="display: none;"
                     @click.self="exportModalOpen = false"
                     @keydown.escape.window="exportModalOpen = false">
                    <div class="bg-dark-card border border-white/10 rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4"
                         @click.stop>
                        <h3 class="text-lg font-semibold text-white">{{ __('Exportar informe Excel') }}</h3>
                        <p class="text-sm text-gray-400">{{ __('Selecciona el mes calendario para ingresos, egresos y cuentas (vencimiento o alta en ese mes). Se mantienen los demás filtros de la página.') }}</p>
                        <div>
                            <label for="export_mes_input" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Mes') }}</label>
                            <input id="export_mes_input" type="month" x-model="exportMes"
                                   class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm px-3 py-2">
                        </div>
                        <div class="flex flex-wrap justify-end gap-2 pt-2">
                            <button type="button" @click="exportModalOpen = false"
                                    class="px-4 py-2 rounded-xl text-sm font-medium text-gray-400 hover:text-white border border-white/10">
                                {{ __('Cancelar') }}
                            </button>
                            <a :href="exportHref()" @click="exportModalOpen = false"
                               class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold bg-brand text-white hover:opacity-95 transition">
                                {{ __('Descargar') }}
                            </a>
                        </div>
                    </div>
                </div>
                <a href="{{ route('stores.dashboard', $store) }}" wire:navigate class="text-sm text-gray-400 hover:text-brand transition">
                    ← {{ __('Resumen') }}
                </a>
            </div>
        </div>
    </x-slot>

    @php($usaCrudCajaLocalMov = ! ($canAccessStoreConfig ?? false))
    @if($usaCrudCajaLocalMov)
        <livewire:create-bolsillo-modal :store-id="$store->id" />
        <livewire:edit-bolsillo-modal :store-id="$store->id" />
    @endif

    <div class="py-8" @if($usaCrudCajaLocalMov) x-data @endif>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('stores.partials.flujo-bolsillo-incompleto')
            {{-- Pestañas principales: Transacciones | Cierres --}}
            <div class="inline-flex rounded-xl border border-white/10 bg-white/5 p-1">
                <span class="px-5 py-2 rounded-lg text-sm font-semibold bg-brand text-white shadow-sm">
                    {{ __('Transacciones') }}
                </span>
                <a href="{{ route('stores.cajas.sesiones', $store) }}"
                   wire:navigate
                   class="px-5 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 transition">
                    {{ __('Cierres de caja') }}
                </a>
            </div>

            @include('stores.caja.partials.movimientos-filters')

            {{-- KPI: flujo neto según mismos filtros que tablas (ingresos − egresos en BD) --}}
            @php($cur = $store->currency ?? 'COP')
            <p class="text-xs text-gray-500 -mt-1 mb-2">{{ $movimientosResumenEtiqueta }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-xl border border-white/10 bg-dark-card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('Balance') }}</p>
                            <p class="text-[11px] text-gray-600 mt-0.5">{{ __('Ingresos − egresos') }}</p>
                            @php($bal = $movimientosResumen['balance'] ?? 0)
                            <p class="text-xl font-bold mt-1 {{ $bal >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ money($bal, $cur) }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-white/10 bg-dark-card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('Ventas totales') }}</p>
                            <p class="text-[11px] text-gray-600 mt-0.5">{{ __('Suma ingresos a bolsillos') }}</p>
                            <p class="text-xl font-bold text-emerald-400 mt-1">{{ money($movimientosResumen['ingresos'] ?? 0, $cur) }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-white/10 bg-dark-card p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500/15 text-red-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('Gastos totales') }}</p>
                            <p class="text-[11px] text-gray-600 mt-0.5">{{ __('Suma egresos desde bolsillos') }}</p>
                            <p class="text-xl font-bold text-red-400 mt-1">{{ money($movimientosResumen['egresos'] ?? 0, $cur) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sub-pestañas --}}
            <div class="flex flex-wrap gap-6 border-b border-white/10 pb-0">
                @php($t = $tab ?? 'ingresos')
                @if($t === 'ingresos')
                    <span class="pb-3 text-sm font-semibold text-white border-b-2 border-brand -mb-px">{{ __('Ingresos') }}</span>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['tab' => 'ingresos']) }}"
                       wire:navigate class="pb-3 text-sm font-medium text-gray-400 hover:text-white -mb-px">{{ __('Ingresos') }}</a>
                @endif
                @if($t === 'egresos')
                    <span class="pb-3 text-sm font-semibold text-white border-b-2 border-brand -mb-px">{{ __('Egresos') }}</span>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['tab' => 'egresos']) }}"
                       wire:navigate class="pb-3 text-sm font-medium text-gray-400 hover:text-white -mb-px">{{ __('Egresos') }}</a>
                @endif
                @if($t === 'por-cobrar')
                    <span class="pb-3 text-sm font-semibold text-white border-b-2 border-brand -mb-px">{{ __('Por cobrar') }}</span>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['tab' => 'por-cobrar']) }}"
                       wire:navigate class="pb-3 text-sm font-medium text-gray-400 hover:text-white -mb-px">{{ __('Por cobrar') }}</a>
                @endif
                @if($t === 'por-pagar')
                    <span class="pb-3 text-sm font-semibold text-white border-b-2 border-brand -mb-px">{{ __('Por pagar') }}</span>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['tab' => 'por-pagar']) }}"
                       wire:navigate class="pb-3 text-sm font-medium text-gray-400 hover:text-white -mb-px">{{ __('Por pagar') }}</a>
                @endif
            </div>

            <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                @if(($tab ?? 'ingresos') === 'ingresos' && isset($ingresosLineas))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="border-b border-white/5">
                                    <th class="w-12 px-4 py-3"></th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Concepto') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Valor') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Medio de pago') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Fecha y hora') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Estado') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($ingresosLineas as $linea)
                                    @php($ci = $linea->comprobanteIngreso)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="px-4 py-3 align-top">
                                            @if($ci)
                                                <a href="{{ route('stores.comprobantes-ingreso.show', [$store, $ci]) }}" wire:navigate
                                                   class="inline-flex text-brand hover:text-white" title="{{ __('Ver comprobante') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-100">
                                            <div class="font-medium">{{ $ci?->notes ?: '—' }}</div>
                                            @if($ci?->number)
                                                <div class="text-xs text-gray-500 mt-0.5">{{ __('Comprobante') }} {{ $ci->number }}</div>
                                            @endif
                                            @if($linea->reference)
                                                <div class="text-xs text-gray-400 mt-0.5">{{ $linea->reference }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-emerald-400">
                                            {{ money($linea->amount, $store->currency ?? 'COP') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-200">{{ $linea->bolsillo?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-300">
                                            {{ $ci?->created_at?->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">—</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-500 text-sm">
                                            {{ __('No hay ingresos registrados en este periodo.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($ingresosLineas->hasPages())
                        <div class="px-4 py-3 border-t border-white/5">{{ $ingresosLineas->links('pagination::tailwind') }}</div>
                    @endif
                @elseif(($tab ?? 'ingresos') === 'egresos' && isset($egresosLineas))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="border-b border-white/5">
                                    <th class="w-12 px-4 py-3"></th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Concepto') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Valor') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Medio de pago') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Fecha y hora') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Estado') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($egresosLineas as $linea)
                                    @php($ce = $linea->comprobanteEgreso)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="px-4 py-3 align-top">
                                            @if($ce)
                                                <a href="{{ route('stores.comprobantes-egreso.show', [$store, $ce]) }}" wire:navigate
                                                   class="inline-flex text-brand hover:text-white" title="{{ __('Ver comprobante') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-100">
                                            <div class="font-medium">{{ $ce?->notes ?: ($ce?->beneficiary_name ?: '—') }}</div>
                                            @if($ce?->number)
                                                <div class="text-xs text-gray-500 mt-0.5">{{ __('Comprobante') }} {{ $ce->number }}</div>
                                            @endif
                                            @if($linea->reference)
                                                <div class="text-xs text-gray-400 mt-0.5">{{ $linea->reference }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-red-400">
                                            {{ money($linea->amount, $store->currency ?? 'COP') }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-200">{{ $linea->bolsillo?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-300">
                                            {{ $ce?->created_at?->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">—</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-500 text-sm">
                                            {{ __('No hay egresos registrados en este periodo.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($egresosLineas->hasPages())
                        <div class="px-4 py-3 border-t border-white/5">{{ $egresosLineas->links('pagination::tailwind') }}</div>
                    @endif
                @elseif(($tab ?? 'ingresos') === 'por-cobrar' && isset($cuentasPorCobrar))
                    @isset($saldoPendienteCobrar)
                        <div class="px-4 pt-4 pb-2 border-b border-white/5">
                            <div class="rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3">
                                <p class="text-sm font-semibold text-emerald-200">
                                    {{ __('Saldo pendiente de cobro') }}:
                                    {{ money($saldoPendienteCobrar, $store->currency ?? 'COP', false) }}
                                </p>
                            </div>
                        </div>
                    @endisset
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="border-b border-white/5">
                                    <th class="w-12 px-4 py-3"></th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Concepto') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Valor') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Medio de pago') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Vencimiento') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Estado') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($cuentasPorCobrar as $ar)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="px-4 py-3 align-top">
                                            @if($ar->invoice)
                                                <a href="{{ route('stores.accounts-receivables.show', [$store, $ar]) }}" wire:navigate
                                                   class="inline-flex text-brand hover:text-white" title="{{ __('Ver cuenta por cobrar') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                </a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-100">
                                            <div class="font-medium">{{ $ar->customer?->name ?? '—' }}</div>
                                            @if($ar->invoice)
                                                <div class="text-xs text-gray-500 mt-0.5">{{ __('Factura') }} #{{ $ar->invoice->id }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-semibold text-amber-200 whitespace-nowrap">{{ money($ar->balance, $store->currency ?? 'COP', false) }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5 whitespace-nowrap">{{ __('Total') }} {{ money($ar->total_amount, $store->currency ?? 'COP', false) }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-400">{{ __('Crédito') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-300">
                                            {{ $ar->due_date?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            @if($ar->status === 'PENDIENTE')
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">{{ __('Pendiente') }}</span>
                                            @elseif($ar->status === 'PARCIAL')
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Parcial') }}</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Cobrado') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-500 text-sm">
                                            {{ __('No hay cuentas por cobrar.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($cuentasPorCobrar->hasPages())
                        <div class="px-4 py-3 border-t border-white/5">{{ $cuentasPorCobrar->links('pagination::tailwind') }}</div>
                    @endif
                @elseif(($tab ?? 'ingresos') === 'por-pagar' && isset($cuentasPorPagar))
                    @isset($deudaTotalPagar)
                        <div class="px-4 pt-4 pb-2 border-b border-white/5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-lg border border-amber-500/25 bg-amber-500/10 px-4 py-3">
                                <p class="text-sm font-semibold text-amber-200">
                                    {{ __('Deuda total pendiente') }}:
                                    {{ money($deudaTotalPagar, $store->currency ?? 'COP', false) }}
                                </p>
                                @storeCan($store, 'accounts-payables.create-manual')
                                    <a href="{{ route('stores.accounts-payables.create-manual', $store) }}" wire:navigate
                                       class="inline-flex shrink-0 items-center justify-center px-3 py-2 rounded-lg bg-brand text-white text-sm font-medium shadow-[0_0_12px_rgba(34,114,255,0.25)] hover:shadow-[0_0_18px_rgba(34,114,255,0.35)]">
                                        {{ __('Registrar CxP manual') }}
                                    </a>
                                @endstoreCan
                            </div>
                        </div>
                    @endisset
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="border-b border-white/5">
                                    <th class="w-12 px-4 py-3"></th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Concepto') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Valor') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Medio de pago') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Vencimiento') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Estado') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($cuentasPorPagar as $ap)
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="px-4 py-3 align-top">
                                            <a href="{{ route('stores.accounts-payables.show', [$store, $ap]) }}" wire:navigate
                                               class="inline-flex text-brand hover:text-white" title="{{ __('Ver CxP') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-100">
                                            <div class="font-medium">{{ $ap->purchase?->proveedor?->nombre ?? $ap->creditor_name ?? '—' }}</div>
                                            @if($ap->purchase)
                                                <div class="text-xs text-gray-500 mt-0.5">{{ __('Compra') }} #{{ $ap->purchase->id }}</div>
                                            @elseif($ap->isManual())
                                                <div class="text-xs text-gray-500 mt-0.5">{{ __('CxP manual') }}@if(filled($ap->document_reference)) · {{ $ap->document_reference }} @endif</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-semibold text-amber-200 whitespace-nowrap">{{ money($ap->balance, $store->currency ?? 'COP', false) }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5 whitespace-nowrap">{{ __('Total') }} {{ money($ap->total_amount, $store->currency ?? 'COP', false) }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-400">
                                            @if($ap->isManual())
                                                {{ __('Cuenta de cobro / manual') }}
                                            @else
                                                {{ __('Crédito proveedor') }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-300">
                                            {{ $ap->due_date?->format('d/m/Y') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            @if($ap->status === 'PENDIENTE')
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">{{ __('Pendiente') }}</span>
                                            @elseif($ap->status === 'PARCIAL')
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{{ __('Parcial') }}</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{{ __('Pagado') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-500 text-sm">
                                            {{ __('No hay CxP.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($cuentasPorPagar->hasPages())
                        <div class="px-4 py-3 border-t border-white/5">{{ $cuentasPorPagar->links('pagination::tailwind') }}</div>
                    @endif
                @else
                    <div class="px-4 py-16 text-center text-gray-500 text-sm">—</div>
                @endif
            </div>

            @include('stores.caja.partials.caja-bolsillos-panel')
        </div>
    </div>
</x-app-layout>
