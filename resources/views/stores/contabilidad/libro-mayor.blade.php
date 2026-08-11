@php
    $fmt = fn ($valor) => '$ '.number_format((float) $valor, 2, ',', '.');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Libro Mayor — {{ $store->name }}</h2>
                <p class="mt-1 text-sm text-gray-400">
                    Informe de consulta: movimientos por cuenta auxiliar con saldo inicial, corrido y final.
                    No sustituye la revisión del contador.
                </p>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('stores.contabilidad.diario', $store) }}" class="text-gray-400 hover:text-brand">Libro Diario</a>
                <a href="{{ route('stores.contabilidad.comprobantes', $store) }}" class="text-gray-400 hover:text-brand">Comprobantes →</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('stores.contabilidad.mayor', $store) }}"
                  class="grid gap-3 rounded-xl border border-white/5 bg-dark-card p-4 md:grid-cols-4">
                <select name="cuenta_contable_id" class="rounded-md border-white/10 bg-white/5 text-gray-100 md:col-span-2">
                    <option value="">Todas las cuentas con movimiento</option>
                    @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" @selected((string) request('cuenta_contable_id') === (string) $cuenta->id)>
                            {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                        </option>
                    @endforeach
                </select>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100"
                       title="Saldo inicial = movimientos anteriores a esta fecha">
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100">
                <div class="flex gap-2 md:col-span-4">
                    <button class="rounded-lg bg-brand px-4 py-2 text-sm text-white">Filtrar</button>
                    <a href="{{ route('stores.contabilidad.mayor', $store) }}"
                       class="rounded-lg bg-gray-700 px-4 py-2 text-sm text-gray-200">Limpiar</a>
                </div>
            </form>

            @forelse($cuentasMayor as $bloque)
                <section class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 px-4 py-3">
                        <div>
                            <h3 class="font-mono text-brand">{{ $bloque['cuenta']->codigo }}</h3>
                            <p class="text-sm text-gray-300">{{ $bloque['cuenta']->nombre }}</p>
                        </div>
                        <span class="rounded-full bg-white/5 px-2 py-1 text-xs uppercase text-gray-400">
                            Naturaleza {{ $bloque['naturaleza'] }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="text-left text-xs uppercase text-gray-400">
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Comprobante</th>
                                    <th class="px-4 py-3">Tercero</th>
                                    <th class="px-4 py-3">Detalle</th>
                                    <th class="px-4 py-3 text-right">Débito</th>
                                    <th class="px-4 py-3 text-right">Crédito</th>
                                    <th class="px-4 py-3 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr class="bg-white/[0.02] text-sm text-gray-400">
                                    <td colspan="4" class="px-4 py-3 font-medium">Saldo inicial</td>
                                    <td class="px-4 py-3 text-right font-mono">—</td>
                                    <td class="px-4 py-3 text-right font-mono">—</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-200">{{ $fmt($bloque['saldo_inicial']) }}</td>
                                </tr>
                                @forelse($bloque['movimientos'] as $fila)
                                    @php
                                        $movimiento = $fila['movimiento'];
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
                                        <td class="px-4 py-3">{{ $movimiento->tercero?->nombre ?? $movimiento->documentoInventario?->tercero_nombre ?? '—' }}</td>
                                        <td class="max-w-xs px-4 py-3">
                                            {{ $movimiento->detalle_contable ?: $movimiento->glosaAsiento() }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                            {{ (float) $movimiento->debito > 0 ? $fmt($movimiento->debito) : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                            {{ (float) $movimiento->credito > 0 ? $fmt($movimiento->credito) : '—' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-100">
                                            {{ $fmt($fila['saldo']) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">
                                            Sin movimientos en el periodo; se muestra por saldo inicial.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-t border-white/10 text-sm font-semibold text-gray-100">
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-right">Totales del periodo / saldo final</td>
                                    <td class="px-4 py-4 text-right font-mono">{{ $fmt($bloque['total_debito']) }}</td>
                                    <td class="px-4 py-4 text-right font-mono">{{ $fmt($bloque['total_credito']) }}</td>
                                    <td class="px-4 py-4 text-right font-mono text-brand">{{ $fmt($bloque['saldo_final']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-white/5 bg-dark-card px-4 py-12 text-center text-gray-400">
                    No hay cuentas con movimiento o saldo inicial para los filtros seleccionados.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
