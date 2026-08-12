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
    $stockUrlTemplate = str_replace(
        (string) ($productosInventariables->first()?->id ?? '0'),
        '__PRODUCT__',
        route('stores.products.stock-bodega', [$store, $productosInventariables->first()?->id ?? 0])
    );
    // Si no hay productos, plantilla manual:
    if ($productosInventariables->isEmpty()) {
        $stockUrlTemplate = url('/stores/'.$store->slug.'/productos/__PRODUCT__/stock-bodega');
    }
@endphp

<x-capture-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-white sm:text-xl">
            Conteo físico
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
         x-data="conteoFisicoForm({
            productos: @js($productosJs),
            bodegas: @js($bodegasJs),
            terceros: @js($tercerosJs),
            fechaDefault: @js($hoy),
            storeUrl: @js(route('stores.products.documentos.conteo.store', $store)),
            stockUrlTemplate: @js($stockUrlTemplate),
            plantillaUrl: @js(route('stores.products.documentos.conteo.plantilla', $store)),
            parsePlantillaUrl: @js(route('stores.products.documentos.conteo.plantilla.parse', $store)),
         })">
        <div class="flex-1 space-y-4 px-3 py-4 sm:px-5 lg:px-6 pb-28">
            @if(session('error'))
                <div class="rounded-lg border border-red-500/30 bg-red-950/30 px-3 py-2 text-sm text-red-200">{{ session('error') }}</div>
            @endif
            <template x-if="errorMsg">
                <div class="rounded-lg border border-red-500/30 bg-red-950/30 px-3 py-2 text-sm text-red-200" x-text="errorMsg"></div>
            </template>
            <template x-if="!errorMsg">
                <div class="rounded-lg border border-sky-500/30 bg-sky-950/20 px-3 py-2 text-sm text-sky-100">
                    Indica las existencias contadas. El sistema calcula la diferencia frente al stock actual y registra solo movimientos de cantidad (sin asiento económico).
                </div>
            </template>

            <div class="space-y-3 rounded-xl border border-white/5 bg-dark-card p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Plantilla Excel</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Descarga por bodega, llena cantidad_contada y sube aquí para precargar las líneas.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="w-full sm:w-64">
                        <label class="mb-1 block text-sm text-gray-400">Bodega de la plantilla</label>
                        <select x-model="plantillaBodegaId"
                                class="w-full rounded-lg border-white/10 bg-white/5 py-2 text-sm text-gray-100">
                            <option value="sin_asignar">Sin asignar</option>
                            @foreach($bodegas as $bodega)
                                <option value="{{ $bodega->id }}">{{ $bodega->codigo }} — {{ $bodega->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <a :href="plantillaDownloadHref"
                       class="inline-flex items-center justify-center rounded-lg border border-brand/40 px-4 py-2 text-sm font-semibold text-brand hover:bg-brand/10">
                        Descargar plantilla
                    </a>
                    <label class="inline-flex cursor-pointer items-center justify-center rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold text-gray-100 hover:bg-white/15">
                        <span x-text="importingPlantilla ? 'Cargando…' : 'Subir plantilla'"></span>
                        <input type="file"
                               class="hidden"
                               accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                               :disabled="importingPlantilla"
                               @change="onPlantillaSelected($event)">
                    </label>
                </div>
            </div>

            <div class="space-y-3 rounded-xl border border-white/5 bg-dark-card p-4 sm:p-5">
                <p class="text-sm text-gray-400">
                    El stock del sistema se recalcula al guardar. La diferencia preview es solo informativa.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-6">
                    <div class="w-full sm:w-56">
                        <label class="mb-1 block text-sm text-gray-400">Fecha <span class="text-red-400">*</span></label>
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
                    <p class="text-xs text-gray-500">Producto + bodega + existencias contadas</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead>
                            <tr class="bg-sky-900/90 text-left text-xs font-semibold uppercase tracking-wide text-white">
                                <th class="w-12 px-3 py-3">Nº</th>
                                <th class="min-w-[14rem] px-3 py-3">Producto</th>
                                <th class="min-w-[10rem] px-3 py-3">Descripción</th>
                                <th class="min-w-[11rem] px-3 py-3">Bodega</th>
                                <th class="min-w-[7rem] px-3 py-3 text-right">Stock sistema</th>
                                <th class="min-w-[8rem] px-3 py-3 text-right">Existencias contadas</th>
                                <th class="min-w-[7rem] px-3 py-3 text-right">Diferencia</th>
                                <th class="w-20 px-2 py-3 text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(line, index) in lines" :key="line.key">
                                <tr class="border-t border-white/5 align-top">
                                    <td class="px-3 py-2.5 text-gray-500" x-text="index + 1"></td>
                                    <td class="px-3 py-2.5">
                                        <div class="relative" :data-dd-anchor="'product-' + line.key">
                                            <input type="text"
                                                   x-model="line.product_search"
                                                   @focus="openDd(line, 'product', $event)"
                                                   @click="openDd(line, 'product', $event)"
                                                   @input="openDd(line, 'product', $event); onProductSearchInput(line)"
                                                   @keydown.escape.stop="closeDd()"
                                                   placeholder="Buscar producto"
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
                                        <div class="relative" :data-dd-anchor="'bodega-' + line.key">
                                            <input type="text"
                                                   x-model="line.bodega_search"
                                                   @focus="openDd(line, 'bodega', $event)"
                                                   @click="openDd(line, 'bodega', $event)"
                                                   @input="openDd(line, 'bodega', $event); onBodegaSearchInput(line)"
                                                   @keydown.escape.stop="closeDd()"
                                                   placeholder="Elegir bodega o Sin asignar"
                                                   class="w-full rounded-lg border border-white/15 bg-white/5 py-2 pr-9 text-sm text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                                   autocomplete="off">
                                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-300">
                                        <span x-show="line.stock_loading" class="text-gray-500">…</span>
                                        <span x-show="!line.stock_loading && line.stock_sistema !== null"
                                              x-text="Number(line.stock_sistema).toFixed(2)"></span>
                                        <span x-show="!line.stock_loading && line.stock_sistema === null" class="text-gray-600">—</span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input type="text"
                                               inputmode="decimal"
                                               autocomplete="off"
                                               x-model="line.cantidad_contada_display"
                                               @focus="$event.target.select()"
                                               @input="onQtyInput(line, $event)"
                                               @blur="onQtyBlur(line)"
                                               class="w-full rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-right text-sm tabular-nums text-gray-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                               placeholder="0.00">
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums"
                                        :class="deltaPreview(line) === null ? 'text-gray-600' : (deltaPreview(line) > 0 ? 'text-emerald-400' : (deltaPreview(line) < 0 ? 'text-amber-400' : 'text-gray-400'))">
                                        <span x-text="deltaPreview(line) === null ? '—' : (deltaPreview(line) > 0 ? '+' : '') + Number(deltaPreview(line)).toFixed(2)"></span>
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
                          placeholder="Comentarios opcionales"></textarea>
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-dark-card/95 backdrop-blur-md">
            <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ $documentosUrl }}" wire:navigate class="text-sm text-gray-400 hover:text-white">Cancelar</a>
                <div class="flex flex-col items-end gap-1">
                    <button type="button"
                            @click="registrar()"
                            :disabled="saving || !puedeRegistrar"
                            :class="saving || !puedeRegistrar
                                ? 'cursor-not-allowed bg-brand/40 text-white/70'
                                : 'bg-brand text-white hover:opacity-95'"
                            class="inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-semibold">
                        <span x-text="saving ? 'Registrando…' : 'Registrar conteo'"></span>
                    </button>
                    <p class="text-[11px] text-gray-500" x-show="!puedeRegistrar && !saving">
                        Completa producto, bodega y existencias contadas (con diferencia vs sistema).
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
