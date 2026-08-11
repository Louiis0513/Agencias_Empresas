@php
    $productosJs = $productosInventariables->map(fn ($p) => [
        'id' => $p->id,
        'codigo' => $p->codigo,
        'nombre' => $p->nombre,
    ])->values();
    $bodegasJs = $bodegas->map(fn ($b) => [
        'id' => $b->id,
        'codigo' => $b->codigo,
        'nombre' => $b->nombre,
    ])->values();
    $tercerosJs = $terceros->map(fn ($t) => [
        'id' => $t->id,
        'codigo' => $t->numero_identificacion,
        'nombre' => $t->nombre,
    ])->values();
    $documentosUrl = route('stores.products', $store).'?tab=documentos';
    $hoy = now()->timezone($store->timezone ?? config('app.timezone'))->format('Y-m-d');
@endphp

<x-capture-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-white sm:text-xl">
            Nota de traslado entre bodegas
            <span class="font-normal text-gray-400">— {{ $store->name }}</span>
        </h1>
        <a href="{{ $documentosUrl }}"
           wire:navigate
           class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 text-gray-300 hover:bg-white/10 hover:text-white transition"
           title="Cerrar"
           aria-label="Cerrar">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </a>
    </x-slot>

    <div class="flex min-h-[calc(100vh-3.5rem)] flex-col"
         x-data="trasladoBodegasForm({
            productos: @js($productosJs),
            bodegas: @js($bodegasJs),
            terceros: @js($tercerosJs),
            fechaDefault: @js($hoy),
            storeUrl: @js(route('stores.products.documentos.traslado.store', $store)),
         })">
        <div class="flex-1 space-y-4 px-3 py-4 sm:px-5 lg:px-6 pb-28">
            <template x-if="errorMsg">
                <div class="rounded-lg border border-red-500/30 bg-red-950/30 px-3 py-2 text-sm text-red-200" x-text="errorMsg"></div>
            </template>
            <template x-if="!errorMsg">
                <div class="rounded-lg border border-sky-500/30 bg-sky-950/20 px-3 py-2 text-sm text-sky-100">
                    Al contabilizar se crea el documento NT, la salida en bodega origen y la entrada en bodega destino (asiento en $0).
                </div>
            </template>

            <div class="space-y-3 rounded-xl border border-white/5 bg-dark-card p-4 sm:p-5">
                <p class="text-sm text-gray-400">
                    Registra el movimiento interno de productos entre bodegas. Origen y destino pueden ser «Sin asignar», pero no iguales.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-6">
                    <div class="w-full sm:w-56">
                        <label class="mb-1 block text-sm text-gray-400">Fecha de traslado <span class="text-red-400">*</span></label>
                        <input type="date" x-model="fecha"
                               class="w-full rounded-lg border-white/10 bg-white/5 py-2 text-sm text-gray-100">
                    </div>
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-sm text-gray-400">Tercero (opcional)</label>
                        <div class="relative" data-dd-anchor="tercero-header">
                            <input type="text"
                                   x-model="tercero_search"
                                   @focus="openDd(null, 'tercero', $event)"
                                   @click="openDd(null, 'tercero', $event)"
                                   @input="openDd(null, 'tercero', $event); onTerceroSearchInput()"
                                   @keydown.escape.stop="closeDd()"
                                   placeholder="Por defecto: nombre de la tienda"
                                   class="w-full rounded-lg border border-white/10 bg-white/5 py-2 pr-9 text-sm text-gray-100 placeholder:text-gray-600 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                   autocomplete="off">
                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 px-4 py-2.5">
                    <h2 class="text-sm font-semibold text-white">Detalle</h2>
                    <p class="text-xs text-gray-500">Al completar una fila se agrega la siguiente · Enter también avanza</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-sm">
                        <thead>
                            <tr class="bg-sky-900/90 text-left text-xs font-semibold uppercase tracking-wide text-white">
                                <th class="w-12 px-3 py-3">Nº</th>
                                <th class="min-w-[15rem] px-3 py-3">Producto</th>
                                <th class="min-w-[12rem] px-3 py-3">Descripción</th>
                                <th class="min-w-[12rem] px-3 py-3">Bodega origen</th>
                                <th class="min-w-[12rem] px-3 py-3">Bodega destino</th>
                                <th class="w-32 px-3 py-3">Cantidad</th>
                                <th class="w-16 px-2 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lines" :key="line.key">
                                <tr class="border-b border-white/5 align-middle text-gray-200">
                                    <td class="px-3 py-2.5 tabular-nums text-gray-400" x-text="index + 1"></td>
                                    <td class="px-3 py-2.5">
                                        <div class="relative" :data-dd-anchor="'product-' + line.key">
                                            <input type="text"
                                                   x-model="line.product_search"
                                                   @focus="openDd(line, 'product', $event)"
                                                   @click="openDd(line, 'product', $event)"
                                                   @input="openDd(line, 'product', $event); onProductSearchInput(line)"
                                                   @keydown.escape.stop="closeDd()"
                                                   @keydown.enter.prevent="onLineEnter(index)"
                                                   :placeholder="index === lines.length - 1 && !line.product_id ? 'Agregar otro ítem...' : 'Buscar código o nombre'"
                                                   class="w-full rounded-lg border border-white/15 bg-white/5 py-2 pr-9 text-sm text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                                   autocomplete="off">
                                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input type="text" x-model="line.descripcion" readonly
                                               class="w-full cursor-default truncate rounded-lg border border-white/10 bg-white/[0.03] py-2 text-sm text-gray-300"
                                               placeholder="Se completa al elegir producto">
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="relative" :data-dd-anchor="'bodega_origen-' + line.key">
                                            <input type="text"
                                                   x-model="line.bodega_origen_search"
                                                   @focus="openDd(line, 'bodega_origen', $event)"
                                                   @click="openDd(line, 'bodega_origen', $event)"
                                                   @input="openDd(line, 'bodega_origen', $event); onBodegaOrigenSearchInput(line)"
                                                   @keydown.escape.stop="closeDd()"
                                                   placeholder="Elegir bodega o Sin asignar"
                                                   class="w-full rounded-lg border border-white/15 bg-white/5 py-2 pr-9 text-sm text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                                   autocomplete="off">
                                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="relative" :data-dd-anchor="'bodega_destino-' + line.key">
                                            <input type="text"
                                                   x-model="line.bodega_destino_search"
                                                   @focus="openDd(line, 'bodega_destino', $event)"
                                                   @click="openDd(line, 'bodega_destino', $event)"
                                                   @input="openDd(line, 'bodega_destino', $event); onBodegaDestinoSearchInput(line)"
                                                   @keydown.escape.stop="closeDd()"
                                                   placeholder="Elegir bodega o Sin asignar"
                                                   class="w-full rounded-lg border border-white/15 bg-white/5 py-2 pr-9 text-sm text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                                   autocomplete="off">
                                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input type="text"
                                               inputmode="decimal"
                                               autocomplete="off"
                                               x-model="line.cantidad_display"
                                               @focus="$event.target.select()"
                                               @input="onQtyInput(line, $event)"
                                               @blur="onQtyBlur(line)"
                                               @keydown.enter.prevent="onLineEnter(index)"
                                               class="w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-right text-sm tabular-nums text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                               placeholder="0.00">
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="flex items-center justify-center gap-0.5" x-show="line.product_id" x-cloak>
                                            <button type="button" @click="duplicateLine(index)" title="Duplicar"
                                                    class="rounded-md p-1.5 text-sky-400 transition hover:bg-sky-500/20">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                            <button type="button" @click="removeLine(index)" title="Eliminar"
                                                    class="rounded-md p-1.5 text-red-400 transition hover:bg-red-500/20">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-white/5 bg-dark-card p-4">
                <label class="mb-1 block text-sm text-gray-400">Observaciones</label>
                <textarea x-model="observaciones" rows="3"
                          class="w-full rounded-lg border-white/10 bg-white/5 text-sm text-gray-100 placeholder:text-gray-600"
                          placeholder="Comentarios opcionales para la impresión del documento"></textarea>
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-dark-card/95 backdrop-blur-md">
            <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ $documentosUrl }}" wire:navigate class="text-sm text-gray-400 hover:text-white">Cancelar</a>
                <div class="flex flex-col items-end gap-1">
                    <button type="button"
                            @click="contabilizar()"
                            :disabled="saving || !puedeContabilizar"
                            :class="saving || !puedeContabilizar
                                ? 'cursor-not-allowed bg-brand/40 text-white/70'
                                : 'bg-brand text-white hover:opacity-95'"
                            class="inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-semibold">
                        <span x-text="saving ? 'Contabilizando…' : 'Contabilizar'"></span>
                    </button>
                    <p class="text-[11px] text-gray-500" x-show="!puedeContabilizar && !saving">
                        Completa producto, cantidad, origen y destino (distintos; puedes usar Sin asignar).
                    </p>
                </div>
            </div>
        </div>

        <template x-teleport="body">
            <div x-show="dd.open"
                 x-cloak
                 @mousedown.outside="onDdOutside($event)"
                 @keydown.escape.window="closeDd()"
                 :style="ddStyle"
                 class="fixed z-[9999] overflow-hidden rounded-lg border border-white/10 bg-slate-900 shadow-2xl"
                 style="display: none;">
                <div class="grid grid-cols-[7rem_1fr] gap-2 border-b border-white/10 bg-white/[0.04] px-3 py-2 text-[11px] uppercase tracking-wide text-gray-400">
                    <span x-text="dd.type === 'tercero' ? 'Identificación' : 'Código'"></span>
                    <span>Nombre</span>
                </div>
                <ul class="max-h-56 overflow-y-auto">
                    <template x-for="item in ddItems" :key="String(item.id ?? item.codigo)">
                        <li>
                            <button type="button"
                                    @mousedown.prevent="pickDdItem(item)"
                                    class="grid w-full grid-cols-[7rem_1fr] gap-2 px-3 py-2 text-left text-sm text-gray-100 hover:bg-sky-600/40">
                                <span class="truncate text-gray-300" x-text="item.codigo"></span>
                                <span class="truncate" x-text="item.nombre"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="ddItems.length === 0" class="px-3 py-3 text-sm text-gray-500">Sin resultados</li>
                </ul>
            </div>
        </template>
    </div>
</x-capture-layout>
