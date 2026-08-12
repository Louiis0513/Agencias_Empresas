<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Contabilización {{ $documento->numero }}</h2>
                <p class="mt-1 text-sm text-gray-400">{{ $documento->fecha->format('d/m/Y') }} — {{ $store->name }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('stores.products.documentos.show', [$store, $documento]) }}"
                   class="rounded-lg px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                   wire:navigate>← Documento</a>
                @unless($documento->esConteoFisico())
                    <a href="{{ route('stores.products.documentos.contabilizacion.excel', [$store, $documento]) }}"
                       class="rounded-lg bg-emerald-700/80 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">
                        Descargar Excel
                    </a>
                @endunless
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if($documento->esConteoFisico())
                <section class="rounded-xl border border-amber-500/30 bg-amber-950/20 px-5 py-6">
                    <p class="text-sm font-medium text-amber-100">Sin contabilización económica</p>
                    <p class="mt-2 text-sm text-amber-200/80">
                        El valor económico será tratado por el módulo de valoración/costeo cuando sea implementado.
                    </p>
                    <p class="mt-3 text-xs text-amber-200/60">
                        Este conteo físico solo registró movimientos de cantidad en el ledger de inventario.
                    </p>
                </section>
            @else
                <section class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="text-left text-xs uppercase text-gray-400">
                                    <th class="px-4 py-3">Código contable</th>
                                    <th class="px-4 py-3">Cuenta contable</th>
                                    <th class="px-4 py-3">Nombre del tercero</th>
                                    <th class="px-4 py-3">Detalle</th>
                                    <th class="px-4 py-3">Descripción</th>
                                    <th class="px-4 py-3">Centro de costo</th>
                                    <th class="px-4 py-3 text-right">Débito</th>
                                    <th class="px-4 py-3 text-right">Crédito</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($documento->movimientosContables as $mov)
                                    <tr class="text-sm text-gray-300">
                                        <td class="px-4 py-3 font-mono">{{ $mov->cuentaContable?->codigo }}</td>
                                        <td class="px-4 py-3">{{ $mov->cuentaContable?->nombre }}</td>
                                        <td class="px-4 py-3">{{ $documento->tercero_nombre ?: '—' }}</td>
                                        <td class="px-4 py-3 max-w-xs">{{ $mov->detalle_contable ?: '—' }}</td>
                                        <td class="px-4 py-3 max-w-xs">{{ $mov->descripcion ?: '—' }}</td>
                                        <td class="px-4 py-3">{{ $mov->centroCosto?->nombre ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right font-mono">
                                            $ {{ number_format((float) $mov->debito, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono">
                                            $ {{ number_format((float) $mov->credito, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t border-white/10 font-semibold text-gray-100">
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-right">Total general</td>
                                    <td class="px-4 py-4 text-right font-mono">$ {{ number_format((float) $documento->total_debito, 2, ',', '.') }}</td>
                                    <td class="px-4 py-4 text-right font-mono">$ {{ number_format((float) $documento->total_credito, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
