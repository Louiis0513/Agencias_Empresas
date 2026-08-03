<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Plan de cuentas — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="cuentaArbol({
        nodosRaiz: @js($nodosRaiz ?? []),
        hijosUrlTpl: @js(url('/stores/'.$store->slug.'/contabilidad/cuentas').'/__ID__/hijos'),
        editUrlTpl: @js(url('/stores/'.$store->slug.'/contabilidad/cuentas').'/__ID__'),
    })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="bg-dark-card border border-white/5 rounded-xl p-4">
                    <div class="text-xs text-gray-400 uppercase">Total</div>
                    <div class="text-2xl font-semibold text-gray-100">{{ $stats['total'] }}</div>
                </div>
                <div class="bg-dark-card border border-white/5 rounded-xl p-4">
                    <div class="text-xs text-gray-400 uppercase">Base (≤6)</div>
                    <div class="text-2xl font-semibold text-gray-100">{{ $stats['base'] }}</div>
                </div>
                <div class="bg-dark-card border border-white/5 rounded-xl p-4">
                    <div class="text-xs text-gray-400 uppercase">Auxiliares</div>
                    <div class="text-2xl font-semibold text-gray-100">{{ $stats['auxiliares'] }}</div>
                </div>
                <div class="bg-dark-card border border-white/5 rounded-xl p-4">
                    <div class="text-xs text-gray-400 uppercase">Transaccionales</div>
                    <div class="text-2xl font-semibold text-gray-100">{{ $stats['transaccionales'] }}</div>
                </div>
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.contabilidad.cuentas', $store) }}" class="flex flex-wrap gap-2 items-end">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Código o nombre..."
                                   class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <select name="clase" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todas las clases</option>
                                @foreach($clases as $clase)
                                    <option value="{{ $clase }}" @selected(request('clase') === $clase)>{{ $clase }}</option>
                                @endforeach
                            </select>
                            <select name="es_auxiliar" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Base y auxiliares</option>
                                <option value="0" @selected(request('es_auxiliar') === '0')>Solo base</option>
                                <option value="1" @selected(request('es_auxiliar') === '1')>Solo auxiliares</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Buscar</button>
                            @if(request()->anyFilled(['search', 'clase', 'es_auxiliar']))
                                <a href="{{ route('stores.contabilidad.cuentas', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Ver árbol</a>
                            @endif
                        </form>

                        <div class="flex flex-wrap gap-2">
                            @storeCan($store, 'contabilidad.cuentas.import')
                            <form method="POST" action="{{ route('stores.contabilidad.cuentas.importar', $store) }}"
                                  onsubmit="return confirm('¿Importar PUC base (sin auxiliares) desde el Excel? No sobrescribe auxiliares manuales.');">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-xs font-semibold uppercase tracking-wider rounded-xl hover:bg-emerald-700">
                                    Importar PUC base
                                </button>
                            </form>
                            @endstoreCan
                        </div>
                    </div>

                    @if(!$modoBusqueda)
                        <p class="text-sm text-gray-400 mb-4">
                            Despliega cada nivel (Clase → Grupo → Cuenta → Subcuenta → Auxiliar → Subauxiliar).
                            Usa <span class="text-gray-200">+</span> en la fila para crear un hijo.
                        </p>
                    @else
                        <p class="text-sm text-gray-400 mb-4">
                            Resultados de búsqueda. <a href="{{ route('stores.contabilidad.cuentas', $store) }}" class="text-brand hover:underline">Volver al árbol</a>.
                        </p>
                    @endif

                    {{-- Árbol --}}
                    @if(!$modoBusqueda)
                        @if(count($nodosRaiz) > 0)
                            <div class="border border-white/5 rounded-xl overflow-hidden">
                                <div class="hidden sm:grid grid-cols-12 gap-2 px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide border-b border-white/5 bg-white/[0.02]">
                                    <div class="col-span-5">Cuenta</div>
                                    <div class="col-span-2">Nivel</div>
                                    <div class="col-span-2">Usado en</div>
                                    <div class="col-span-1">Estado</div>
                                    <div class="col-span-2 text-right">Acciones</div>
                                </div>
                                <template x-for="(row, index) in filas" :key="row.id + '-' + row.depth">
                                    <div class="grid grid-cols-12 gap-2 px-3 py-2.5 items-center border-b border-white/5 hover:bg-white/[0.03] text-sm"
                                         :class="row.depth === 0 ? 'bg-white/[0.02]' : ''">
                                        <div class="col-span-12 sm:col-span-5 flex items-center gap-1 min-w-0">
                                            <span class="shrink-0" :style="'width:' + (row.depth * 1.1) + 'rem'"></span>
                                            <button type="button"
                                                    class="w-6 h-6 shrink-0 flex items-center justify-center rounded text-gray-400 hover:text-gray-100 hover:bg-white/10 disabled:opacity-30"
                                                    :disabled="!row.tiene_hijos || row.loading"
                                                    @click="toggle(row, index)"
                                                    :title="row.tiene_hijos ? (row.open ? 'Contraer' : 'Expandir') : 'Sin hijos'">
                                                <template x-if="row.loading">
                                                    <span class="text-xs">…</span>
                                                </template>
                                                <template x-if="!row.loading && row.tiene_hijos">
                                                    <svg class="w-3.5 h-3.5 transition-transform" :class="row.open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </template>
                                                <template x-if="!row.loading && !row.tiene_hijos">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-white/20"></span>
                                                </template>
                                            </button>
                                            <span class="font-mono text-brand shrink-0" x-text="row.codigo"></span>
                                            <span class="text-gray-200 truncate" x-text="row.nombre" :title="row.nombre"></span>
                                        </div>
                                        <div class="col-span-6 sm:col-span-2 flex flex-wrap gap-1">
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-700/80 text-gray-300" x-text="row.nivel_label"></span>
                                            <template x-if="row.nivel_agrupacion === 'Transaccional'">
                                                <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-900/40 text-emerald-200">Tx</span>
                                            </template>
                                        </div>
                                        <div class="col-span-6 sm:col-span-2 text-xs text-gray-500 truncate">
                                            <template x-if="row.usos && row.usos.length">
                                                <span x-text="row.usos.join(' · ')"></span>
                                            </template>
                                            <template x-if="!row.usos || !row.usos.length">
                                                <span>—</span>
                                            </template>
                                        </div>
                                        <div class="col-span-4 sm:col-span-1 text-xs">
                                            <span x-show="row.activo" class="text-emerald-400">Activa</span>
                                            <span x-show="!row.activo" class="text-gray-500">Inactiva</span>
                                        </div>
                                        <div class="col-span-8 sm:col-span-2 text-right space-x-2 whitespace-nowrap">
                                            @storeCan($store, 'contabilidad.cuentas.create')
                                            <button type="button"
                                                    class="text-emerald-400 hover:underline text-xs"
                                                    x-show="row.meta && row.meta.puede"
                                                    @click="openHijo(row)">
                                                + <span x-text="row.meta.accion"></span>
                                            </button>
                                            @endstoreCan
                                            @storeCan($store, 'contabilidad.cuentas.edit')
                                            <button type="button"
                                                    class="text-brand hover:underline text-xs"
                                                    @click="openEdit(row)">
                                                Editar
                                            </button>
                                            @endstoreCan
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <p class="text-gray-400 mb-4">No hay cuentas contables para esta tienda.</p>
                                @storeCan($store, 'contabilidad.cuentas.import')
                                <form method="POST" action="{{ route('stores.contabilidad.cuentas.importar', $store) }}">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Importar PUC base ahora</button>
                                </form>
                                @endstoreCan
                            </div>
                        @endif
                    @else
                        {{-- Lista plana (búsqueda) --}}
                        @if($cuentas && $cuentas->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-white/5">
                                    <thead class="border-b border-white/5">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Clase</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Usado en</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach($cuentas as $cuenta)
                                            @php
                                                $usos = $usosPorCuenta[$cuenta->id] ?? [];
                                                $meta = $metaHijos[$cuenta->id] ?? null;
                                            @endphp
                                            <tr class="hover:bg-white/5">
                                                <td class="px-4 py-3 text-sm font-mono text-gray-100">{{ $cuenta->codigo }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-200">{{ $cuenta->nombre }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-400">{{ $cuenta->clase }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-400">
                                                    @forelse($usos as $uso)
                                                        @php $et = is_array($uso) ? ($uso['etiqueta'] ?? '') : (string) $uso; @endphp
                                                        @if($et !== '')
                                                            <span class="px-2 py-0.5 text-xs rounded-full bg-sky-900/40 text-sky-200">{{ $et }}</span>
                                                        @endif
                                                    @empty
                                                        <span class="text-gray-600">—</span>
                                                    @endforelse
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm space-x-2">
                                                    @storeCan($store, 'contabilidad.cuentas.create')
                                                        @if($meta && ($meta['puede'] ?? false))
                                                            <button type="button" class="text-emerald-400 hover:underline"
                                                                @click="openHijo({ id: {{ $cuenta->id }}, codigo: @js($cuenta->codigo), nombre: @js($cuenta->nombre), meta: @js($meta) })">
                                                                + {{ $meta['accion'] }}
                                                            </button>
                                                        @endif
                                                    @endstoreCan
                                                    @storeCan($store, 'contabilidad.cuentas.edit')
                                                    <button type="button" class="text-brand hover:underline"
                                                        @click="openEdit({ id: {{ $cuenta->id }}, nombre: @js($cuenta->nombre), activo: {{ $cuenta->activo ? 'true' : 'false' }} })">
                                                        Editar
                                                    </button>
                                                    @endstoreCan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4">{{ $cuentas->links() }}</div>
                        @else
                            <div class="text-center py-12 text-gray-400">Sin resultados para estos filtros.</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Modal crear hijo --}}
        <div x-show="showHijo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showHijo = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-1">
                    Crear <span x-text="hijoAccion"></span>
                </h3>
                <p class="text-sm text-gray-400 mb-4">
                    Bajo <span class="font-mono text-brand" x-text="hijoPadreCodigo"></span>
                    — <span x-text="hijoPadreNombre"></span>
                </p>
                <form method="POST" action="{{ route('stores.contabilidad.cuentas.hijos', $store) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="cuenta_padre_id" :value="hijoPadreId">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Prefijo</label>
                            <input type="text" :value="hijoPrefijo" readonly
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-400 font-mono cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">
                                Sufijo (<span x-text="hijoDigitosSufijo === 1 ? '1 dígito' : '2 dígitos'"></span>)
                            </label>
                            <input type="text" name="sufijo" x-model="hijoSufijo" :maxlength="hijoDigitosSufijo" required
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 font-mono"
                                   :placeholder="hijoDigitosSufijo === 1 ? '1' : '01'">
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Código final:
                                <span class="font-mono text-brand"
                                      x-text="hijoPrefijo + String(hijoSufijo || '').padStart(hijoDigitosSufijo, '0')"></span>
                            </p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="nombre" x-model="hijoNombre" required maxlength="255"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                               placeholder="Nombre de la cuenta">
                    </div>
                    <template x-if="hijoEsTransaccional">
                        <div class="space-y-4 border-t border-white/10 pt-4">
                            <p class="text-sm font-medium text-gray-200">Característica transaccional</p>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Categoría</label>
                                <select name="categoria" x-model="hijoCategoria"
                                        class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                    <option value="">—</option>
                                    @foreach($categoriasSugeridas as $cat)
                                        <option value="{{ $cat }}">{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Maneja vencimientos</label>
                                <select name="maneja_vencimientos" x-model="hijoVencimientos"
                                        class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                    <option value="No maneja vencimiento">No maneja vencimiento</option>
                                    <option value="Con detalle de vencimientos">Con detalle de vencimientos</option>
                                </select>
                            </div>
                            <input type="hidden" name="nivel_agrupacion" value="Transaccional">
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                                    <input type="hidden" name="diferencia_fiscal" value="0">
                                    <input type="checkbox" name="diferencia_fiscal" value="1" x-model="hijoDiferenciaFiscal"
                                           class="rounded border-white/20 bg-white/5">
                                    Diferencia fiscal / NIIF
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                                    <input type="hidden" name="activo" value="0">
                                    <input type="checkbox" name="activo" value="1" x-model="hijoActivo"
                                           class="rounded border-white/20 bg-white/5">
                                    Activa
                                </label>
                            </div>
                        </div>
                    </template>
                    <template x-if="hijoTieneUsos">
                        <div class="rounded-xl border border-amber-500/40 bg-amber-950/40 px-4 py-3 text-sm text-amber-100">
                            <p class="font-medium text-amber-50">El padre posee movimiento o vínculos</p>
                            <p class="mt-1">Se trasladarán al primer hijo. ¿Confirmas?</p>
                            <label class="mt-3 inline-flex items-center gap-2">
                                <input type="hidden" name="confirmar_traslado" value="0">
                                <input type="checkbox" name="confirmar_traslado" value="1" x-model="hijoConfirmarTraslado"
                                       class="rounded border-white/20 bg-white/5" required>
                                Confirmo el traslado al nuevo código
                            </label>
                        </div>
                    </template>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showHijo = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl"
                                :disabled="hijoTieneUsos && !hijoConfirmarTraslado">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal editar --}}
        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showEdit = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-md p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Editar cuenta</h3>
                <form method="POST" :action="editUrlTpl.replace('__ID__', editId)" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="nombre" x-model="editNombre" required
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" name="activo" value="1" x-model="editActivo" class="rounded border-white/20 bg-white/5">
                        Activa
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEdit = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function cuentaArbol(cfg) {
            return {
                showHijo: false,
                showEdit: false,
                editId: null,
                editNombre: '',
                editActivo: true,
                hijoPadreId: '',
                hijoPadreCodigo: '',
                hijoPadreNombre: '',
                hijoAccion: '',
                hijoPrefijo: '',
                hijoSufijo: '',
                hijoDigitosSufijo: 2,
                hijoNombre: '',
                hijoTieneUsos: false,
                hijoConfirmarTraslado: false,
                hijoEsTransaccional: false,
                hijoCategoria: '',
                hijoVencimientos: 'No maneja vencimiento',
                hijoActivo: true,
                hijoDiferenciaFiscal: false,
                hijosUrlTpl: cfg.hijosUrlTpl,
                editUrlTpl: cfg.editUrlTpl,
                filas: [],
                init() {
                    this.filas = (cfg.nodosRaiz || []).map((n) => ({
                        ...n,
                        depth: 0,
                        open: false,
                        loading: false,
                    }));
                },
                async toggle(row, index) {
                    if (!row.tiene_hijos || row.loading) return;
                    if (row.open) {
                        let end = index + 1;
                        while (end < this.filas.length && this.filas[end].depth > row.depth) {
                            end++;
                        }
                        this.filas.splice(index + 1, end - index - 1);
                        row.open = false;
                        return;
                    }
                    row.loading = true;
                    try {
                        const url = this.hijosUrlTpl.replace('__ID__', String(row.id));
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        if (!res.ok) throw new Error('No se pudieron cargar los hijos');
                        const hijos = await res.json();
                        const mapped = (Array.isArray(hijos) ? hijos : []).map((h) => ({
                            ...h,
                            depth: row.depth + 1,
                            open: false,
                            loading: false,
                        }));
                        this.filas.splice(index + 1, 0, ...mapped);
                        row.open = true;
                        if (mapped.length === 0) {
                            row.tiene_hijos = false;
                        }
                    } catch (e) {
                        console.error(e);
                        alert(e.message || 'Error al expandir');
                    } finally {
                        row.loading = false;
                    }
                },
                openHijo(row) {
                    const meta = row.meta || {};
                    if (!meta.puede) return;
                    this.hijoPadreId = String(row.id);
                    this.hijoPadreCodigo = row.codigo;
                    this.hijoPadreNombre = row.nombre;
                    this.hijoAccion = meta.accion || 'hijo';
                    this.hijoPrefijo = row.codigo;
                    this.hijoSufijo = meta.sufijo_sugerido || '';
                    this.hijoDigitosSufijo = meta.digitos_sufijo || 2;
                    this.hijoNombre = '';
                    this.hijoTieneUsos = !!meta.tiene_usos;
                    this.hijoConfirmarTraslado = false;
                    this.hijoEsTransaccional = (meta.longitud_hijo || 0) >= 8;
                    this.hijoCategoria = '';
                    this.hijoVencimientos = 'No maneja vencimiento';
                    this.hijoActivo = true;
                    this.hijoDiferenciaFiscal = false;
                    this.showHijo = true;
                },
                openEdit(row) {
                    this.editId = row.id;
                    this.editNombre = row.nombre;
                    this.editActivo = !!row.activo;
                    this.showEdit = true;
                },
            };
        }
    </script>
</x-app-layout>
