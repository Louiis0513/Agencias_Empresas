<div>
    <label class="block text-sm text-gray-400 mb-1">Familia</label>
    <select name="familia" x-model="{{ $familiaModel }}" required class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
        @foreach($familias as $key => $label)
            <option value="{{ $key }}" @selected((string) ($familiaDefault ?? '') === (string) $key)>{{ $key }} — {{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm text-gray-400 mb-1">Código</label>
        <input type="text" name="codigo" value="{{ $codigoDefault }}"
               placeholder="Automático si lo dejas vacío"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    </div>
    <div>
        <label class="block text-sm text-gray-400 mb-1">Prefijo</label>
        <input type="text" name="prefijo" value="{{ $prefijoDefault }}"
               placeholder="Ej. FV, RC"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 uppercase">
    </div>
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Nombre</label>
    <input type="text" name="nombre" value="{{ $nombreDefault }}" required
           placeholder="Ej. Factura de venta"
           class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Título (impresión)</label>
    <input type="text" name="titulo" value="{{ $tituloDefault }}"
           placeholder="Igual al nombre si lo dejas vacío"
           class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm text-gray-400 mb-1">Siguiente número</label>
        <input type="number" name="siguiente_numero" min="1" value="{{ $siguienteDefault }}"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    </div>
    <div>
        <label class="block text-sm text-gray-400 mb-1">Libro oficial</label>
        <select name="libro_oficial" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="" @selected(($libroDefault ?? '') === '')>No aplica</option>
            @foreach(\App\Models\TipoComprobante::etiquetasLibrosOficiales() as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected(($libroDefault ?? '') === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="flex flex-wrap gap-4 pt-1">
    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="numeracion_automatica" value="0">
        <input type="checkbox" name="numeracion_automatica" value="1" @checked($numeracionDefault) class="rounded border-white/20 bg-white/5">
        Numeración automática
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="maneja_centro_costos" value="0">
        <input type="checkbox" name="maneja_centro_costos" value="1" @checked($centroCostosDefault) class="rounded border-white/20 bg-white/5">
        Maneja centro de costos
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" value="1" @checked($activoDefault) class="rounded border-white/20 bg-white/5">
        Activo
    </label>
</div>
