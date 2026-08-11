<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $documento->numero }}</h2>
                <p class="mt-1 text-sm text-gray-400">
                    {{ $documento->tituloTipoDocumento() }} — {{ $store->name }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('stores.products', $store) }}?tab=documentos"
                   class="rounded-lg px-4 py-2 text-sm text-gray-300 hover:bg-white/5"
                   wire:navigate>← Documentos</a>
                <a href="{{ route('stores.products.documentos.pdf', [$store, $documento]) }}"
                   target="_blank"
                   class="rounded-lg bg-white/10 px-4 py-2 text-sm text-gray-100">PDF / Imprimir</a>
                <a href="{{ route('stores.products.documentos.contabilizacion', [$store, $documento]) }}"
                   class="rounded-lg bg-emerald-700/80 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600"
                   wire:navigate>Ver contabilización</a>
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                            class="inline-flex items-center gap-1 rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-200 hover:bg-white/5">
                        Más
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 z-20 mt-1 w-56 rounded-lg border border-white/10 bg-dark-card py-1 shadow-xl">
                        <button type="button" disabled class="block w-full cursor-not-allowed px-4 py-2 text-left text-sm text-gray-500">Editar</button>
                        <button type="button" disabled class="block w-full cursor-not-allowed px-4 py-2 text-left text-sm text-gray-500">Anular</button>
                        <button type="button" disabled class="block w-full cursor-not-allowed px-4 py-2 text-left text-sm text-gray-500">Borrar</button>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-950/30 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif

            <section class="rounded-xl border border-white/5 bg-dark-card p-6">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Estado</div>
                        <div class="mt-1 font-semibold text-gray-100">{{ $documento->estado }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Fecha</div>
                        <div class="mt-1 text-gray-100">{{ $documento->fecha->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Tercero</div>
                        <div class="mt-1 text-gray-100">{{ $documento->tercero_nombre ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Creado por</div>
                        <div class="mt-1 text-gray-100">{{ $documento->creador?->name ?? '—' }}</div>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <div class="text-xs uppercase text-gray-500">Observaciones</div>
                        <div class="mt-1 whitespace-pre-line text-gray-100">{{ $documento->observaciones ?: '—' }}</div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                <div class="border-b border-white/5 p-4">
                    <h3 class="font-semibold text-gray-100">Detalle de inventario</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/5">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-400">
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3">Descripción</th>
                                <th class="px-4 py-3">Bodega</th>
                                <th class="px-4 py-3">Centro de costo</th>
                                <th class="px-4 py-3 text-right">Cantidad</th>
                                <th class="px-4 py-3 text-right">Costo unitario</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($documento->lineas as $linea)
                                <tr class="text-sm text-gray-300">
                                    <td class="px-4 py-3">{{ $linea->orden }}</td>
                                    <td class="px-4 py-3 font-mono">{{ $linea->product?->codigo }}</td>
                                    <td class="px-4 py-3">{{ $linea->descripcion }}</td>
                                    <td class="px-4 py-3">{{ $linea->bodega?->codigo ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $linea->centroCosto?->nombre ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $linea->cantidad, 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-right font-mono">$ {{ number_format((float) $linea->costo_unitario, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-mono">$ {{ number_format((float) $linea->costo_total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-white/10 font-semibold text-gray-100">
                            <tr>
                                <td colspan="7" class="px-4 py-4 text-right">Total</td>
                                <td class="px-4 py-4 text-right font-mono">$ {{ number_format((float) $documento->total, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
