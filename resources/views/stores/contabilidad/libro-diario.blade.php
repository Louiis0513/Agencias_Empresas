<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Libro Diario — {{ $store->name }}</h2>
                <p class="mt-1 text-sm text-gray-400">Movimientos de comprobantes contabilizados, incluidos sus reversos.</p>
            </div>
            <a href="{{ route('stores.contabilidad.comprobantes', $store) }}" class="text-sm text-gray-400 hover:text-brand">Ver comprobantes →</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('stores.contabilidad.diario', $store) }}"
                  class="grid gap-3 rounded-xl border border-white/5 bg-dark-card p-4 md:grid-cols-5">
                <input name="search" value="{{ request('search') }}" placeholder="Comprobante, glosa o tercero"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100 md:col-span-2">
                <select name="cuenta_contable_id" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                    <option value="">Todas las cuentas</option>
                    @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" @selected((string) request('cuenta_contable_id') === (string) $cuenta->id)>
                            {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                        </option>
                    @endforeach
                </select>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100">
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100">
                <div class="flex gap-2 md:col-span-5">
                    <button class="rounded-lg bg-brand px-4 py-2 text-sm text-white">Filtrar</button>
                    <a href="{{ route('stores.contabilidad.diario', $store) }}"
                       class="rounded-lg bg-gray-700 px-4 py-2 text-sm text-gray-200">Limpiar</a>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/5">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-400">
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Comprobante</th>
                                <th class="px-4 py-3">Cuenta</th>
                                <th class="px-4 py-3">Tercero</th>
                                <th class="px-4 py-3">Detalle</th>
                                <th class="px-4 py-3 text-right">Débito</th>
                                <th class="px-4 py-3 text-right">Crédito</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($movimientos as $movimiento)
                                @php
                                    $fechaMov = $movimiento->fechaAsiento();
                                    $numeroMov = $movimiento->numeroAsiento();
                                    $urlShow = $movimiento->comprobante
                                        ? route('stores.contabilidad.comprobantes.show', [$store, $movimiento->comprobante])
                                        : ($movimiento->documentoInventario
                                            ? route('stores.products.documentos.show', [$store, $movimiento->documentoInventario])
                                            : null);
                                @endphp
                                <tr class="text-sm text-gray-300">
                                    <td class="whitespace-nowrap px-4 py-3">{{ $fechaMov?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($urlShow)
                                            <a href="{{ $urlShow }}"
                                               class="font-mono text-brand hover:underline">{{ $numeroMov }}</a>
                                        @else
                                            <span class="font-mono">{{ $numeroMov ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-mono">{{ $movimiento->cuentaContable->codigo }}</span>
                                        <div class="text-xs text-gray-500">{{ $movimiento->cuentaContable->nombre }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $movimiento->tercero?->nombre ?? $movimiento->comprobante?->tercero?->nombre ?? $movimiento->documentoInventario?->tercero_nombre ?? '—' }}</td>
                                    <td class="max-w-xs px-4 py-3">
                                        {{ $movimiento->glosaAsiento() }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                        {{ (float) $movimiento->debito > 0 ? '$ '.number_format((float) $movimiento->debito, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                        {{ (float) $movimiento->credito > 0 ? '$ '.number_format((float) $movimiento->credito, 2, ',', '.') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">No hay movimientos contabilizados para los filtros seleccionados.</td></tr>
                            @endforelse
                        </tbody>
                        @if($movimientos->count())
                            <tfoot class="border-t border-white/10 font-semibold text-gray-100">
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-right">Totales de esta página</td>
                                    <td class="px-4 py-4 text-right font-mono">${{ number_format((float) $movimientos->sum('debito'), 2, ',', '.') }}</td>
                                    <td class="px-4 py-4 text-right font-mono">${{ number_format((float) $movimientos->sum('credito'), 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                <div class="border-t border-white/5 p-4">{{ $movimientos->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
