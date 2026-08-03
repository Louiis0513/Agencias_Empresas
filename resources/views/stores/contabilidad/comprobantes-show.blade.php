<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">
                    {{ $comprobante->numero ?? 'Borrador #'.$comprobante->id }}
                </h2>
                <p class="mt-1 text-sm text-gray-400">{{ $comprobante->tipoComprobante->nombre }} — {{ $store->name }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stores.contabilidad.comprobantes', $store) }}"
                   class="rounded-lg px-4 py-2 text-sm text-gray-300 hover:bg-white/5">← Listado</a>
                @if($comprobante->esBorrador())
                    @storeCan($store, 'contabilidad.comprobantes.edit')
                    <a href="{{ route('stores.contabilidad.comprobantes.edit', [$store, $comprobante]) }}"
                       class="rounded-lg bg-white/10 px-4 py-2 text-sm text-gray-100">Editar</a>
                    @endstoreCan
                    @storeCan($store, 'contabilidad.comprobantes.post')
                    <form method="POST" action="{{ route('stores.contabilidad.comprobantes.post', [$store, $comprobante]) }}"
                          onsubmit="return confirm('¿Contabilizar este asiento? Después no podrá editarse.');">
                        @csrf
                        <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Contabilizar</button>
                    </form>
                    @endstoreCan
                @elseif($comprobante->estaContabilizado() && !$comprobante->reversa_de_id)
                    @storeCan($store, 'contabilidad.comprobantes.reverse')
                    <form method="POST" action="{{ route('stores.contabilidad.comprobantes.reverse', [$store, $comprobante]) }}"
                          onsubmit="return confirm('¿Crear y contabilizar el asiento inverso?');">
                        @csrf
                        <button class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Reversar</button>
                    </form>
                    @endstoreCan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-950/30 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif

            @if($comprobante->reversaDe)
                <div class="rounded-xl border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-amber-200">
                    Este comprobante revierte a
                    <a class="font-semibold underline" href="{{ route('stores.contabilidad.comprobantes.show', [$store, $comprobante->reversaDe]) }}">
                        {{ $comprobante->reversaDe->numero }}
                    </a>.
                </div>
            @endif
            @if($comprobante->reverso)
                <div class="rounded-xl border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-amber-200">
                    Este comprobante fue reversado mediante
                    <a class="font-semibold underline" href="{{ route('stores.contabilidad.comprobantes.show', [$store, $comprobante->reverso]) }}">
                        {{ $comprobante->reverso->numero }}
                    </a>.
                </div>
            @endif

            <section class="rounded-xl border border-white/5 bg-dark-card p-6">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Estado</div>
                        <div class="mt-1 font-semibold text-gray-100">{{ $comprobante->estado }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Fecha</div>
                        <div class="mt-1 text-gray-100">{{ $comprobante->fecha->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Creado por</div>
                        <div class="mt-1 text-gray-100">{{ $comprobante->creador?->name ?? '—' }}</div>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <div class="text-xs uppercase text-gray-500">Observaciones</div>
                        <div class="mt-1 whitespace-pre-line text-gray-100">{{ $comprobante->descripcion }}</div>
                    </div>
                    @if($comprobante->contabilizado_at)
                        <div class="sm:col-span-2">
                            <div class="text-xs uppercase text-gray-500">Contabilizado</div>
                            <div class="mt-1 text-sm text-gray-300">
                                {{ $comprobante->contabilizado_at->format('d/m/Y H:i') }}
                                por {{ $comprobante->contabilizadoPor?->name ?? '—' }}
                            </div>
                        </div>
                    @endif
                    @if($comprobante->reversado_at)
                        <div class="sm:col-span-2">
                            <div class="text-xs uppercase text-gray-500">Reversado</div>
                            <div class="mt-1 text-sm text-gray-300">
                                {{ $comprobante->reversado_at->format('d/m/Y H:i') }}
                                por {{ $comprobante->reversadoPor?->name ?? '—' }}
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                <div class="border-b border-white/5 p-4">
                    <h3 class="font-semibold text-gray-100">Movimientos contables</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/5">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-400">
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Cuenta</th>
                                <th class="px-4 py-3">Tercero</th>
                                <th class="px-4 py-3">Detalle contable</th>
                                <th class="px-4 py-3">Descripción</th>
                                <th class="px-4 py-3">Centro de costo</th>
                                <th class="px-4 py-3 text-right">Débito</th>
                                <th class="px-4 py-3 text-right">Crédito</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($comprobante->movimientos as $linea)
                                <tr class="text-sm text-gray-300">
                                    <td class="px-4 py-3 text-gray-500">{{ $linea->orden }}</td>
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-brand">{{ $linea->cuentaContable->codigo }}</span>
                                        <div class="text-xs text-gray-400">{{ $linea->cuentaContable->nombre }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $linea->tercero?->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $linea->detalle_contable ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $linea->descripcion ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($linea->centroCosto)
                                            <span class="font-mono text-xs text-brand">{{ $linea->centroCosto->codigo }}</span>
                                            <div class="text-xs text-gray-400">
                                                @if($linea->centroCosto->padre)
                                                    {{ $linea->centroCosto->padre->nombre }} /
                                                @endif
                                                {{ $linea->centroCosto->nombre }}
                                            </div>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ (float) $linea->debito > 0 ? '$ '.number_format((float) $linea->debito, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        {{ (float) $linea->credito > 0 ? '$ '.number_format((float) $linea->credito, 2, ',', '.') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-white/10 font-semibold text-gray-100">
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-right">Totales</td>
                                <td class="px-4 py-4 text-right font-mono">${{ number_format((float) $comprobante->total_debito, 2, ',', '.') }}</td>
                                <td class="px-4 py-4 text-right font-mono">${{ number_format((float) $comprobante->total_credito, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
