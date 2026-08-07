{{--
    Aviso: factura de venta sin inventario/planes hasta rediseño contable.
    Requiere: $store
--}}
@php
    $storeRef = $store ?? ($this->store ?? null);
@endphp
@if($storeRef)
    <div class="mb-4 rounded-xl border border-amber-500/40 bg-amber-950/40 px-4 py-3 text-sm text-amber-100" role="status">
        <p class="font-medium text-amber-50">Flujo incompleto (factura de venta)</p>
        <p class="mt-1 text-amber-100/90">
            La creación de facturas con productos, inventario y suscripciones está deshabilitada mientras se rediseña el flujo
            <span class="text-amber-50 font-medium">documento → cuentas PUC → asiento</span>.
            Puedes consultar facturas históricas; no crear ventas nuevas por este módulo.
        </p>
    </div>
@endif
