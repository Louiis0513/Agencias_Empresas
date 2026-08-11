{{-- Pestaña Documentos de productos / servicios --}}
@php
    $saldosCreateUrl = route('stores.products.documentos.saldos-iniciales.create', $store);
    $ajusteCreateUrl = route('stores.products.documentos.ajuste.create', $store);
    $trasladoCreateUrl = route('stores.products.documentos.traslado.create', $store);
    $documentos = $documentos ?? null;
@endphp

<div
    class="space-y-4"
    x-data="{
        openImport: false,
        openNuevo: false,
        closeMenus() { this.openImport = false; this.openNuevo = false; }
    }"
    @keydown.escape.window="closeMenus()"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-white">Inventarios</h3>
            <p class="mt-0.5 text-sm text-gray-500">Documentos de productos y servicios (ajustes, saldos iniciales, etc.).</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative" @click.outside="openImport = false">
                <button type="button"
                        @click="openImport = !openImport; openNuevo = false"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-brand/40 text-brand text-sm font-semibold hover:bg-brand/10">
                    Importar
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="openImport" x-cloak
                     class="absolute right-0 mt-1 w-80 rounded-lg border border-white/10 bg-dark-card shadow-xl z-30 py-1">
                    <button type="button" disabled
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
                        Importar comprobantes de ajuste
                        <span class="block text-xs text-gray-600">Próximamente</span>
                    </button>
                    <a href="{{ $saldosCreateUrl }}" wire:navigate
                       class="block px-4 py-2.5 text-sm text-gray-100 hover:bg-white/5">
                        Importar saldos iniciales de inventario
                        <span class="block text-xs text-gray-500">Carga saldos y genera documento A</span>
                    </a>
                    <button type="button" disabled
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
                        Importar conteo físico
                        <span class="block text-xs text-gray-600">Próximamente</span>
                    </button>
                </div>
            </div>

            <div class="relative" @click.outside="openNuevo = false">
                <button type="button"
                        @click="openNuevo = !openNuevo; openImport = false"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:opacity-95">
                    Nuevo documento
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="openNuevo" x-cloak
                     class="absolute right-0 mt-1 w-80 rounded-lg border border-white/10 bg-dark-card shadow-xl z-30 py-1">
                    <a href="{{ $ajusteCreateUrl }}" wire:navigate
                       class="block px-4 py-2.5 text-sm text-gray-100 hover:bg-white/5">
                        Ajuste de inventario
                        <span class="block text-xs text-gray-500">Aumenta o disminuye cantidades y valores</span>
                    </a>
                    <a href="{{ $trasladoCreateUrl }}" wire:navigate
                       class="block px-4 py-2.5 text-sm text-gray-100 hover:bg-white/5">
                        Nota de traslado entre bodegas
                        <span class="block text-xs text-gray-500">Mueve inventario de una bodega a otra</span>
                    </a>
                    <button type="button" disabled
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
                        Ensamble de producto
                        <span class="block text-xs text-gray-600">Próximamente</span>
                    </button>
                    <button type="button" disabled
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
                        Conteo físico / Ajustes
                        <span class="block text-xs text-gray-600">Próximamente</span>
                    </button>
                    <button type="button" disabled
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
                        Remisión
                        <span class="block text-xs text-gray-600">Próximamente</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
        <div class="p-4 sm:p-5 space-y-4 border-b border-white/5">
            <form method="GET" action="{{ route('stores.products', $store) }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="documentos">
                <div class="relative flex-1 min-w-[14rem]">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="search" name="doc_search" value="{{ $documentosFiltros['search'] ?? '' }}"
                           placeholder="Buscar comprobante / tercero"
                           class="w-full pl-10 rounded-lg border border-white/10 bg-white/5 text-gray-100 placeholder:text-gray-600 text-sm">
                </div>
                <input type="date" name="doc_fecha_desde" value="{{ $documentosFiltros['fecha_desde'] ?? '' }}"
                       class="rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
                <input type="date" name="doc_fecha_hasta" value="{{ $documentosFiltros['fecha_hasta'] ?? '' }}"
                       class="rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 bg-white/5 text-gray-200 text-sm hover:bg-white/10">
                    Filtrar
                </button>
                <a href="{{ route('stores.products', $store) }}?tab=documentos"
                   class="text-brand text-sm hover:underline ml-auto">Limpiar filtros</a>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-white/5">
                <thead>
                    <tr class="text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3">Tipo transacción</th>
                        <th class="px-4 py-3">Comprobante</th>
                        <th class="px-4 py-3">Fecha elaboración</th>
                        <th class="px-4 py-3">Identificación</th>
                        <th class="px-4 py-3">Sucursal</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse(($documentos ?? collect()) as $documento)
                        <tr class="text-sm text-gray-300 hover:bg-white/[0.02]">
                            <td class="px-4 py-3">{{ $documento->tituloTipoDocumento() }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('stores.products.documentos.show', [$store, $documento]) }}"
                                   class="font-mono text-brand hover:underline" wire:navigate>
                                    {{ $documento->numero }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $documento->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-gray-500">—</td>
                            <td class="px-4 py-3 text-gray-500">—</td>
                            <td class="px-4 py-3">{{ $documento->tercero_nombre ?: '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">
                                $ {{ number_format((float) $documento->total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <p class="text-gray-300 font-medium">No hay documentos de inventario</p>
                                <p class="mt-2 text-sm text-gray-500">Empieza con <span class="text-gray-400">Importar → Importar saldos iniciales de inventario</span>.</p>
                                <a href="{{ $saldosCreateUrl }}" wire:navigate
                                   class="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:opacity-95">
                                    Importar saldos iniciales de inventario
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/5 px-4 py-3 text-sm text-gray-500">
            @if($documentos && method_exists($documentos, 'total'))
                <div>{{ $documentos->total() }} documento{{ $documentos->total() === 1 ? '' : 's' }}</div>
                <div class="ml-auto">{{ $documentos->links() }}</div>
            @else
                <div>0 documentos</div>
            @endif
        </div>
    </div>
</div>
