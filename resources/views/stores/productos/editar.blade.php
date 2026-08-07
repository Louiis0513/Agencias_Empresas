@php
    $categoriasJson = $categorias->map(fn ($c) => [
        'id' => $c->id,
        'nombre' => $c->nombre,
        'tipo' => $c->tipo,
    ])->values();

    $impuestosCargoJson = $impuestosCargo->map(fn ($i) => [
        'id' => $i->id,
        'nombre' => $i->nombre,
        'por_valor' => (bool) $i->por_valor,
    ])->values();

    $unidadesDianJson = $unidadesDian->map(fn ($u) => [
        'codigo' => $u->codigo,
        'nombre' => $u->nombre,
        'label' => $u->codigo.' - '.$u->nombre,
    ])->values();

    $unidadDianDefault = old('unidad_medida_dian', $product->unidad_medida_dian ?: '94');
    $tipoDefault = old('tipo', $product->tipo);
    $categoriaDefault = old('categoria_contable_id', $product->categoria_contable_id);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ __('Edición de producto / servicio') }} — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.products.show', [$store, $product]) }}" wire:navigate
               class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al detalle
            </a>
        </div>
    </x-slot>

    <div class="py-8"
         x-data="{
            tipo: @js($tipoDefault),
            tabExtra: 'impuestos',
            categorias: @js($categoriasJson),
            categoriaProductoDefault: @js($categoriaProductoDefault),
            categoriaServicioDefault: @js($categoriaServicioDefault),
            categoriaId: @js($categoriaDefault),
            esInventariable: @js((bool) old('es_inventariable', $product->es_inventariable)),
            visibleVentas: @js((bool) old('visible_en_ventas', $product->visible_en_ventas)),
            precioIncluyeIva: @js((bool) old('precio_incluye_iva', $product->precio_incluye_iva)),
            impuestosCargo: @js($impuestosCargoJson),
            impuestoCargoId: @js(old('impuesto_cargo_id', $product->impuesto_cargo_id ?? '')),
            aplicaImpuestoBolsas: @js((bool) old('aplica_impuesto_bolsas', $product->aplica_impuesto_bolsas)),
            get categoriasFiltradas() {
                return this.categorias.filter(c => c.tipo === this.tipo);
            },
            get impuestoCargoPorValor() {
                if (!this.impuestoCargoId) return false;
                const imp = this.impuestosCargo.find(i => String(i.id) === String(this.impuestoCargoId));
                return !!(imp && imp.por_valor);
            },
            onTipoChange() {
                const def = this.tipo === 'servicio'
                    ? this.categoriaServicioDefault
                    : this.categoriaProductoDefault;
                const ok = this.categoriasFiltradas.some(c => String(c.id) === String(this.categoriaId));
                if (!ok) {
                    this.categoriaId = def || (this.categoriasFiltradas[0]?.id ?? '');
                }
                if (this.tipo === 'servicio') {
                    this.esInventariable = false;
                } else if (!this.esInventariable) {
                    this.esInventariable = true;
                }
            }
         }">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('success'))
                <x-flash-alert type="success">{{ session('success') }}</x-flash-alert>
            @endif
            @if(session('error'))
                <x-flash-alert type="error">{{ session('error') }}</x-flash-alert>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('stores.productos._form', ['isEdit' => true, 'product' => $product])
        </div>
    </div>

    @storeCan($store, 'products.edit')
        <livewire:add-product-photo-modal :store-id="$store->id" />
    @endstoreCan
</x-app-layout>
