<?php
    $editando = old('_edit_id');
    $cuentaLabel = fn ($cuenta) => $cuenta
        ? $cuenta->codigo.' — '.$cuenta->nombre
        : '—';
?>
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Tipos de recibo de caja — {{ $store->name }}
            </h2>
            <div class="flex items-center gap-4">
                @storeCan($store, 'contabilidad.tipos.view')
                <a href="{{ route('stores.contabilidad.tipos', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    Comprobantes contables
                </a>
                @endstoreCan
                @storeCan($store, 'comprobantes-ingreso.view')
                <a href="{{ route('stores.comprobantes-ingreso.index', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    Comprobantes de ingreso
                </a>
                @endstoreCan
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        showCreate: {{ $errors->any() && ! $editando ? 'true' : 'false' }},
        showEdit: {{ $editando ? 'true' : 'false' }},
        editId: {{ $editando ? (int) $editando : 'null' }},
        editCodigo: @js(old('codigo', '')),
        editTitulo: @js(old('titulo', '')),
        editPrefijo: @js(old('prefijo', 'RC')),
        editSiguiente: {{ (int) old('siguiente_numero', 1) }},
        editCuentaAnticipos: @js(old('cuenta_anticipos_id', '') !== null && old('cuenta_anticipos_id', '') !== '' ? (string) old('cuenta_anticipos_id') : ''),
        editNumeracion: {{ old('numeracion_automatica', true) ? 'true' : 'false' }},
        editCentroCostos: {{ old('maneja_centro_costos', false) ? 'true' : 'false' }},
        editCentroObligatorio: {{ old('centro_costo_obligatorio', false) ? 'true' : 'false' }},
        editActivo: {{ old('activo', true) ? 'true' : 'false' }},
        openEdit(t) {
            this.editId = t.id;
            this.editCodigo = t.codigo;
            this.editTitulo = t.titulo || t.nombre || '';
            this.editPrefijo = t.prefijo || 'RC';
            this.editSiguiente = t.siguiente_numero;
            this.editCuentaAnticipos = t.cuenta_anticipos_id ? String(t.cuenta_anticipos_id) : '';
            this.editNumeracion = !!t.numeracion_automatica;
            this.editCentroCostos = !!t.maneja_centro_costos;
            this.editCentroObligatorio = !!t.centro_costo_obligatorio;
            this.editActivo = !!t.activo;
            this.showEdit = true;
        }
    }">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-xl border border-sky-500/30 bg-sky-950/40 px-4 py-3 text-sm text-sky-100">
                Configuración de tipos <strong>RC</strong> (recibos de caja), estilo Siigo.
                Cada código tiene su propio consecutivo. La cuenta de anticipos se usará cuando se cablee el cobro adelantado.
                Aún no reemplaza la numeración de los comprobantes de ingreso operativos (<code class="text-xs">CI-</code>).
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.contabilidad.recibos-caja', $store) }}" class="flex flex-wrap gap-2 items-end">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Buscar código o título..."
                                   class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <select name="activo" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('activo') === '1')>En uso</option>
                                <option value="0" @selected(request('activo') === '0')>Inactivos</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                            @if(request()->anyFilled(['search', 'activo']))
                                <a href="{{ route('stores.contabilidad.recibos-caja', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
                            @endif
                        </form>

                        @storeCan($store, 'contabilidad.tipos.create')
                        <button type="button" @click="showCreate = true"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl">
                            Crear nuevo tipo de comprobante
                        </button>
                        @endstoreCan
                    </div>

                    @if($tipos->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="bg-brand/20 border-b border-white/10">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wide">Código</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wide">Título</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-300 uppercase">Anticipos</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-300 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-300 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($tipos as $tipo)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-3 text-sm font-mono text-gray-100">{{ $tipo->codigo }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-100">{{ $tipo->titulo ?: $tipo->nombre }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-400 font-mono text-xs">{{ $cuentaLabel($tipo->cuentaAnticipos) }}</td>
                                            <td class="px-4 py-3 text-sm">
                                                @if($tipo->activo)
                                                    <span class="text-emerald-400">En uso</span>
                                                @else
                                                    <span class="text-red-400">Inactivo</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                @storeCan($store, 'contabilidad.tipos.edit')
                                                <button type="button"
                                                    class="text-brand hover:underline"
                                                    @click="openEdit({
                                                        id: {{ $tipo->id }},
                                                        codigo: @js($tipo->codigo),
                                                        titulo: @js($tipo->titulo ?: $tipo->nombre),
                                                        prefijo: @js($tipo->prefijo),
                                                        siguiente_numero: {{ $tipo->siguiente_numero }},
                                                        cuenta_anticipos_id: {{ $tipo->cuenta_anticipos_id ? (int) $tipo->cuenta_anticipos_id : 'null' }},
                                                        numeracion_automatica: {{ $tipo->numeracion_automatica ? 'true' : 'false' }},
                                                        maneja_centro_costos: {{ $tipo->maneja_centro_costos ? 'true' : 'false' }},
                                                        centro_costo_obligatorio: {{ $tipo->centro_costo_obligatorio ? 'true' : 'false' }},
                                                        activo: {{ $tipo->activo ? 'true' : 'false' }}
                                                    })">
                                                    Editar
                                                </button>
                                                @endstoreCan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $tipos->links() }}
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-400">
                            <p class="mb-2">Aún no hay tipos de recibo de caja.</p>
                            <p class="text-sm">Al abrir esta pantalla se crea RC-1 automáticamente.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showCreate = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Crear nuevo tipo de recibo de caja</h3>
                <form method="POST" action="{{ route('stores.contabilidad.recibos-caja.store', $store) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="familia" value="RC">
                    <input type="hidden" name="prefijo" value="RC">
                    @include('stores.contabilidad.partials.tipo-recibo-caja-form', [
                        'codigoDefault' => old('codigo', $codigoSugerido),
                        'tituloDefault' => old('titulo', 'Recibo de caja'),
                        'siguienteDefault' => old('siguiente_numero', 1),
                        'cuentaAnticiposDefault' => old('cuenta_anticipos_id'),
                        'numeracionDefault' => old('numeracion_automatica', true),
                        'centroCostosDefault' => old('maneja_centro_costos', false),
                        'centroObligatorioDefault' => old('centro_costo_obligatorio', false),
                        'activoDefault' => old('activo', true),
                        'cuentasAnticipos' => $cuentasAnticipos,
                        'alpine' => false,
                    ])
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showEdit = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Editar recibo de caja</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/contabilidad/recibos-caja') }}/' + editId" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_id" :value="editId">
                    <input type="hidden" name="familia" value="RC">
                    <input type="hidden" name="prefijo" x-model="editPrefijo">
                    @include('stores.contabilidad.partials.tipo-recibo-caja-form', [
                        'codigoDefault' => '',
                        'tituloDefault' => '',
                        'siguienteDefault' => 1,
                        'cuentaAnticiposDefault' => null,
                        'numeracionDefault' => true,
                        'centroCostosDefault' => false,
                        'centroObligatorioDefault' => false,
                        'activoDefault' => true,
                        'cuentasAnticipos' => $cuentasAnticipos,
                        'alpine' => true,
                    ])
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEdit = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
