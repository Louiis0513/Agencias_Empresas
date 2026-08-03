{{--
    Aviso: cobro/pago por bolsillo es operativo; el flujo contable definitivo
    será Forma de pago → cuenta PUC → asiento.
    Requiere: $store
--}}
@php
    $storeRef = $store ?? ($this->store ?? null);
@endphp
@if($storeRef)
    <div class="mb-4 rounded-xl border border-amber-500/40 bg-amber-950/40 px-4 py-3 text-sm text-amber-100" role="status">
        <p class="font-medium text-amber-50">Flujo incompleto (bolsillo)</p>
        <p class="mt-1 text-amber-100/90">
            El cobro o pago por bolsillo es solo operativo (saldo de caja/banco).
            El flujo contable definitivo será
            <span class="text-amber-50 font-medium">Forma de pago → cuenta PUC → asiento</span>
            (en construcción).
        </p>
        <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
            @storeCan($storeRef, 'contabilidad.cuentas.view')
                <a href="{{ route('stores.contabilidad.cuentas', $storeRef) }}" wire:navigate
                   class="underline hover:text-white">Plan de cuentas</a>
            @endstoreCan
            @if(\Illuminate\Support\Facades\Route::has('stores.contabilidad.formas-pago'))
                @storeCan($storeRef, 'contabilidad.formas-pago.view')
                    <a href="{{ route('stores.contabilidad.formas-pago', $storeRef) }}" wire:navigate
                       class="underline hover:text-white">Formas de pago</a>
                @endstoreCan
            @endif
        </p>
    </div>
@endif
