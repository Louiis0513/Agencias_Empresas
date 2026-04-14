@php
    $isProductos = ($tab ?? 'productos') === 'productos';
    $canExportInventarioExcel = $isProductos && app(\App\Services\StorePermissionService::class)->can($store, 'inventario.view');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">
                    {{ $isProductos ? 'Informes de productos' : 'Informes de facturación' }}
                </h2>
                <p class="text-sm text-gray-400 mt-1">
                    {{ $isProductos ? 'Resumen visual de ventas, márgenes y stock (datos de demostración).' : 'Panel de facturación (contenido próximo).' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($isProductos)
                    @if($canExportInventarioExcel)
                        <a href="{{ route('stores.inventario.export-excel', $store) }}" class="px-3 py-2 rounded-lg border border-brand/30 bg-brand/20 text-brand text-sm font-medium hover:bg-brand/30 transition">
                            Exportar inventario Excel
                        </a>
                    @else
                        <button type="button" disabled class="px-3 py-2 rounded-lg border border-white/10 text-gray-500 text-sm cursor-not-allowed" title="Requiere permiso de inventario">
                            Exportar inventario Excel
                        </button>
                    @endif
                @else
                    <button type="button" disabled class="px-3 py-2 rounded-lg border border-white/10 text-gray-500 text-sm cursor-not-allowed">
                        Exportar Excel
                    </button>
                @endif
                <button type="button" disabled class="px-3 py-2 rounded-lg border border-white/10 text-gray-500 text-sm cursor-not-allowed">
                    Exportar PDF
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Pestañas de tipo de informe: solo el panel activo se renderiza abajo --}}
            <div class="flex flex-wrap gap-2 border-b border-white/10 pb-4">
                @storeCan($store, 'reports.products.view')
                <a href="{{ route('stores.reports.index', [$store, 'tab' => 'productos', 'ventas' => $ventasRange ?? \App\Services\ProductReportsService::VENTAS_7D]) }}" wire:navigate
                   class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $isProductos ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 border border-transparent hover:bg-white/5 hover:text-white' }}">
                    Productos
                </a>
                @endstoreCan
                @storeCan($store, 'reports.billing.view')
                <a href="{{ route('stores.reports.index', [$store, 'tab' => 'facturacion', 'ventas' => $ventasRange ?? \App\Services\ProductReportsService::VENTAS_7D]) }}" wire:navigate
                   class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ! $isProductos ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 border border-transparent hover:bg-white/5 hover:text-white' }}">
                    Facturación
                </a>
                @endstoreCan
            </div>

            @if($isProductos)
                {{-- ========== INFORME PRODUCTOS: dashboard informativo ========== --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-4 md:p-5">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Desde</label>
                            <input type="date" class="w-full rounded-lg border border-white/10 bg-dark-card text-gray-300 focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Hasta</label>
                            <input type="date" class="w-full rounded-lg border border-white/10 bg-dark-card text-gray-300 focus:border-brand focus:ring-brand">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Categoría</label>
                            <select class="w-full rounded-lg border border-white/10 bg-dark-card text-gray-300 focus:border-brand focus:ring-brand">
                                <option>Todas</option>
                                <option>Bebidas</option>
                                <option>Abarrotes</option>
                                <option>Limpieza</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="button" class="w-full md:w-auto px-3 py-2 rounded-lg bg-brand/20 text-brand border border-brand/30 text-sm">Aplicar</button>
                            <button type="button" class="w-full md:w-auto px-3 py-2 rounded-lg border border-white/10 text-gray-300 text-sm">Limpiar</button>
                        </div>
                    </div>
                </div>

                {{-- KPIs --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Unidades vendidas</p>
                        <p class="mt-2 text-2xl font-semibold text-white">1.248</p>
                        <p class="text-xs text-emerald-400 mt-1">+12,4% vs periodo anterior</p>
                    </div>
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Ingresos por ventas</p>
                        <p class="mt-2 text-2xl font-semibold text-white">$ 42.350.000</p>
                        <p class="text-xs text-emerald-400 mt-1">+8,1% vs periodo anterior</p>
                    </div>
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Margen bruto estimado</p>
                        <p class="mt-2 text-2xl font-semibold text-white">38,2%</p>
                        <p class="text-xs text-gray-400 mt-1">Sobre costo de venta</p>
                    </div>
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5">
                        <p class="text-xs uppercase tracking-wide text-gray-500">SKUs bajo mínimo</p>
                        <p class="mt-2 text-2xl font-semibold text-orange-300">14</p>
                        <p class="text-xs text-amber-400/90 mt-1">Requieren reposición</p>
                    </div>
                </div>

                {{-- Gráficos (placeholders estilo dashboard) --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 flex flex-col">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-white font-semibold">Ventas por periodo</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Unidades (demo)</p>
                            </div>
                        </div>
                        <div class="mt-4 flex-1 flex items-end justify-between gap-1 h-44 px-1 border-b border-white/10">
                            @foreach([40, 65, 45, 80, 55, 90, 70, 85, 60, 95] as $h)
                                <div class="flex-1 rounded-t bg-gradient-to-t from-brand/40 to-brand/80 min-w-0" style="height: {{ $h }}%"></div>
                            @endforeach
                        </div>
                        <div class="flex justify-between text-[10px] text-gray-500 mt-2 px-0.5">
                            @foreach(range(1, 10) as $m)
                                <span>{{ $m }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 flex flex-col items-center justify-center">
                        <h3 class="text-white font-semibold self-start w-full">Salud de inventario</h3>
                        <p class="text-xs text-gray-500 self-start w-full mt-0.5 mb-4">Cobertura vs alertas (demo)</p>
                        <div class="relative w-36 h-36">
                            <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                                <circle cx="18" cy="18" r="15.915" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="3"/>
                                <circle cx="18" cy="18" r="15.915" fill="none" stroke="rgb(147, 51, 234)" stroke-width="3"
                                    stroke-dasharray="72 100" stroke-linecap="round"/>
                                <circle cx="18" cy="18" r="15.915" fill="none" stroke="rgb(20, 184, 166)" stroke-width="3"
                                    stroke-dasharray="28 100" stroke-dashoffset="-72" stroke-linecap="round"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <span class="text-3xl font-bold text-white">78</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 w-full mt-4 text-sm">
                            <div>
                                <p class="text-gray-500 text-xs">En rango</p>
                                <p class="text-teal-400 font-medium">72%</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs">En alerta</p>
                                <p class="text-purple-400 font-medium">28%</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 flex flex-col">
                        <div>
                            <h3 class="text-white font-semibold">Utilidad acumulada</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Periodo seleccionado (demo)</p>
                        </div>
                        <p class="text-3xl font-semibold text-white mt-3">$ 16.210.500</p>
                        <p class="text-xs text-emerald-400">+7,5% respecto al tramo anterior</p>
                        <div class="mt-auto pt-4 h-28">
                            <svg viewBox="0 0 120 48" class="w-full h-full text-brand" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="areaInformes" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="rgb(20, 184, 166)" stop-opacity="0.35"/>
                                        <stop offset="100%" stop-color="rgb(20, 184, 166)" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                                <path d="M0,40 L15,35 L30,38 L45,28 L60,32 L75,18 L90,24 L105,12 L120,8 L120,48 L0,48 Z" fill="url(#areaInformes)"/>
                                <path d="M0,40 L15,35 L30,38 L45,28 L60,32 L75,18 L90,24 L105,12 L120,8" fill="none" stroke="rgb(20, 184, 166)" stroke-width="1.5"/>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Líneas dobles (tendencia) --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-5">
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div>
                            <h3 class="text-white font-semibold">Ingresos vs utilidad</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Tendencia semanal (demo)</p>
                        </div>
                    </div>
                    <div class="h-48 w-full">
                        <svg viewBox="0 0 200 80" class="w-full h-full" preserveAspectRatio="none">
                            <line x1="0" y1="70" x2="200" y2="70" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                            <polyline fill="none" stroke="rgb(255,255,255)" stroke-width="1.2" opacity="0.85"
                                points="0,55 25,50 50,45 75,40 100,35 125,30 150,28 175,22 200,18"/>
                            <polyline fill="none" stroke="rgb(249, 115, 22)" stroke-width="1.2"
                                points="0,62 25,58 50,52 75,48 100,42 125,38 150,34 175,30 200,26"/>
                        </svg>
                    </div>
                    <div class="flex gap-6 text-xs text-gray-400 mt-2">
                        <span class="inline-flex items-center gap-2"><span class="w-3 h-0.5 bg-white/80"></span> Ingresos</span>
                        <span class="inline-flex items-center gap-2"><span class="w-3 h-0.5 bg-orange-500"></span> Utilidad</span>
                    </div>
                </div>

                {{-- Tablas Top 10: más vendidos arriba, mayor margen abajo (sin filtro de fechas); stock/utilidad en grid --}}
                <div class="space-y-4">
                    <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-white/5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-white font-semibold text-sm">Top 10 — Más vendidos</h3>
                                <p class="text-xs text-gray-500">Unidades vendidas (facturas no anuladas; incluye pendientes de pago)</p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @php $vr = $ventasRange ?? \App\Services\ProductReportsService::VENTAS_7D; @endphp
                                <a href="{{ route('stores.reports.index', ['store' => $store, 'tab' => 'productos', 'ventas' => \App\Services\ProductReportsService::VENTAS_7D]) }}" wire:navigate class="px-2.5 py-1 rounded-md text-xs font-medium border transition {{ $vr === \App\Services\ProductReportsService::VENTAS_7D ? 'bg-brand/20 text-brand border-brand/30' : 'border-white/10 text-gray-400 hover:bg-white/5 hover:text-white' }}">7D</a>
                                <a href="{{ route('stores.reports.index', ['store' => $store, 'tab' => 'productos', 'ventas' => \App\Services\ProductReportsService::VENTAS_1M]) }}" wire:navigate class="px-2.5 py-1 rounded-md text-xs font-medium border transition {{ $vr === \App\Services\ProductReportsService::VENTAS_1M ? 'bg-brand/20 text-brand border-brand/30' : 'border-white/10 text-gray-400 hover:bg-white/5 hover:text-white' }}">1M</a>
                                <a href="{{ route('stores.reports.index', ['store' => $store, 'tab' => 'productos', 'ventas' => \App\Services\ProductReportsService::VENTAS_3M]) }}" wire:navigate class="px-2.5 py-1 rounded-md text-xs font-medium border transition {{ $vr === \App\Services\ProductReportsService::VENTAS_3M ? 'bg-brand/20 text-brand border-brand/30' : 'border-white/10 text-gray-400 hover:bg-white/5 hover:text-white' }}">3M</a>
                                <a href="{{ route('stores.reports.index', ['store' => $store, 'tab' => 'productos', 'ventas' => \App\Services\ProductReportsService::VENTAS_SIEMPRE]) }}" wire:navigate class="px-2.5 py-1 rounded-md text-xs font-medium border transition {{ $vr === \App\Services\ProductReportsService::VENTAS_SIEMPRE ? 'bg-brand/20 text-brand border-brand/30' : 'border-white/10 text-gray-400 hover:bg-white/5 hover:text-white' }}">Siempre</a>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th class="px-3 py-2 text-left">#</th>
                                        <th class="px-3 py-2 text-left">Producto</th>
                                        <th class="px-3 py-2 text-left">SKU</th>
                                        <th class="px-3 py-2 text-right">Cant.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-gray-200">
                                    @forelse($topMasVendidos ?? [] as $index => $fila)
                                        <tr class="hover:bg-white/5">
                                            <td class="px-3 py-2">{{ $index + 1 }}</td>
                                            <td class="px-3 py-2 max-w-xs truncate" title="{{ $fila['nombre'] }}">{{ $fila['nombre'] }}</td>
                                            <td class="px-3 py-2">@if($fila['sku'] !== null && $fila['sku'] !== ''){{ $fila['sku'] }}@else<span class="text-gray-500">—</span>@endif</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($fila['cantidad'], 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-6 text-center text-gray-500">Sin ventas en periodo seleccionado.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-white/5">
                            <h3 class="text-white font-semibold text-sm">Top 10 — Mayor margen</h3>
                            <p class="text-xs text-gray-500">% de margen bruto actual (simples, variantes en lote y unidades serializadas disponibles; sin filtro de fechas)</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th class="px-3 py-2 text-left">#</th>
                                        <th class="px-3 py-2 text-left">Producto</th>
                                        <th class="px-3 py-2 text-left">SKU</th>
                                        <th class="px-3 py-2 text-right">Costo</th>
                                        <th class="px-3 py-2 text-right">Precio</th>
                                        <th class="px-3 py-2 text-right">Margen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-gray-200">
                                    @forelse($topMayorMargen ?? [] as $index => $fila)
                                        <tr class="hover:bg-white/5">
                                            <td class="px-3 py-2">{{ $index + 1 }}</td>
                                            <td class="px-3 py-2 max-w-md truncate" title="{{ $fila['nombre'] }}">{{ $fila['nombre'] }}</td>
                                            <td class="px-3 py-2">@if($fila['sku'] !== null && $fila['sku'] !== ''){{ $fila['sku'] }}@else<span class="text-gray-500">—</span>@endif</td>
                                            <td class="px-3 py-2 text-right">@if($fila['costo'] !== null){{ money($fila['costo'], $store->currency ?? 'COP', false) }}@else<span class="text-gray-500">—</span>@endif</td>
                                            <td class="px-3 py-2 text-right">@if($fila['precio'] !== null){{ money($fila['precio'], $store->currency ?? 'COP') }}@else<span class="text-gray-500">—</span>@endif</td>
                                            <td class="px-3 py-2 text-right">@if($fila['margen_pct'] !== null){{ number_format($fila['margen_pct'], 2, ',', '.') }}%@else<span class="text-gray-500">—</span>@endif</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-3 py-6 text-center text-gray-500">Sin datos de margen en el catálogo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-white/5">
                            <h3 class="text-white font-semibold text-sm">Top 10 — Stock bajo</h3>
                            <p class="text-xs text-gray-500">Prioridad de reposición</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th class="px-3 py-2 text-left">#</th>
                                        <th class="px-3 py-2 text-left">Producto</th>
                                        <th class="px-3 py-2 text-left">SKU</th>
                                        <th class="px-3 py-2 text-right">Stock</th>
                                        <th class="px-3 py-2 text-left">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-gray-200">
                                    @for($i = 1; $i <= 10; $i++)
                                        <tr class="hover:bg-white/5">
                                            <td class="px-3 py-2">{{ $i }}</td>
                                            <td class="px-3 py-2">Producto Stock {{ $i }}</td>
                                            <td class="px-3 py-2">INV-{{ str_pad((string) (50 + $i), 3, '0', STR_PAD_LEFT) }}</td>
                                            <td class="px-3 py-2 text-right">{{ max(0, 9 - $i) }}</td>
                                            <td class="px-3 py-2">
                                                <span class="text-xs px-2 py-0.5 rounded-md {{ $i <= 5 ? 'bg-red-500/15 text-red-300 border border-red-500/20' : 'bg-amber-500/15 text-amber-200 border border-amber-500/20' }}">
                                                    {{ $i <= 5 ? 'Crítico' : 'Bajo' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 border-b border-white/5">
                            <h3 class="text-white font-semibold text-sm">Top 10 — Mayor utilidad</h3>
                            <p class="text-xs text-gray-500">Volumen × rentabilidad (demo)</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wide">
                                    <tr>
                                        <th class="px-3 py-2 text-left">#</th>
                                        <th class="px-3 py-2 text-left">Producto</th>
                                        <th class="px-3 py-2 text-right">Uds.</th>
                                        <th class="px-3 py-2 text-right">Util. unit.</th>
                                        <th class="px-3 py-2 text-right">Util. total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-gray-200">
                                    @for($i = 1; $i <= 10; $i++)
                                        <tr class="hover:bg-white/5">
                                            <td class="px-3 py-2">{{ $i }}</td>
                                            <td class="px-3 py-2">Producto Utilidad {{ $i }}</td>
                                            <td class="px-3 py-2 text-right">{{ 95 - ($i * 3) }}</td>
                                            <td class="px-3 py-2 text-right">$ {{ number_format(5000 + ($i * 350), 0, ',', '.') }}</td>
                                            <td class="px-3 py-2 text-right">$ {{ number_format(350000 - ($i * 12000), 0, ',', '.') }}</td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>

                {{-- Métrica extra sugerida --}}
                <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-white/5 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-white font-semibold text-sm">Productos sin movimiento (30 días)</h3>
                            <p class="text-xs text-gray-500">Rotación y riesgo de obsolescencia (demo)</p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-md bg-purple-500/15 text-purple-300 border border-purple-500/20">Sugerido</span>
                    </div>
                    <div class="p-4 text-sm text-gray-400">
                        Aquí irá el listado cuando exista el servicio. Vista previa: <span class="text-gray-300">42 SKUs</span> sin ventas en el último mes (dato ficticio).
                    </div>
                </div>
            @else
                {{-- ========== INFORME FACTURACIÓN: solo placeholder (no carga el dashboard de productos) ========== --}}
                <div class="bg-dark-card border border-dashed border-white/15 rounded-xl p-10 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <h3 class="text-lg font-semibold text-white">Informe de facturación</h3>
                    <p class="text-gray-400 text-sm mt-2 max-w-md mx-auto">
                        Este panel se habilitará cuando definamos las métricas y permisos de facturación. No comparte contenido con el informe de productos.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
