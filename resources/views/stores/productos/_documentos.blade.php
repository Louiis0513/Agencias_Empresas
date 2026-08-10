{{-- Pestaña Documentos de productos / servicios (solo UI, estilo Siigo) --}}
@php
    $saldosCreateUrl = route('stores.products.documentos.saldos-iniciales.create', $store);
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
                        <span class="block text-xs text-gray-500">Formulario (sin contabilizar aún)</span>
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
                    <button type="button" disabled
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
                        Ajuste de inventario
                        <span class="block text-xs text-gray-600">Próximamente</span>
                    </button>
                    <button type="button" disabled
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-500 cursor-not-allowed">
                        Nota de traslado entre bodegas
                        <span class="block text-xs text-gray-600">Próximamente</span>
                    </button>
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
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[14rem]">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="search" disabled
                           placeholder="Buscar cliente / tercero"
                           class="w-full pl-10 rounded-lg border border-white/10 bg-white/5 text-gray-400 placeholder:text-gray-600 text-sm cursor-not-allowed"
                           title="Disponible cuando existan documentos">
                </div>
                <button type="button" disabled
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 bg-white/5 text-gray-500 text-sm cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    Más filtros
                </button>
                <button type="button" disabled
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 bg-white/5 text-gray-500 text-sm cursor-not-allowed ml-auto"
                        title="Próximamente">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Descargar
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-gray-300">
                    Fecha elaboración: Todas
                    <span class="text-gray-500" aria-hidden="true">×</span>
                </span>
                <button type="button" disabled class="text-brand/60 text-sm cursor-not-allowed">Limpiar filtros</button>
            </div>
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
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/5 px-4 py-3 text-sm text-gray-500">
            <div class="flex items-center gap-2">
                <span>Items por página</span>
                <select disabled class="rounded-md border-white/10 bg-white/5 text-gray-500 text-sm cursor-not-allowed">
                    <option>50</option>
                </select>
            </div>
            <div>0 documentos</div>
            <div class="flex items-center gap-1 text-gray-600">
                <span class="px-2">Página 1 de 1</span>
            </div>
        </div>
    </div>
</div>
