<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Tipos de comprobante — {{ $store->name }}
            </h2>
            <div class="flex items-center gap-4">
                @storeCan($store, 'contabilidad.centros-costo.view')
                <a href="{{ route('stores.contabilidad.centros-costo', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    Centros de costo
                </a>
                @endstoreCan
                <a href="{{ route('stores.contabilidad.categorias', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    ← Categorías contables
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        showCreate: {{ $errors->any() && ! old('_edit_id') ? 'true' : 'false' }},
        showEdit: false,
        familia: @js(old('familia', 'FV')),
        editId: null,
        editFamilia: 'FV',
        editCodigo: '',
        editNombre: '',
        editTitulo: '',
        editPrefijo: '',
        editSiguiente: 1,
        editLibro: '',
        editNumeracion: true,
        editCentroCostos: false,
        editActivo: true,
        openEdit(t) {
            this.editId = t.id;
            this.editFamilia = t.familia;
            this.editCodigo = t.codigo;
            this.editNombre = t.nombre;
            this.editTitulo = t.titulo || '';
            this.editPrefijo = t.prefijo;
            this.editSiguiente = t.siguiente_numero;
            this.editLibro = t.libro_oficial || '';
            this.editNumeracion = !!t.numeracion_automatica;
            this.editCentroCostos = !!t.maneja_centro_costos;
            this.editActivo = !!t.activo;
            this.showEdit = true;
        }
    }">
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

            <div class="mb-4 rounded-xl border border-sky-500/30 bg-sky-950/40 px-4 py-3 text-sm text-sky-100">
                Catálogo estilo Siigo: FV (venta), RC (recibo de caja), FC (compra), RP (pago/egreso) y CC (comprobante contable).
                Cada familia puede tener varios tipos con consecutivo propio. La numeración de CI/CE aún no se reemplaza.
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.contabilidad.tipos', $store) }}" class="flex flex-wrap gap-2 items-end">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Buscar código, nombre o prefijo..."
                                   class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <select name="familia" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todas las familias</option>
                                @foreach($familias as $key => $label)
                                    <option value="{{ $key }}" @selected(request('familia') === $key)>{{ $key }} — {{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="activo" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('activo') === '1')>Activos</option>
                                <option value="0" @selected(request('activo') === '0')>Inactivos</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                            @if(request()->anyFilled(['search', 'familia', 'activo']))
                                <a href="{{ route('stores.contabilidad.tipos', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
                            @endif
                        </form>

                        @storeCan($store, 'contabilidad.tipos.create')
                        <button type="button" @click="showCreate = true; familia = 'FV'"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white text-xs font-semibold uppercase tracking-wider rounded-xl">
                            Crear tipo
                        </button>
                        @endstoreCan
                    </div>

                    @if($tipos->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Familia</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Prefijo</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Siguiente</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Libro</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($tipos as $tipo)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-3 py-3 text-sm font-mono text-brand">{{ $tipo->familia }}</td>
                                            <td class="px-3 py-3 text-sm font-mono text-gray-300">{{ $tipo->codigo }}</td>
                                            <td class="px-3 py-3 text-sm text-gray-100">
                                                {{ $tipo->nombre }}
                                                <div class="text-xs text-gray-500">{{ $tipo->etiquetaFamilia() }}</div>
                                            </td>
                                            <td class="px-3 py-3 text-sm font-mono text-gray-300">{{ $tipo->prefijo }}</td>
                                            <td class="px-3 py-3 text-sm text-gray-300">
                                                {{ $tipo->siguiente_numero }}
                                                @if($tipo->numeracion_automatica)
                                                    <span class="text-xs text-gray-500">auto</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-400">{{ $tipo->etiquetaLibroOficial() }}</td>
                                            <td class="px-3 py-3 text-sm">
                                                @if($tipo->activo)
                                                    <span class="text-emerald-400">Activo</span>
                                                @else
                                                    <span class="text-red-400">Inactivo</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-right text-sm">
                                                @storeCan($store, 'contabilidad.tipos.edit')
                                                <button type="button"
                                                    class="text-brand hover:underline"
                                                    @click="openEdit({
                                                        id: {{ $tipo->id }},
                                                        familia: @js($tipo->familia),
                                                        codigo: @js($tipo->codigo),
                                                        nombre: @js($tipo->nombre),
                                                        titulo: @js($tipo->titulo),
                                                        prefijo: @js($tipo->prefijo),
                                                        siguiente_numero: {{ $tipo->siguiente_numero }},
                                                        libro_oficial: @js($tipo->libro_oficial),
                                                        numeracion_automatica: {{ $tipo->numeracion_automatica ? 'true' : 'false' }},
                                                        maneja_centro_costos: {{ $tipo->maneja_centro_costos ? 'true' : 'false' }},
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
                            <p class="mb-2">Aún no hay tipos de comprobante.</p>
                            <p class="text-sm">Al abrir esta pantalla se crean los 5 tipos por defecto (FV, RC, FC, RP, CC).</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Modal crear --}}
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showCreate = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Crear tipo de comprobante</h3>
                <form method="POST" action="{{ route('stores.contabilidad.tipos.store', $store) }}" class="space-y-4">
                    @csrf
                    @include('stores.contabilidad.partials.tipo-comprobante-form', [
                        'familias' => $familias,
                        'familiaModel' => 'familia',
                        'familiaDefault' => old('familia', 'FV'),
                        'codigoDefault' => old('codigo', $codigoSugerido),
                        'nombreDefault' => old('nombre'),
                        'tituloDefault' => old('titulo'),
                        'prefijoDefault' => old('prefijo'),
                        'siguienteDefault' => old('siguiente_numero', 1),
                        'libroDefault' => old('libro_oficial'),
                        'numeracionDefault' => old('numeracion_automatica', true),
                        'centroCostosDefault' => old('maneja_centro_costos', false),
                        'activoDefault' => old('activo', true),
                    ])
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal editar --}}
        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showEdit = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Editar tipo de comprobante</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/contabilidad/tipos-comprobante') }}/' + editId" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_id" :value="editId">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Familia</label>
                        <select name="familia" x-model="editFamilia" required class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            @foreach($familias as $key => $label)
                                <option value="{{ $key }}">{{ $key }} — {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Código</label>
                            <input type="text" name="codigo" x-model="editCodigo" required
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Prefijo</label>
                            <input type="text" name="prefijo" x-model="editPrefijo" required
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 uppercase">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="nombre" x-model="editNombre" required
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Título (impresión)</label>
                        <input type="text" name="titulo" x-model="editTitulo"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Siguiente número</label>
                            <input type="number" name="siguiente_numero" min="1" x-model="editSiguiente"
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Libro oficial</label>
                            <select name="libro_oficial" x-model="editLibro" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">No aplica</option>
                                <option value="ventas">Ventas</option>
                                <option value="compras">Compras</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-4 pt-1">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                            <input type="hidden" name="numeracion_automatica" value="0">
                            <input type="checkbox" name="numeracion_automatica" value="1" x-model="editNumeracion" class="rounded border-white/20 bg-white/5">
                            Numeración automática
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                            <input type="hidden" name="maneja_centro_costos" value="0">
                            <input type="checkbox" name="maneja_centro_costos" value="1" x-model="editCentroCostos" class="rounded border-white/20 bg-white/5">
                            Maneja centro de costos
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" value="1" x-model="editActivo" class="rounded border-white/20 bg-white/5">
                            Activo
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEdit = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
