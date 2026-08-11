@php
    $esServicio = $product->esServicio();
    $tipoLabel = $esServicio ? 'Servicio' : 'Producto';
    $unidadDian = $product->unidadMedidaFe
        ? $product->unidadMedidaFe->codigo.' - '.$product->unidadMedidaFe->nombre
        : ($product->unidad_medida_dian ?: '—');
    $dash = fn ($v) => ($v === null || $v === '') ? '—' : $v;
    $siNo = fn ($v) => $v ? 'Sí' : 'No';
    $preciosPorLista = $product->precios->keyBy('lista_precio_id');
    $fmtPrecio = function ($valor): string {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return number_format((float) $valor, 2, ',', '.').' COP';
    };
    $impuestoLabel = function ($impuesto) use ($dash): string {
        if (! $impuesto) {
            return '—';
        }
        if ($impuesto->por_valor) {
            return $impuesto->nombre;
        }
        $tarifa = rtrim(rtrim(number_format((float) $impuesto->tarifa, 2, '.', ''), '0'), '.');

        return $impuesto->nombre.($tarifa !== '' ? ' '.$tarifa.'%' : '');
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight truncate">
                {{ $tipoLabel }} — {{ $product->nombre }}
            </h2>
            <a href="{{ route('stores.products', $store) }}"
               class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-white/10 text-gray-300 hover:text-white hover:border-white/30 transition"
               aria-label="Cerrar y volver al listado"
               title="Volver">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('success'))
                <x-flash-alert type="success">{{ session('success') }}</x-flash-alert>
            @endif
            @if(session('error'))
                <x-flash-alert type="error">{{ session('error') }}</x-flash-alert>
            @endif

            {{-- Metadatos + acciones --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-sm text-gray-400">
                    Creado: {{ $product->created_at?->format('d/m/Y H:i:s') }} hrs
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    @storeCan($store, 'products.destroy')
                    <form method="POST" action="{{ route('stores.products.destroy', [$store, $product]) }}"
                          onsubmit="return confirm(@js('¿Eliminar «'.$product->nombre.'»? Una vez eliminado no se puede recuperar; habrá que crearlo de nuevo.'));">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-red-500/50 text-red-400 text-sm font-medium hover:bg-red-500/10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Eliminar
                        </button>
                    </form>
                    @endstoreCan
                    @storeCan($store, 'products.create')
                    <form method="POST" action="{{ route('stores.products.duplicate', [$store, $product]) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-brand/40 text-brand text-sm font-medium hover:bg-brand/10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Duplicar
                        </button>
                    </form>
                    @endstoreCan
                    @storeCan($store, 'products.edit')
                    <a href="{{ route('stores.products.edit', [$store, $product]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-brand/40 text-brand text-sm font-medium hover:bg-brand/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Editar
                    </a>
                    @endstoreCan
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Columna principal --}}
                <div class="lg:col-span-2 space-y-4">
                    {{-- Título + estado --}}
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
                        <h1 class="text-xl sm:text-2xl font-bold text-white leading-snug">
                            {{ $product->nombre }}
                        </h1>
                        @unless($esServicio)
                            @php
                                $unidadStock = $product->unidad_medida_factura ?: 'und';
                                $stockFmt = number_format((float) ($stockActual ?? 0), 2, ',', '.');
                            @endphp
                            <div class="mt-2" x-data="{ openStock: false }">
                                <p class="text-sm text-gray-400">
                                    Stock actual: {{ $stockFmt }} {{ $unidadStock }}.
                                    @if($product->es_inventariable)
                                        <button type="button"
                                                class="text-brand hover:underline ml-1"
                                                @click="openStock = !openStock"
                                                x-text="openStock ? 'Ocultar stock' : 'Ver stock'"></button>
                                    @endif
                                </p>
                                @if($product->es_inventariable)
                                    <div x-show="openStock"
                                         x-cloak
                                         x-transition
                                         class="mt-3 rounded-lg border border-white/10 bg-white/[0.03] p-3 max-w-md">
                                        <p class="text-xs font-medium text-gray-400 mb-2">Stock por bodega</p>
                                        @forelse($stockPorBodega as $fila)
                                            <div class="flex items-center justify-between gap-3 py-1.5 text-sm {{ ! $loop->last ? 'border-b border-white/5' : '' }}">
                                                <span class="text-gray-300 truncate">
                                                    @if(($fila['codigo'] ?? '') !== '—')
                                                        <span class="font-mono text-gray-500">{{ $fila['codigo'] }}</span>
                                                        ·
                                                    @endif
                                                    {{ $fila['nombre'] }}
                                                </span>
                                                <span class="tabular-nums text-gray-100 shrink-0">
                                                    {{ number_format((float) $fila['cantidad'], 2, ',', '.') }}
                                                </span>
                                            </div>
                                        @empty
                                            <p class="text-sm text-gray-500">Sin movimientos en bodegas.</p>
                                        @endforelse
                                        <div class="mt-2 pt-2 border-t border-white/10 flex items-center justify-between text-sm">
                                            <span class="text-gray-400">Total</span>
                                            <span class="tabular-nums font-medium text-white">{{ $stockFmt }} {{ $unidadStock }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endunless
                        <div class="mt-4 flex items-center gap-3">
                            <span class="text-sm text-gray-300">{{ $product->is_active ? 'Activo' : 'Inactivo' }}</span>
                            @storeCan($store, 'products.edit')
                            <form method="POST" action="{{ route('stores.products.toggle', [$store, $product]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        role="switch"
                                        aria-checked="{{ $product->is_active ? 'true' : 'false' }}"
                                        title="{{ $product->is_active ? 'Inactivar' : 'Activar' }}"
                                        class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brand/50 {{ $product->is_active ? 'bg-brand' : 'bg-white/20' }}">
                                    <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition translate-y-0.5 {{ $product->is_active ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                                </button>
                            </form>
                            @else
                            <span role="switch"
                                  aria-checked="{{ $product->is_active ? 'true' : 'false' }}"
                                  class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors {{ $product->is_active ? 'bg-brand' : 'bg-white/20' }}">
                                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition translate-y-0.5 {{ $product->is_active ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                            </span>
                            @endstoreCan
                        </div>
                    </div>

                    {{-- Datos generales --}}
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-white mb-4">Datos generales</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-5">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Tipo</p>
                                <p class="text-sm text-gray-100">{{ $tipoLabel }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">{{ $esServicio ? 'Nombre del servicio' : 'Nombre del producto' }}</p>
                                <p class="text-sm text-gray-100 break-words">{{ $product->nombre }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">{{ $esServicio ? 'Servicio inventariable' : 'Producto inventariable' }}</p>
                                <p class="text-sm text-gray-100">{{ $siNo($product->es_inventariable) }}</p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 mb-1">{{ $esServicio ? 'Categoría del servicio' : 'Categoría del producto' }}</p>
                                <p class="text-sm text-gray-100">{{ $dash($product->categoriaContable?->nombre) }}</p>
                            </div>
                            @if($esServicio)
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Código del servicio</p>
                                    <p class="text-sm text-gray-100 font-mono">{{ $product->codigo }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Unidad de medida DIAN</p>
                                    <p class="text-sm text-gray-100">{{ $unidadDian }}</p>
                                </div>
                            @else
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Código de barras</p>
                                    <p class="text-sm text-gray-100">{{ $dash($product->codigo_barras) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Visible en facturas de venta</p>
                                    <p class="text-sm text-gray-100">{{ $siNo($product->visible_en_ventas) }}</p>
                                </div>
                            @endif

                            @unless($esServicio)
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Código de producto (SKU)</p>
                                    <p class="text-sm text-gray-100 font-mono">{{ $product->codigo }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Unidad de medida DIAN</p>
                                    <p class="text-sm text-gray-100">{{ $unidadDian }}</p>
                                </div>
                                <div></div>
                            @endunless
                        </div>
                    </div>

                    {{-- Descripción (y stock) --}}
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-white mb-4">
                            {{ $esServicio ? 'Descripción' : 'Descripción y stock' }}
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-5">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Referencia</p>
                                <p class="text-sm text-gray-100">{{ $dash($product->referencia) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Unidad de medida de la factura</p>
                                <p class="text-sm text-gray-100">{{ $dash($product->unidad_medida_factura) }}</p>
                            </div>
                            @unless($esServicio)
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Stock mínimo</p>
                                    <p class="text-sm text-gray-100">
                                        {{ $product->stock_minimo !== null ? number_format((float) $product->stock_minimo, 0, ',', '.') : '—' }}
                                    </p>
                                </div>
                            @else
                                <div></div>
                            @endunless
                            <div class="sm:col-span-2">
                                <p class="text-xs text-gray-500 mb-1">Descripción larga</p>
                                <p class="text-sm text-gray-100 whitespace-pre-wrap">{{ $dash($product->descripcion) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-4">
                    {{-- Lista de precios --}}
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-white">Lista de precios</h3>
                            <button type="button" class="text-sm text-brand hover:underline">Ver todos</button>
                        </div>
                        <div class="space-y-3">
                            @forelse($listasActivas as $lista)
                                @php $pp = $preciosPorLista->get($lista->id); @endphp
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-gray-400">{{ $lista->nombre }}</p>
                                    <p class="text-sm text-gray-100 tabular-nums text-right">{{ $fmtPrecio($pp?->precio) }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">—</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Impuestos --}}
                    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
                        <h3 class="text-base font-semibold text-white mb-4">Impuestos</h3>
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm text-gray-400">Impuesto cargo</p>
                                <p class="text-sm text-gray-100 text-right">{{ $impuestoLabel($product->impuestoCargo) }}</p>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm text-gray-400">Retención</p>
                                <p class="text-sm text-gray-100 text-right">{{ $impuestoLabel($product->impuestoRetencion) }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Imágenes (sidebar, debajo de impuestos) --}}
                    @if($product->images->isNotEmpty())
                        <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
                            <h3 class="text-base font-semibold text-white mb-4">Imágenes</h3>
                            @include('stores.productos._image-thumbs', [
                                'images' => $product->images,
                                'productId' => $product->id,
                            ])
                        </div>
                    @endif

                    {{-- Datos exportación (solo producto) --}}
                    @unless($esServicio)
                        <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
                            <h3 class="text-base font-semibold text-white mb-4">Datos exportación</h3>
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-gray-400">Marca</p>
                                    <p class="text-sm text-gray-100 text-right">{{ $dash($product->marca) }}</p>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-gray-400">Modelo</p>
                                    <p class="text-sm text-gray-100 text-right">{{ $dash($product->modelo) }}</p>
                                </div>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-gray-400">Código arancelario</p>
                                    <p class="text-sm text-gray-100 text-right">{{ $dash($product->codigo_arancelario) }}</p>
                                </div>
                            </div>
                        </div>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <livewire:manage-product-image-modal :store-id="$store->id" />
    @storeCan($store, 'products.edit')
        <livewire:add-product-photo-modal :store-id="$store->id" />
    @endstoreCan
</x-app-layout>
