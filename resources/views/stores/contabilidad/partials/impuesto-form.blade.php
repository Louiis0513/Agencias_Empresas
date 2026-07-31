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
        <label class="mb-1 block text-sm text-gray-400">Tarifa (%)</label>
        <input type="number" min="0" step="0.0001" name="tarifa" required
               @if($useAlpineEdit) x-model="editTarifa" @else value="{{ $tarifaDefault }}" @endif
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    </div>
</div>

<div>
    <label class="mb-1 block text-sm text-gray-400">Nombre</label>
    <input type="text" name="nombre" required maxlength="255"
           @if($useAlpineEdit) x-model="editNombre" @else value="{{ $nombreDefault }}" @endif
           class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
           placeholder="Ej. IVA 19%">
</div>

<div>
    <label class="mb-1 block text-sm text-gray-400">Tipo de impuesto</label>
    <select name="tipo" required
            @if($useAlpineEdit) x-model="editTipo" @endif
            class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
        @foreach($tipos as $tipo)
            <option value="{{ $tipo }}" @selected(! $useAlpineEdit && (string) $tipoDefault === (string) $tipo)>{{ $tipo }}</option>
        @endforeach
    </select>
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
        <input type="hidden" name="por_valor" value="0">
        <input type="checkbox" name="por_valor" value="1"
               @if($useAlpineEdit) x-model="editPorValor" @elseif($porValorDefault) checked @endif
               class="rounded border-white/20 bg-white/5 text-brand">
        Por valor
    </label>
</div>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label class="mb-1 block text-sm text-gray-400">Cuenta ventas</label>
        <select name="cuenta_ventas_id" required
                @if($useAlpineEdit) x-model="editVentas" @endif
                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="">Selecciona…</option>
            @foreach($cuentas as $cuenta)
                <option value="{{ $cuenta->id }}" @selected(! $useAlpineEdit && (string) $ventasDefault === (string) $cuenta->id)>
                    {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm text-gray-400">Cuenta compras</label>
        <select name="cuenta_compras_id" required
                @if($useAlpineEdit) x-model="editCompras" @endif
                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="">Selecciona…</option>
            @foreach($cuentas as $cuenta)
                <option value="{{ $cuenta->id }}" @selected(! $useAlpineEdit && (string) $comprasDefault === (string) $cuenta->id)>
                    {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm text-gray-400">Devolución ventas</label>
        <select name="cuenta_devolucion_ventas_id" required
                @if($useAlpineEdit) x-model="editDevVentas" @endif
                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="">Selecciona…</option>
            @foreach($cuentas as $cuenta)
                <option value="{{ $cuenta->id }}" @selected(! $useAlpineEdit && (string) $devVentasDefault === (string) $cuenta->id)>
                    {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-sm text-gray-400">Devolución compras</label>
        <select name="cuenta_devolucion_compras_id" required
                @if($useAlpineEdit) x-model="editDevCompras" @endif
                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="">Selecciona…</option>
            @foreach($cuentas as $cuenta)
                <option value="{{ $cuenta->id }}" @selected(! $useAlpineEdit && (string) $devComprasDefault === (string) $cuenta->id)>
                    {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
