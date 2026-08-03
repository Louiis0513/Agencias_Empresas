<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Centros de costo — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.contabilidad.tipos', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                Tipos de comprobante →
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        tab: @js($tab),
        showCreate: {{ $errors->any() && ! old('_edit_id') && ($tab ?? 'catalogo') === 'catalogo' ? 'true' : 'false' }},
        showEdit: false,
        createEsSubcentro: {{ old('es_subcentro') ? 'true' : 'false' }},
        editId: null,
        editCodigo: '',
        editNombre: '',
        editActivo: true,
        editEsSubcentro: false,
        editParentId: '',
        openEdit(c) {
            this.editId = c.id;
            this.editCodigo = String(c.codigo);
            this.editNombre = c.nombre;
            this.editActivo = !!c.activo;
            this.editEsSubcentro = !!c.parent_id;
            this.editParentId = c.parent_id ? String(c.parent_id) : '';
            this.showEdit = true;
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-950/30 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-xl border border-sky-500/30 bg-sky-950/40 px-4 py-3 text-sm text-sky-100">
                Dimensión analítica estilo Siigo (área, proyecto o departamento) <strong>dentro de la tienda</strong>.
                En <em>Definir comprobantes</em> decides de forma global en qué tipos se maneja, el valor por defecto y si es obligatorio.
            </div>

            {{-- Pestañas estilo Siigo --}}
            <div class="mb-4 flex gap-1 border-b border-white/10">
                <a href="{{ route('stores.contabilidad.centros-costo', $store) }}"
                   class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition
                   {{ $tab === 'catalogo' ? 'bg-dark-card text-white border border-b-0 border-white/10 -mb-px' : 'text-gray-400 hover:text-gray-200' }}">
                    Crear centro de costo
                </a>
                <a href="{{ route('stores.contabilidad.centros-costo', [$store, 'tab' => 'definir']) }}"
                   class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition
                   {{ $tab === 'definir' ? 'bg-dark-card text-white border border-b-0 border-white/10 -mb-px' : 'text-gray-400 hover:text-gray-200' }}">
                    Definir comprobantes
                </a>
            </div>

            @if($tab === 'catalogo')
                <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                    <div class="p-6">
                        <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                            <form method="GET" action="{{ route('stores.contabilidad.centros-costo', $store) }}" class="flex flex-wrap gap-2 items-end">
                                <input type="text" name="search" value="{{ request('search') }}"
                                       placeholder="Buscar código o nombre..."
                                       class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <select name="nivel" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                    <option value="">Todos</option>
                                    <option value="centro" @selected(request('nivel') === 'centro')>Solo centros</option>
                                    <option value="subcentro" @selected(request('nivel') === 'subcentro')>Solo subcentros</option>
                                </select>
                                <select name="activo" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                    <option value="">Activos e inactivos</option>
                                    <option value="1" @selected(request('activo') === '1')>Activos</option>
                                    <option value="0" @selected(request('activo') === '0')>Inactivos</option>
                                </select>
                                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                                @if(request()->anyFilled(['search', 'nivel', 'activo']))
                                    <a href="{{ route('stores.contabilidad.centros-costo', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
                                @endif
                            </form>

                            @storeCan($store, 'contabilidad.centros-costo.create')
                            <button type="button" @click="showCreate = true"
                                    class="inline-flex items-center px-4 py-2 bg-brand text-white text-xs font-semibold uppercase tracking-wider rounded-xl">
                                Crear
                            </button>
                            @endstoreCan
                        </div>

                        @if($items && $items->count())
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-white/5">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nivel</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Padre</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Activo</th>
                                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach($items as $item)
                                            <tr class="hover:bg-white/5 transition text-sm text-gray-300">
                                                <td class="px-3 py-3">
                                                    @if($item->parent_id)
                                                        <span class="px-2 py-0.5 text-xs rounded-full bg-violet-900/40 text-violet-200">Subcentro</span>
                                                    @else
                                                        <span class="px-2 py-0.5 text-xs rounded-full bg-sky-900/40 text-sky-200">Centro</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 font-mono">{{ $item->codigo }}</td>
                                                <td class="px-3 py-3">{{ $item->nombre }}</td>
                                                <td class="px-3 py-3 text-gray-500">
                                                    @if($item->padre)
                                                        {{ $item->padre->codigo }} — {{ $item->padre->nombre }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3">
                                                    @if($item->activo)
                                                        <span class="text-emerald-400">Sí</span>
                                                    @else
                                                        <span class="text-gray-500">No</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 text-right">
                                                    @storeCan($store, 'contabilidad.centros-costo.edit')
                                                    <button type="button" class="text-brand hover:underline text-sm"
                                                        @click="openEdit({
                                                            id: {{ $item->id }},
                                                            codigo: @js($item->codigo),
                                                            nombre: @js($item->nombre),
                                                            activo: {{ $item->activo ? 'true' : 'false' }},
                                                            parent_id: {{ $item->parent_id ? $item->parent_id : 'null' }}
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
                            <div class="mt-4">{{ $items->links() }}</div>
                        @else
                            <div class="text-center py-10 text-gray-400 text-sm">
                                <p class="mb-2">Aún no hay centros de costo.</p>
                                <p>Crea el primero (se generará el subcentro General automáticamente).</p>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                {{-- Definir comprobantes --}}
                <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                    <form method="POST" action="{{ route('stores.contabilidad.centros-costo.definir', $store) }}">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/5 px-6 py-4">
                            <div>
                                <h3 class="font-semibold text-gray-100">Definir comprobantes</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Configuración global de la tienda (no depende del centro que estés creando).</p>
                            </div>
                            @storeCan($store, 'contabilidad.centros-costo.edit')
                            <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl">
                                Guardar
                            </button>
                            @endstoreCan
                        </div>

                        <div class="overflow-x-auto p-6">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase text-gray-400 border-b border-white/10">
                                        <th class="px-3 py-3 min-w-56">Comprobante</th>
                                        <th class="px-3 py-3 w-48 text-center">Maneja centro de costos</th>
                                        <th class="px-3 py-3 min-w-72">Valor por defecto</th>
                                        <th class="px-3 py-3 w-36 text-center">Es obligatorio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $row = 0; @endphp
                                    @forelse($tiposPorFamilia as $familia => $tipos)
                                        <tr>
                                            <td colspan="4" class="px-3 pt-6 pb-2 text-xs font-bold uppercase tracking-wide text-sky-300">
                                                {{ $etiquetasGrupo[$familia] ?? $familia }}
                                            </td>
                                        </tr>
                                        @foreach($tipos as $tipo)
                                            @php
                                                $old = old('tipos.'.$row);
                                                $maneja = isset($old['maneja_centro_costos'])
                                                    ? filter_var($old['maneja_centro_costos'], FILTER_VALIDATE_BOOLEAN)
                                                    : (bool) $tipo->maneja_centro_costos;
                                                $obligatorio = isset($old['centro_costo_obligatorio'])
                                                    ? filter_var($old['centro_costo_obligatorio'], FILTER_VALIDATE_BOOLEAN)
                                                    : (bool) $tipo->centro_costo_obligatorio;
                                                $defaultId = $old['centro_costo_default_id'] ?? $tipo->centro_costo_default_id;
                                            @endphp
                                            <tr class="border-t border-white/5 text-gray-300"
                                                x-data="{
                                                    maneja: {{ $maneja ? 'true' : 'false' }},
                                                    obligatorio: {{ $obligatorio ? 'true' : 'false' }},
                                                    defaultId: @js($defaultId ? (string) $defaultId : '')
                                                }">
                                                <td class="px-3 py-3">
                                                    <input type="hidden" name="tipos[{{ $row }}][id]" value="{{ $tipo->id }}">
                                                    <span class="font-medium text-gray-100">{{ $tipo->familia }}-{{ $tipo->codigo }}</span>
                                                    <div class="text-xs text-gray-500">{{ $tipo->nombre }}</div>
                                                </td>
                                                <td class="px-3 py-3 text-center">
                                                    <input type="hidden" name="tipos[{{ $row }}][maneja_centro_costos]" value="0">
                                                    <input type="checkbox" name="tipos[{{ $row }}][maneja_centro_costos]" value="1"
                                                           x-model="maneja"
                                                           @change="if (!maneja) { defaultId = ''; obligatorio = false; }"
                                                           class="rounded border-white/20 bg-white/5">
                                                </td>
                                                <td class="px-3 py-3">
                                                    <select name="tipos[{{ $row }}][centro_costo_default_id]"
                                                            x-model="defaultId"
                                                            :disabled="!maneja"
                                                            class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">
                                                        <option value="">Sin valor por defecto</option>
                                                        @foreach($subcentrosOpciones as $sub)
                                                            <option value="{{ $sub->id }}">
                                                                {{ $sub->codigo }} — {{ $sub->padre?->nombre }} / {{ $sub->nombre }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="px-3 py-3 text-center">
                                                    <input type="hidden" name="tipos[{{ $row }}][centro_costo_obligatorio]" value="0">
                                                    <input type="checkbox" name="tipos[{{ $row }}][centro_costo_obligatorio]" value="1"
                                                           x-model="obligatorio"
                                                           :disabled="!maneja"
                                                           class="rounded border-white/20 bg-white/5 disabled:opacity-40">
                                                </td>
                                            </tr>
                                            @php $row++; @endphp
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-10 text-center text-gray-400">
                                                No hay tipos de comprobante. Se crean al abrir Tipos de comprobante o esta pestaña.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($subcentrosOpciones->isEmpty())
                            <div class="mx-6 mb-6 rounded-lg border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-sm text-amber-200">
                                Aún no hay subcentros activos. Crea un centro en la pestaña anterior para poder elegir valor por defecto.
                            </div>
                        @endif
                    </form>
                </div>
            @endif
        </div>

        @if($tab === 'catalogo')
        {{-- Crear --}}
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @keydown.escape.window="showCreate = false">
            <div class="w-full max-w-lg rounded-xl border border-white/10 bg-dark-card p-6 shadow-xl" @click.outside="showCreate = false">
                <h3 class="text-lg font-semibold text-white mb-4">Crear centro / subcentro</h3>
                <form method="POST" action="{{ route('stores.contabilidad.centros-costo.store', $store) }}" class="space-y-4">
                    @csrf
                    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                        <input type="hidden" name="es_subcentro" value="0">
                        <input type="checkbox" name="es_subcentro" value="1" x-model="createEsSubcentro" class="rounded border-white/20 bg-white/5">
                        Es un subcentro (bajo un centro existente)
                    </label>
                    <div x-show="createEsSubcentro">
                        <label class="block text-sm text-gray-400 mb-1">Centro al que pertenece</label>
                        <select name="parent_id" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                                :required="createEsSubcentro">
                            <option value="">Seleccione...</option>
                            @foreach($centrosPadre as $padre)
                                <option value="{{ $padre->id }}" @selected(old('parent_id') == $padre->id)>
                                    {{ $padre->codigo }} — {{ $padre->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Código</label>
                        <input type="text" name="codigo" value="{{ old('codigo') }}" required maxlength="32"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" required maxlength="255"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-xl text-sm">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl text-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Editar --}}
        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @keydown.escape.window="showEdit = false">
            <div class="w-full max-w-lg rounded-xl border border-white/10 bg-dark-card p-6 shadow-xl" @click.outside="showEdit = false">
                <h3 class="text-lg font-semibold text-white mb-4">Editar</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/contabilidad/centros-costo') }}/' + editId" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_id" :value="editId">
                    <div x-show="editEsSubcentro">
                        <label class="block text-sm text-gray-400 mb-1">Centro al que pertenece</label>
                        <select name="parent_id" x-model="editParentId" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            @foreach($centrosPadre as $padre)
                                <option value="{{ $padre->id }}">{{ $padre->codigo }} — {{ $padre->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Código</label>
                        <input type="text" name="codigo" x-model="editCodigo" required maxlength="32"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="nombre" x-model="editNombre" required maxlength="255"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" name="activo" value="1" x-model="editActivo" class="rounded border-white/20 bg-white/5">
                        Activo
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEdit = false" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-xl text-sm">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl text-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
