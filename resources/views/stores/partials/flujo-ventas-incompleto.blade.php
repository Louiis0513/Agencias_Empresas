{{--
    Aviso: carrito de ventas deshabilitado hasta rediseño.
    Requiere: $store
--}}
@php
    $storeRef = $store ?? ($this->store ?? null);
@endphp
@if($storeRef)
    <div class="mb-4 rounded-xl border border-amber-500/40 bg-amber-950/40 px-4 py-3 text-sm text-amber-100" role="status">
        <p class="font-medium text-amber-50">Flujo incompleto (ventas)</p>
        <p class="mt-1 text-amber-100/90">
            El carrito de ventas (stock, variantes, seriales) fue retirado.
            Se reconstruirá junto con el catálogo y la facturación contable
            <span class="text-amber-50 font-medium">(en construcción)</span>.
        </p>
    </div>
@endif
