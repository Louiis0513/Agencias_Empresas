<div>
    <label class="block text-sm text-gray-400 mb-1">Código del comprobante</label>
    @if($alpine ?? false)
        <input type="text" name="codigo" x-model="editCodigo" required
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 font-mono uppercase"
               placeholder="Ej. 1, 2">
    @else
        <input type="text" name="codigo" value="{{ $codigoDefault }}"
               placeholder="Automático si lo dejas vacío"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 font-mono uppercase">
    @endif
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Título del comprobante</label>
    @if($alpine ?? false)
        <input type="text" name="titulo" x-model="editTitulo" required
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
               placeholder="Ej. Recibo de caja">
    @else
        <input type="text" name="titulo" value="{{ $tituloDefault }}" required
               placeholder="Ej. Recibo de caja"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    @endif
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Consecutivo</label>
    @if($alpine ?? false)
        <input type="number" name="siguiente_numero" min="1" x-model="editSiguiente"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    @else
        <input type="number" name="siguiente_numero" min="1" value="{{ $siguienteDefault }}"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    @endif
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Cuenta contable de anticipos</label>
    @if($alpine ?? false)
        <select name="cuenta_anticipos_id" x-model="editCuentaAnticipos" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="">Sin asignar (opcional)</option>
            @foreach($cuentasAnticipos as $cuenta)
                <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
            @endforeach
        </select>
    @else
        <select name="cuenta_anticipos_id" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="" @selected(($cuentaAnticiposDefault ?? '') === '' || ($cuentaAnticiposDefault ?? null) === null)>Sin asignar (opcional)</option>
            @foreach($cuentasAnticipos as $cuenta)
                <option value="{{ $cuenta->id }}" @selected((string) ($cuentaAnticiposDefault ?? '') === (string) $cuenta->id)>
                    {{ $cuenta->codigo }} — {{ $cuenta->nombre }}
                </option>
            @endforeach
        </select>
    @endif
    <p class="mt-1 text-xs text-gray-500">Para cuando el cliente paga adelantado (antes de facturar). Estilo Siigo.</p>
</div>
<div class="flex flex-wrap gap-4 pt-1">
    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="numeracion_automatica" value="0">
        @if($alpine ?? false)
            <input type="checkbox" name="numeracion_automatica" value="1" x-model="editNumeracion" class="rounded border-white/20 bg-white/5">
        @else
            <input type="checkbox" name="numeracion_automatica" value="1" @checked($numeracionDefault) class="rounded border-white/20 bg-white/5">
        @endif
        Numeración automática
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="maneja_centro_costos" value="0">
        @if($alpine ?? false)
            <input type="checkbox" name="maneja_centro_costos" value="1" x-model="editCentroCostos" class="rounded border-white/20 bg-white/5">
        @else
            <input type="checkbox" name="maneja_centro_costos" value="1" @checked($centroCostosDefault) class="rounded border-white/20 bg-white/5">
        @endif
        Maneja centro de costos
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="centro_costo_obligatorio" value="0">
        @if($alpine ?? false)
            <input type="checkbox" name="centro_costo_obligatorio" value="1" x-model="editCentroObligatorio" class="rounded border-white/20 bg-white/5">
        @else
            <input type="checkbox" name="centro_costo_obligatorio" value="1" @checked($centroObligatorioDefault ?? false) class="rounded border-white/20 bg-white/5">
        @endif
        Centro de costos obligatorio
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="activo" value="0">
        @if($alpine ?? false)
            <input type="checkbox" name="activo" value="1" x-model="editActivo" class="rounded border-white/20 bg-white/5">
        @else
            <input type="checkbox" name="activo" value="1" @checked($activoDefault) class="rounded border-white/20 bg-white/5">
        @endif
        En uso
    </label>
</div>
