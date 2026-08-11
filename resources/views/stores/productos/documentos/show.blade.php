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

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-950/30 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif

            {{-- Documento imprimible estilo Siigo (fondo claro) --}}
            <article class="overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-900 shadow-xl">
                <div class="space-y-5 p-5 sm:p-7">
                    {{-- Cabecera 3 columnas --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_1.6fr_1.1fr] md:items-start">
                        <div class="flex min-h-[7.5rem] items-center justify-center">
                            @if(! empty($logoUrl))
                                <img src="{{ $logoUrl }}" alt="Logo" class="mx-auto max-h-36 max-w-[14rem] object-contain">
                            @else
                                <div class="flex h-28 w-40 items-center justify-center rounded border border-dashed border-gray-300 text-xs text-gray-400">
                                    Sin logo
                                </div>
                            @endif
                        </div>

                        <div class="text-center">
                            <p class="text-sm font-bold uppercase tracking-wide text-gray-900 sm:text-base">{{ $store->name }}</p>
                            @if($store->rut_nit)
                                <p class="mt-1 text-xs text-gray-500">NIT {{ $store->rut_nit }}</p>
                            @endif
                            @if($store->address)
                                <p class="mt-0.5 text-xs text-gray-500">{{ $store->address }}</p>
                            @endif
                            @if($store->phone || ($store->mobile ?? null))
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Tel: {{ implode(' - ', array_filter([$store->phone, $store->mobile ?? null])) }}
                                </p>
                            @endif
                            @if(($ciudadEmpresa ?? '') !== '')
                                <p class="mt-0.5 text-xs text-gray-500">{{ $ciudadEmpresa }}</p>
                            @endif
                        </div>

                        <div class="space-y-2">
                            <div class="rounded border border-gray-400 px-3 py-3 text-center">
                                <p class="text-sm font-semibold leading-snug text-gray-900">
                                    {{ $documento->tituloTipoDocumento() }}
                                </p>
                                <p class="mt-1 text-base font-bold text-gray-900">
                                    No. {{ $documento->numeroImpresion() }}
                                </p>
                            </div>
                            <div class="overflow-hidden rounded border border-gray-400">
                                <div class="bg-gray-200 px-3 py-1.5 text-center text-xs font-bold text-gray-700">
                                    {{ $documento->esTraslado() ? 'Fecha de traslado' : 'Fecha Comprobante' }}
                                </div>
                                <div class="px-3 py-3 text-center text-base font-bold text-gray-900">
                                    {{ $documento->fecha->format('Y-m-d') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($documento->tercero_nombre)
                        <p class="text-sm text-gray-700">
                            <span class="font-semibold">Tercero:</span> {{ $documento->tercero_nombre }}
                        </p>
                    @endif
                    @if($documento->observaciones)
                        <p class="text-sm text-gray-700">
                            <span class="font-semibold">Observaciones:</span> {{ $documento->observaciones }}
                        </p>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-xs sm:text-sm">
                            <thead>
                                <tr class="bg-gray-200 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-800 sm:text-xs">
                                    <th class="border border-gray-300 px-2 py-2 text-center">Ítem</th>
                                    <th class="border border-gray-300 px-2 py-2">Producto</th>
                                    <th class="border border-gray-300 px-2 py-2">Descripción</th>
                                    <th class="border border-gray-300 px-2 py-2">Referencia de fábrica</th>
                                    @if($documento->esTraslado())
                                        <th class="border border-gray-300 px-2 py-2">Bodega origen</th>
                                        <th class="border border-gray-300 px-2 py-2">Bodega destino</th>
                                        <th class="border border-gray-300 px-2 py-2 text-right">Cantidad</th>
                                    @else
                                        <th class="border border-gray-300 px-2 py-2">Bodega</th>
                                        <th class="border border-gray-300 px-2 py-2 text-center">Aumenta/Disminuye</th>
                                        <th class="border border-gray-300 px-2 py-2 text-right">Cantidad</th>
                                        <th class="border border-gray-300 px-2 py-2 text-right">Costo total</th>
                                        <th class="border border-gray-300 px-2 py-2">Nombre de Cuenta contable</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documento->lineas as $linea)
                                    <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }} text-gray-800">
                                        <td class="border border-gray-300 px-2 py-2 text-center">{{ $linea->orden }}</td>
                                        <td class="border border-gray-300 px-2 py-2 font-mono">{{ $linea->product?->codigo }}</td>
                                        <td class="border border-gray-300 px-2 py-2">{{ $linea->descripcion }}</td>
                                        <td class="border border-gray-300 px-2 py-2">{{ $linea->product?->referencia ?: '—' }}</td>
                                        @if($documento->esTraslado())
                                            <td class="border border-gray-300 px-2 py-2">{{ $linea->etiquetaBodegaOrigen() }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ $linea->etiquetaBodegaDestino() }}</td>
                                            <td class="border border-gray-300 px-2 py-2 text-right font-mono">{{ number_format((float) $linea->cantidad, 2, '.', ',') }}</td>
                                        @else
                                            <td class="border border-gray-300 px-2 py-2">{{ $linea->bodega?->nombre ?? ($linea->bodega?->codigo ?? 'Sin asignar') }}</td>
                                            <td class="border border-gray-300 px-2 py-2 text-center">{{ $linea->etiquetaDireccion() }}</td>
                                            <td class="border border-gray-300 px-2 py-2 text-right font-mono">{{ number_format((float) $linea->cantidad, 2, '.', ',') }}</td>
                                            <td class="border border-gray-300 px-2 py-2 text-right font-mono">{{ number_format((float) $linea->costo_total, 2, ',', '.') }}</td>
                                            <td class="border border-gray-300 px-2 py-2">{{ $linea->product?->categoriaContable?->cuentaInventario?->nombre ?? '—' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-3 text-xs text-gray-500">
                        <span>{{ $documento->numero }} · {{ $documento->estado }}</span>
                        @unless($documento->esTraslado())
                            <span class="font-semibold text-gray-800">
                                Total: $ {{ number_format((float) $documento->total, 2, ',', '.') }}
                            </span>
                        @endunless
                    </div>
                </div>
            </article>
        </div>
    </div>
</x-app-layout>
