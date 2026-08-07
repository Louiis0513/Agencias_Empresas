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

    $unidadDianDefault = old('unidad_medida_dian', '94');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ __('Creación de producto / servicio') }} — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.products', $store) }}" wire:navigate
               class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-8"
         x-data="{
            tipo: @js(old('tipo', 'producto')),
            tabExtra: 'impuestos',
            categorias: @js($categoriasJson),
            categoriaProductoDefault: @js(old('categoria_contable_id', $categoriaProductoDefault)),
            categoriaServicioDefault: @js(old('categoria_contable_id', $categoriaServicioDefault)),
            categoriaId: @js(old('categoria_contable_id', $categoriaProductoDefault)),
            esInventariable: @js((bool) old('es_inventariable', true)),
            visibleVentas: @js((bool) old('visible_en_ventas', true)),
            precioIncluyeIva: @js((bool) old('precio_incluye_iva', false)),
            impuestosCargo: @js($impuestosCargoJson),
            impuestoCargoId: @js(old('impuesto_cargo_id', '')),
            aplicaImpuestoBolsas: @js((bool) old('aplica_impuesto_bolsas', false)),
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
         }"
         x-init="onTipoChange()">
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

            @include('stores.productos._form', ['isEdit' => false, 'product' => null])
        </div>
    </div>
</x-app-layout>
