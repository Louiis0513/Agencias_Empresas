{{--
    Aviso: catálogo de productos en reconstrucción (estilo Siigo / categoría contable).
    Requiere: $store
--}}
@php
    $storeRef = $store ?? ($this->store ?? null);
@endphp
@if($storeRef)
    <div class="mb-4 rounded-xl border border-amber-500/40 bg-amber-950/40 px-4 py-3 text-sm text-amber-100" role="status">
        <p class="font-medium text-amber-50">Flujo incompleto (productos)</p>
        <p class="mt-1 text-amber-100/90">
            El catálogo comercial anterior (categorías, atributos, inventario por lotes/series) fue retirado.
            El maestro de productos/servicios se rediseñará con
            <span class="text-amber-50 font-medium">categoría contable</span>
            (en construcción).
        </p>
        <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
            @storeCan($storeRef, 'contabilidad.categorias.view')
                <a href="{{ route('stores.contabilidad.categorias', $storeRef) }}" wire:navigate
                   class="underline hover:text-white">Categoría de productos y servicios</a>
            @endstoreCan
        </p>
    </div>
@endif
