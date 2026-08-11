@php
    use App\Models\Product;

    $tieneFiltros = collect($filtros ?? [])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

    $precioLista = function (Product $product): string {
        $precios = $product->precios;
        if ($precios->isEmpty()) {
            return '—';
        }
        $activo = $precios->first(fn ($pp) => $pp->listaPrecio && $pp->listaPrecio->activo);
        $elegido = $activo ?? $precios->sortBy(fn ($pp) => $pp->listaPrecio?->numero ?? 999)->first();

        return $elegido
            ? number_format((float) $elegido->precio, 2, ',', '.')
            : '—';
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ __('Productos y servicios') }} — {{ $store->name }}
            </h2>
        </div>
    </x-slot>

    <div
        class="py-8"
        x-data="{
            tab: @js(in_array(request('tab'), ['gestion', 'combos', 'documentos', 'costeo'], true) ? request('tab') : 'gestion'),
            drawerOpen: false,
            openActionId: null,
            actionMenuStyle: {},
            toggleAction(id, event) {
                event?.stopPropagation();
                if (this.openActionId === id) {
                    this.openActionId = null;
                    return;
                }
                const btn = event?.currentTarget;
                if (btn) {
                    const r = btn.getBoundingClientRect();
                    this.actionMenuStyle = {
                        position: 'fixed',
                        top: (r.bottom + 4) + 'px',
                        right: (window.innerWidth - r.right) + 'px',
                        zIndex: 80,
                    };
                }
                this.$nextTick(() => { this.openActionId = id; });
            }
        }"
        @keydown.escape.window="drawerOpen = false; openActionId = null"
        @scroll.window="openActionId = null"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <x-flash-alert type="success" class="mb-4">{{ session('success') }}</x-flash-alert>
            @endif
            @if(session('error'))
                <x-flash-alert type="error" class="mb-4">{{ session('error') }}</x-flash-alert>
            @endif

            {{-- Pestañas estilo Siigo --}}
            <div class="mb-6 border-b border-white/10">
                <nav class="-mb-px flex flex-wrap gap-1" aria-label="Pestañas productos">
                    <button type="button"
                            @click="tab = 'gestion'"
                            :class="tab === 'gestion' ? 'border-brand text-brand' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-white/20'"
                            class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition">
                        Gestión de productos / servicios
                    </button>
                    <button type="button"
                            @click="tab = 'combos'"
                            :class="tab === 'combos' ? 'border-brand text-brand' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-white/20'"
                            class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition">
                        Gestión combos
                    </button>
                    <button type="button"
                            @click="tab = 'documentos'"
                            :class="tab === 'documentos' ? 'border-brand text-brand' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-white/20'"
                            class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition">
                        Documentos de productos / servicios
                    </button>
                    <button type="button"
                            @click="tab = 'costeo'"
                            :class="tab === 'costeo' ? 'border-brand text-brand' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-white/20'"
                            class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition">
                        Costeo mensual
                    </button>
                </nav>
            </div>

            {{-- Gestión --}}
            <div x-show="tab === 'gestion'" x-cloak>
                <div class="bg-dark-card border border-white/5 overflow-visible sm:rounded-xl">
                    <div class="p-4 sm:p-6 space-y-4">
                        {{-- Barra: buscador + filtros + acciones --}}
                        <div class="flex flex-wrap items-center gap-3">
                            <form method="GET" action="{{ route('stores.products', $store) }}"
                                  class="flex flex-1 flex-wrap items-center gap-2 min-w-[16rem]"
                                  id="productos-search-form">
                                @foreach(['tipo', 'categoria_contable_id', 'estado', 'es_inventariable', 'stock'] as $hidden)
                                    @if(!empty($filtros[$hidden]))
                                        <input type="hidden" name="{{ $hidden }}" value="{{ $filtros[$hidden] }}">
                                    @endif
                                @endforeach
                                <div class="relative flex-1 min-w-[200px]">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    </span>
                                    <input type="search" name="search" value="{{ $filtros['search'] ?? '' }}"
                                           placeholder="Buscar producto o servicio"
                                           class="w-full pl-10 rounded-full border border-white/10 bg-white/5 text-gray-100 placeholder:text-gray-500 text-sm">
                                </div>
                                <button type="submit" class="sr-only">Buscar</button>
                            </form>

                            <button type="button"
                                    @click="drawerOpen = true"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm font-medium hover:bg-white/10">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                Filtros
                                @if($tieneFiltros)
                                    <span class="inline-flex h-2 w-2 rounded-full bg-brand"></span>
                                @endif
                            </button>

                            <div class="flex items-center gap-2 ml-auto">
                                <button type="button" title="Descargar"
                                        class="inline-flex items-center justify-center h-10 w-10 rounded-lg border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10"
                                        aria-label="Descargar">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                </button>
                                <button type="button"
                                        class="inline-flex items-center px-4 py-2 rounded-lg border border-brand/40 text-brand text-sm font-semibold hover:bg-brand/10">
                                    Ver reportes
                                </button>
                                <div class="relative" x-data="{ openCreate: false }" @click.outside="openCreate = false">
                                    <button type="button"
                                            @click="openCreate = !openCreate"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:opacity-95">
                                        Crear / Importar
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div x-show="openCreate" x-cloak x-transition
                                         class="absolute right-0 z-20 mt-1 w-52 rounded-lg border border-white/10 bg-dark-card py-1 shadow-xl">
                                        @storeCan($store, 'products.create')
                                        <a href="{{ route('stores.products.create', $store) }}" wire:navigate
                                           class="block px-4 py-2 text-sm text-gray-200 hover:bg-white/5">
                                            Crear producto / servicio
                                        </a>
                                        @endstoreCan
                                        <button type="button"
                                                class="block w-full px-4 py-2 text-sm text-gray-500 text-left cursor-not-allowed"
                                                disabled>
                                            Importar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tabla: overflow-visible para que el menú de acciones se superponga --}}
                        <div class="rounded-lg border border-white/5 overflow-visible">
                            <div class="overflow-x-auto overflow-y-visible">
                            <table class="min-w-full divide-y divide-white/5 text-sm">
                                <thead class="bg-white/5 text-left text-xs uppercase tracking-wider text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3 font-medium">Tipo</th>
                                        <th class="px-4 py-3 font-medium">Nombre</th>
                                        <th class="px-4 py-3 font-medium">Código</th>
                                        <th class="px-4 py-3 font-medium">Unidad</th>
                                        <th class="px-4 py-3 font-medium">Precios</th>
                                        <th class="px-4 py-3 font-medium">Impuestos</th>
                                        <th class="px-4 py-3 font-medium">Stock</th>
                                        <th class="px-4 py-3 font-medium">Estado</th>
                                        <th class="px-4 py-3 font-medium text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-gray-200">
                                    @forelse($products as $product)
                                        <tr class="hover:bg-white/[0.03]">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                {{ $product->esServicio() ? 'Servicio' : 'Producto' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <a href="{{ route('stores.products.show', [$store, $product]) }}"
                                                   class="text-brand font-medium hover:underline">
                                                    {{ $product->nombre }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 font-mono text-xs text-gray-300">{{ $product->codigo }}</td>
                                            <td class="px-4 py-3 text-gray-300">{{ $product->unidad_medida_factura ?: 'unidad' }}</td>
                                            <td class="px-4 py-3 tabular-nums text-gray-300">{{ $precioLista($product) }}</td>
                                            <td class="px-4 py-3 text-gray-300">
                                                {{ $product->impuestoCargo?->nombre ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-500">—</td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($product->is_active)
                                                    <span class="inline-flex items-center gap-1.5 text-emerald-400">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                        Activo
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 text-gray-500">
                                                        <span class="h-2 w-2 rounded-full bg-gray-500"></span>
                                                        Inactivo
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="inline-flex items-center gap-1">
                                                    @storeCan($store, 'products.edit')
                                                    <a href="{{ route('stores.products.edit', [$store, $product]) }}"
                                                       class="inline-flex items-center px-3 py-1.5 rounded-md border border-brand/40 text-brand text-xs font-semibold hover:bg-brand/10">
                                                        Editar
                                                    </a>
                                                    @endstoreCan
                                                    <div class="relative">
                                                        <button type="button"
                                                                @click="toggleAction({{ $product->id }}, $event)"
                                                                class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-brand/40 text-brand hover:bg-brand/10"
                                                                aria-label="Más acciones"
                                                                :aria-expanded="openActionId === {{ $product->id }}">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                        </button>
                                                        <template x-teleport="body">
                                                            <div x-show="openActionId === {{ $product->id }}"
                                                                 x-cloak
                                                                 x-transition
                                                                 @click.outside="openActionId = null"
                                                                 :style="actionMenuStyle"
                                                                 class="w-44 rounded-lg border border-white/10 bg-dark-card py-1 shadow-xl text-left">
                                                                @storeCan($store, 'products.edit')
                                                                <button type="button"
                                                                        @click="openActionId = null; Livewire.dispatch('open-add-product-photo', { productId: {{ $product->id }} })"
                                                                        class="block w-full px-4 py-2 text-sm text-gray-200 hover:bg-white/5 text-left">
                                                                    Agregar foto
                                                                </button>
                                                                @endstoreCan
                                                                @storeCan($store, 'products.edit')
                                                                <form method="POST" action="{{ route('stores.products.toggle', [$store, $product]) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button type="submit" class="block w-full px-4 py-2 text-sm text-gray-200 hover:bg-white/5 text-left">
                                                                        {{ $product->is_active ? 'Inactivar' : 'Activar' }}
                                                                    </button>
                                                                </form>
                                                                @endstoreCan
                                                                @storeCan($store, 'products.destroy')
                                                                <form method="POST" action="{{ route('stores.products.destroy', [$store, $product]) }}"
                                                                      onsubmit="return confirm(@js('¿Eliminar «'.$product->nombre.'»? Una vez eliminado no se puede recuperar; habrá que crearlo de nuevo.'));">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="block w-full px-4 py-2 text-sm text-red-400 hover:bg-white/5 text-left">Eliminar</button>
                                                                </form>
                                                                @endstoreCan
                                                                <button type="button" class="block w-full px-4 py-2 text-sm text-gray-200 hover:bg-white/5 text-left">Duplicar</button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="px-4 py-10 text-center text-gray-400">
                                                No hay productos ni servicios con los criterios actuales.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            </div>
                        </div>

                        @if($products->hasPages())
                            <div class="pt-2">
                                {{ $products->links() }}
                            </div>
                        @elseif($products->total() > 0)
                            <p class="text-xs text-gray-500 text-right">
                                {{ $products->firstItem() }} a {{ $products->lastItem() }} de {{ number_format($products->total()) }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Overlay filtros --}}
                <div x-show="drawerOpen" x-transition.opacity
                     class="fixed inset-0 z-40 bg-black/60"
                     style="display: none;"
                     @click="drawerOpen = false"></div>

                {{-- Drawer filtros --}}
                <div x-show="drawerOpen"
                     x-transition:enter="transition transform duration-200 ease-out"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition transform duration-150 ease-in"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-dark-card border-l border-white/10 shadow-2xl flex flex-col"
                     style="display: none;">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/10">
                        <h3 class="text-lg font-semibold text-white">Filtros</h3>
                        <button type="button" @click="drawerOpen = false" class="text-gray-400 hover:text-white" aria-label="Cerrar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p class="px-5 pt-3 text-sm text-gray-400">Define los filtros que deseas aplicar a la tabla:</p>

                    <form method="GET" action="{{ route('stores.products', $store) }}" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        @if(!empty($filtros['search']))
                            <input type="hidden" name="search" value="{{ $filtros['search'] }}">
                        @endif

                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Tipo de producto</label>
                            <select name="tipo" class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
                                <option value="">Todos</option>
                                <option value="producto" @selected(($filtros['tipo'] ?? '') === 'producto')>Producto</option>
                                <option value="servicio" @selected(($filtros['tipo'] ?? '') === 'servicio')>Servicio</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Categoría</label>
                            <select name="categoria_contable_id" class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
                                <option value="">Todas</option>
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat->id }}" @selected((string) ($filtros['categoria_contable_id'] ?? '') === (string) $cat->id)>
                                        {{ $cat->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Estado</label>
                            <select name="estado" class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
                                <option value="">Todos</option>
                                <option value="activo" @selected(($filtros['estado'] ?? '') === 'activo')>Activos</option>
                                <option value="inactivo" @selected(($filtros['estado'] ?? '') === 'inactivo')>Inactivos</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Stock del producto</label>
                            <select name="stock" class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
                                <option value="">Todos</option>
                                <option value="con_saldos" @selected(($filtros['stock'] ?? '') === 'con_saldos')>Con saldos</option>
                                <option value="sin_saldos" @selected(($filtros['stock'] ?? '') === 'sin_saldos')>Sin saldos</option>
                                <option value="bajo_minimo" @selected(($filtros['stock'] ?? '') === 'bajo_minimo')>Por debajo del saldo mínimo</option>
                                <option value="negativos" @selected(($filtros['stock'] ?? '') === 'negativos')>Saldos negativos</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">El filtro de stock se habilitará cuando exista inventario.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1">Inventariables</label>
                            <select name="es_inventariable" class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-sm">
                                <option value="">Todos</option>
                                <option value="1" @selected(($filtros['es_inventariable'] ?? '') === '1')>Sí</option>
                                <option value="0" @selected(($filtros['es_inventariable'] ?? '') === '0')>No</option>
                            </select>
                        </div>

                        <div class="flex gap-2 pt-2 pb-6">
                            <button type="submit"
                                    class="flex-1 inline-flex justify-center px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:opacity-95">
                                Aplicar filtros
                            </button>
                            <a href="{{ route('stores.products', $store) }}"
                               class="inline-flex justify-center px-4 py-2 rounded-lg border border-white/10 text-gray-200 text-sm font-medium hover:bg-white/5">
                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Pestañas placeholder --}}
            <div x-show="tab === 'combos'" x-cloak class="bg-dark-card border border-white/5 rounded-xl p-10 text-center">
                <p class="text-gray-300 font-medium">Gestión combos</p>
                <p class="mt-2 text-sm text-gray-500">Próximamente</p>
            </div>
            <div x-show="tab === 'documentos'" x-cloak>
                @include('stores.productos._documentos', [
                    'store' => $store,
                    'documentos' => $documentos ?? null,
                    'documentosFiltros' => $documentosFiltros ?? [],
                ])
            </div>
            <div x-show="tab === 'costeo'" x-cloak class="bg-dark-card border border-white/5 rounded-xl p-10 text-center">
                <p class="text-gray-300 font-medium">Costeo mensual</p>
                <p class="mt-2 text-sm text-gray-500">Próximamente</p>
            </div>
        </div>
    </div>

    @storeCan($store, 'products.edit')
        <livewire:add-product-photo-modal :store-id="$store->id" />
    @endstoreCan
</x-app-layout>
