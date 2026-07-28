<div>
    <label class="block text-sm text-gray-400 mb-1">Código</label>
    <input type="text" name="codigo" value="{{ $codigoDefault }}"
           placeholder="Automático si lo dejas vacío"
           class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Nombre</label>
    <input type="text" name="nombre" value="{{ $nombreDefault }}" required
           placeholder="Ej. Productos"
           class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Tipo</label>
    <select name="tipo" x-model="{{ $tipoModel }}" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
        <option value="producto">Producto</option>
        <option value="servicio">Servicio</option>
    </select>
</div>

<div class="space-y-4">
    <div>
        <label class="block text-sm text-gray-400 mb-1">Cuenta inventarios</label>
        <select name="cuenta_inventario_id"
                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="">Selecciona…</option>
            @foreach($cuentas['inventario'] as $c)
                <option value="{{ $c->id }}" @selected((string) $inventarioDefault === (string) $c->id)>
                    {{ $c->codigo }} — {{ $c->nombre }}
                </option>
            @endforeach
        </select>
        @if($cuentas['inventario']->isEmpty())
            <p class="text-xs text-amber-400 mt-1">Crea primero una auxiliar bajo 143501 (Inventarios).</p>
        @endif
    </div>
    <div>
        <label class="block text-sm text-gray-400 mb-1">Cuenta costo de ventas</label>
        <select name="cuenta_costo_id"
                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
            <option value="">Selecciona…</option>
            @foreach($cuentas['costo'] as $c)
                <option value="{{ $c->id }}" @selected((string) $costoDefault === (string) $c->id)>
                    {{ $c->codigo }} — {{ $c->nombre }}
                </option>
            @endforeach
        </select>
        @if($cuentas['costo']->isEmpty())
            <p class="text-xs text-amber-400 mt-1">Crea primero una auxiliar bajo 613505 (Costo de ventas).</p>
        @endif
    </div>
</div>

<div>
    <label class="block text-sm text-gray-400 mb-1">Cuenta de ingreso</label>
    <select name="cuenta_ingreso_id" required class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
        <option value="">Selecciona…</option>
        @foreach($cuentas['ingreso'] as $c)
            <option value="{{ $c->id }}" @selected((string) $ingresoDefault === (string) $c->id)>
                {{ $c->codigo }} — {{ $c->nombre }}
            </option>
        @endforeach
    </select>
    @if($cuentas['ingreso']->isEmpty())
        <p class="text-xs text-amber-400 mt-1">Crea primero una auxiliar bajo 413501 (Ingresos).</p>
    @endif
</div>
<div>
    <label class="block text-sm text-gray-400 mb-1">Cuenta de devoluciones</label>
    <select name="cuenta_devolucion_id" required class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
        <option value="">Selecciona…</option>
        @foreach($cuentas['devolucion'] as $c)
            <option value="{{ $c->id }}" @selected((string) $devolucionDefault === (string) $c->id)>
                {{ $c->codigo }} — {{ $c->nombre }}
            </option>
        @endforeach
    </select>
    @if($cuentas['devolucion']->isEmpty())
        <p class="text-xs text-amber-400 mt-1">Crea primero una auxiliar bajo 417505 (Devoluciones).</p>
    @endif
</div>
<label class="inline-flex items-center gap-2 text-sm text-gray-300">
    <input type="hidden" name="activo" value="0">
    <input type="checkbox" name="activo" value="1" @checked($activoDefault) class="rounded border-white/20 bg-white/5">
    Activa
</label>
