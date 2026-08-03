@php
    $useAlpineEdit = $useAlpineEdit ?? false;
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm text-gray-400">Código</label>
        <input type="number" min="1" name="codigo" required
               @if($useAlpineEdit) x-model="editCodigo" @else value="{{ $codigoDefault }}" @endif
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    </div>
    <div>
        <label class="mb-1 block text-sm text-gray-400">Aplica a</label>
        <select name="aplica_a" required
                @if($useAlpineEdit) x-model="editAplicaA" @endif
                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            @foreach($aplicaAOptions as $value => $label)
                <option value="{{ $value }}" @selected(! $useAlpineEdit && (string) $aplicaADefault === (string) $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div>
    <label class="mb-1 block text-sm text-gray-400">Nombre</label>
    <input type="text" name="nombre" required maxlength="255"
           @if($useAlpineEdit) x-model="editNombre" @else value="{{ $nombreDefault }}" @endif
           class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
           placeholder="Ej. Efectivo, Transferencia Bancolombia">
</div>

<div>
    <label class="mb-1 block text-sm text-gray-400">Cuenta contable</label>
    <select name="cuenta_contable_id" required
            @if($useAlpineEdit) x-model="editCuenta" @endif
            class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
        <option value="">Selecciona…</option>
        @foreach($cuentas as $cuenta)
            <option value="{{ $cuenta->id }}" @selected(! $useAlpineEdit && (string) $cuentaDefault === (string) $cuenta->id)>
                {{ $cuenta->codigo }} — {{ $cuenta->nombre }}@if($cuenta->categoria) ({{ $cuenta->categoria }})@endif
            </option>
        @endforeach
    </select>
</div>

<div>
    <label class="mb-1 block text-sm text-gray-400">Medio de pago D. Electrónico (DIAN)</label>
    <select name="medio_pago_dian"
            @if($useAlpineEdit) x-model="editMedioDian" @endif
            class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
        <option value="">—</option>
        @foreach($mediosDian as $codigo => $desc)
            <option value="{{ $codigo }}" @selected(! $useAlpineEdit && (string) $medioDefault === (string) $codigo)>
                {{ $codigo }} — {{ $desc }}
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">Catálogo fijo DIAN para documentos electrónicos (PaymentMeansCode).</p>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <label class="flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="en_uso" value="0">
        <input type="checkbox" name="en_uso" value="1"
               @if($useAlpineEdit) x-model="editEnUso" @elseif($enUsoDefault) checked @endif
               class="rounded border-white/20 bg-white/5 text-brand">
        En uso
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="es_pago_en_linea" value="0">
        <input type="checkbox" name="es_pago_en_linea" value="1"
               @if($useAlpineEdit) x-model="editPagoLinea" @elseif($pagoLineaDefault ?? false) checked @endif
               class="rounded border-white/20 bg-white/5 text-brand">
        Pago en línea
    </label>
</div>
