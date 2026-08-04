<div>
    <label class="block text-sm text-gray-400 mb-1">Código del comprobante</label>
    @if($alpine ?? false)
        <input type="text" name="codigo" x-model="editCodigo" required
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 font-mono uppercase"
               placeholder="Ej. 1, 999, ADT">
    @else
        <input type="text" name="codigo" value="{{ $codigoDefault }}"
               placeholder="Automático si lo dejas vacío"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 font-mono uppercase">
    @endif
    <p class="mt-1 text-xs text-gray-500">Número o alfanumérico único dentro de CC.</p>
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Título del comprobante</label>
    @if($alpine ?? false)
        <input type="text" name="titulo" x-model="editTitulo" required
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
               placeholder="Se muestra al imprimir">
    @else
        <input type="text" name="titulo" value="{{ $tituloDefault }}" required
               placeholder="Se muestra al imprimir"
               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
    @endif
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
        <label class="block text-sm text-gray-400 mb-1">Comprobante aplica en libros oficiales como</label>
        @if($alpine ?? false)
            <select name="libro_oficial" x-model="editLibro" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                <option value="">No aplica</option>
                @foreach(\App\Models\TipoComprobante::etiquetasLibrosOficiales() as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        @else
            <select name="libro_oficial" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                <option value="" @selected(($libroDefault ?? '') === '')>No aplica</option>
                @foreach(\App\Models\TipoComprobante::etiquetasLibrosOficiales() as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(($libroDefault ?? '') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        @endif
    </div>
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
