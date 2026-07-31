<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-white">Comprobantes contables — {{ $store->name }}</h2>
                <p class="mt-1 text-sm text-gray-400">Asientos manuales CC y sus reversiones.</p>
            </div>
            @storeCan($store, 'contabilidad.comprobantes.create')
            <a href="{{ route('stores.contabilidad.comprobantes.create', $store) }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white">
                Nuevo asiento
            </a>
            @endstoreCan
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

            <form method="GET" action="{{ route('stores.contabilidad.comprobantes', $store) }}"
                  class="grid gap-3 rounded-xl border border-white/5 bg-dark-card p-4 md:grid-cols-5">
                <input name="search" value="{{ request('search') }}" placeholder="Número, observaciones o tercero"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100 md:col-span-2">
                <select name="estado" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                    <option value="">Todos los estados</option>
                    @foreach($estados as $estado)
                        <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>
                    @endforeach
                </select>
                <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100">
                <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                       class="rounded-md border-white/10 bg-white/5 text-gray-100">
                <div class="flex gap-2 md:col-span-5">
                    <button class="rounded-lg bg-brand px-4 py-2 text-sm text-white">Filtrar</button>
                    <a href="{{ route('stores.contabilidad.comprobantes', $store) }}"
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
                                <th class="px-4 py-3">Observaciones</th>
                                <th class="px-4 py-3 text-right">Débito / Crédito</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($comprobantes as $comprobante)
                                @php
                                    $badge = match($comprobante->estado) {
                                        \App\Models\ComprobanteContable::ESTADO_CONTABILIZADO => 'bg-emerald-500/15 text-emerald-300',
                                        \App\Models\ComprobanteContable::ESTADO_REVERSADO => 'bg-amber-500/15 text-amber-300',
                                        default => 'bg-sky-500/15 text-sky-300',
                                    };
                                @endphp
                                <tr class="text-sm text-gray-300 hover:bg-white/[0.03]">
                                    <td class="whitespace-nowrap px-4 py-3">{{ $comprobante->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 font-mono text-brand">
                                        {{ $comprobante->numero ?? 'BORRADOR #'.$comprobante->id }}
                                        <div class="text-xs text-gray-500">{{ $comprobante->tipoComprobante->nombre }}</div>
                                    </td>
                                    <td class="max-w-xs truncate px-4 py-3" title="{{ $comprobante->descripcion }}">{{ $comprobante->descripcion }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono">
                                        ${{ number_format((float) $comprobante->total_debito, 2, ',', '.') }}
                                        <div class="text-xs text-gray-500">{{ $comprobante->movimientos_count }} líneas</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs {{ $badge }}">{{ $comprobante->estado }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('stores.contabilidad.comprobantes.show', [$store, $comprobante]) }}"
                                           class="text-brand hover:underline">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">No hay comprobantes contables.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-white/5 p-4">{{ $comprobantes->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
