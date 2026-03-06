<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Cotizaciones - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.ventas.carrito', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Ir al Carrito
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded relative dark:bg-green-900/30 dark:border-green-700 dark:text-green-300" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    @if($cotizaciones->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Usuario</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nota</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Vence en</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Ítems</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Valor cotización</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Valor actual</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase">Alerta</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($cotizaciones as $cot)
                                        <tr class="hover:bg-white/5 transition/50"
                                            x-data="{
                                                venceAt: '{{ $cot->vence_at ? $cot->vence_at->format('Y-m-d') : '' }}',
                                                get vencida() {
                                                    if (!this.venceAt) return false;
                                                    const [y, m, d] = this.venceAt.split('-').map(Number);
                                                    const vence = new Date(y, m - 1, d);
                                                    const hoy = new Date();
                                                    hoy.setHours(0, 0, 0, 0);
                                                    vence.setHours(0, 0, 0, 0);
                                                    return vence < hoy;
                                                },
                                                get textoVence() {
                                                    if (!this.venceAt) return null;
                                                    const [y, m, d] = this.venceAt.split('-').map(Number);
                                                    const vence = new Date(y, m - 1, d);
                                                    const hoy = new Date();
                                                    hoy.setHours(0, 0, 0, 0);
                                                    vence.setHours(0, 0, 0, 0);
                                                    const dias = Math.round((vence - hoy) / (24 * 60 * 60 * 1000));
                                                    if (dias < 0) return '(vencida)';
                                                    if (dias === 0) return '(vence hoy)';
                                                    if (dias === 1) return '(vence mañana)';
                                                    return '(vence en ' + dias + ' días)';
                                                }
                                            }"
                                            :class="{ 'bg-red-50 dark:bg-red-900/20': venceAt && vencida }">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-100">{{ $cot->id }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"
                                                x-data="{ d: new Date('{{ $cot->created_at->utc()->toIso8601String() }}') }"
                                                x-text="d.toLocaleString('es', { dateStyle: 'short', timeStyle: 'short' })">
                                                {{ $cot->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $cot->user->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $cot->customer?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate" title="{{ $cot->nota }}">{{ $cot->nota }}</td>
                                            <td class="px-4 py-3 text-sm"
                                                :class="venceAt && vencida ? 'text-red-700 dark:text-red-300 font-medium' : 'text-gray-700 dark:text-gray-300'">
                                                @if($cot->vence_at)
                                                    {{ $cot->vence_at->format('d/m/Y') }}
                                                    <span class="block text-xs" x-show="textoVence" x-text="textoVence"></span>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $cot->items->count() }}</td>
                                            @php
                                                $totales = $totalesPorCotizacion[$cot->id] ?? ['total_cotizado' => 0, 'total_actual' => 0];
                                                $diff = $totales['total_actual'] - $totales['total_cotizado'];
                                                $hayCambio = abs($diff) > 0.005;
                                            @endphp
                                            <td class="px-4 py-3 text-sm text-gray-100 text-right font-medium">{{ money($totales['total_cotizado'], $store->currency ?? 'COP', false) }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-100 text-right font-medium">{{ money($totales['total_actual'], $store->currency ?? 'COP', false) }}</td>
                                            <td class="px-4 py-3 text-center">
                                                @if($hayCambio)
                                                    <span class="text-xs font-medium {{ $diff > 0 ? 'text-amber-800 dark:text-amber-200' : 'text-emerald-800 dark:text-emerald-200' }}"
                                                        title="{{ $diff > 0 ? 'El valor actual es mayor que el cotizado' : 'El valor actual es menor que el cotizado' }}">
                                                        {{ $diff > 0 ? '+' : '' }}{{ money($diff, $store->currency ?? 'COP', false) }} {{ $diff > 0 ? '(aumentó)' : '(disminuyó)' }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <a href="{{ route('stores.ventas.cotizaciones.show', [$store, $cot]) }}"
                                                   wire:navigate
                                                   class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700">
                                                    Ver
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $cotizaciones->links() }}
                        </div>
                    @else
                        <p class="text-sm text-gray-400">No hay cotizaciones. Agrega productos al carrito y guárdalos como cotización.</p>
                        <a href="{{ route('stores.ventas.carrito', $store) }}" class="inline-flex mt-4 items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] text-sm font-medium">
                            Ir al Carrito
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
