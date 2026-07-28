<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Categorías de productos y servicios — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.contabilidad.cuentas', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Plan de cuentas
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        showCreate: {{ $errors->any() && ! old('_edit_id') ? 'true' : 'false' }},
        showEdit: false,
        tipo: @js(old('tipo', 'producto')),
        editId: null,
        editCodigo: '',
        editNombre: '',
        editTipo: 'producto',
        editInventario: '',
        editCosto: '',
        editIngreso: '',
        editDevolucion: '',
        editActivo: true,
        openEdit(c) {
            this.editId = c.id;
            this.editCodigo = c.codigo;
            this.editNombre = c.nombre;
            this.editTipo = c.tipo;
            this.editInventario = c.cuenta_inventario_id ? String(c.cuenta_inventario_id) : '';
            this.editCosto = c.cuenta_costo_id ? String(c.cuenta_costo_id) : '';
            this.editIngreso = c.cuenta_ingreso_id ? String(c.cuenta_ingreso_id) : '';
            this.editDevolucion = c.cuenta_devolucion_id ? String(c.cuenta_devolucion_id) : '';
            this.editActivo = !!c.activo;
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
                Producto y servicio usan las 4 cuentas (inventario, costo, ingreso y devolución), cada una con sus auxiliares.
                Elige auxiliares del plan de cuentas (ej. inventario <span class="font-mono">14350101</span>).
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.contabilidad.categorias', $store) }}" class="flex flex-wrap gap-2 items-end">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Buscar código o nombre..."
                                   class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <select name="tipo" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todos los tipos</option>
                                <option value="producto" @selected(request('tipo') === 'producto')>Producto</option>
                                <option value="servicio" @selected(request('tipo') === 'servicio')>Servicio</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                            @if(request()->anyFilled(['search', 'tipo']))
                                <a href="{{ route('stores.contabilidad.categorias', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
                            @endif
                        </form>

                        @storeCan($store, 'contabilidad.categorias.create')
                        <button type="button" @click="showCreate = true; tipo = 'producto'"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white text-xs font-semibold uppercase tracking-wider rounded-xl">
                            Crear categoría
                        </button>
                        @endstoreCan
                    </div>

                    @if($categorias->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Inventarios</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Costo ventas</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Ingreso</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Devoluciones</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($categorias as $cat)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-3 py-3 text-sm font-mono text-brand">{{ $cat->codigo }}</td>
                                            <td class="px-3 py-3 text-sm text-gray-100">
                                                {{ $cat->nombre }}
                                                <div class="text-xs text-gray-500">{{ $cat->etiquetaTipo() }}</div>
                                            </td>
                                            <td class="px-3 py-3 text-sm font-mono text-gray-300">{{ $cat->cuentaInventario?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 text-sm font-mono text-gray-300">{{ $cat->cuentaCosto?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 text-sm font-mono text-gray-300">{{ $cat->cuentaIngreso?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 text-sm font-mono text-gray-300">{{ $cat->cuentaDevolucion?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 text-sm">
                                                @if($cat->activo)
                                                    <span class="text-emerald-400">Activa</span>
                                                @else
                                                    <span class="text-red-400">Inactiva</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-right text-sm">
                                                @storeCan($store, 'contabilidad.categorias.edit')
                                                <button type="button"
                                                    class="text-brand hover:underline"
                                                    @click="openEdit({
                                                        id: {{ $cat->id }},
                                                        codigo: @js($cat->codigo),
                                                        nombre: @js($cat->nombre),
                                                        tipo: @js($cat->tipo),
                                                        cuenta_inventario_id: {{ $cat->cuenta_inventario_id ?? 'null' }},
                                                        cuenta_costo_id: {{ $cat->cuenta_costo_id ?? 'null' }},
                                                        cuenta_ingreso_id: {{ $cat->cuenta_ingreso_id ?? 'null' }},
                                                        cuenta_devolucion_id: {{ $cat->cuenta_devolucion_id ?? 'null' }},
                                                        activo: {{ $cat->activo ? 'true' : 'false' }}
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
                            {{ $categorias->links() }}
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-400">
                            <p class="mb-2">Aún no hay categorías contables.</p>
                            <p class="text-sm">Crea una de tipo Producto y asígnale las auxiliares de inventario, costo, ingreso y devolución.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Modal crear --}}
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showCreate = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Crear categoría</h3>
                <form method="POST" action="{{ route('stores.contabilidad.categorias.store', $store) }}" class="space-y-4">
                    @csrf
                    @include('stores.contabilidad.partials.categoria-contable-form', [
                        'prefix' => 'create',
                        'codigoDefault' => old('codigo', $codigoSugerido),
                        'nombreDefault' => old('nombre'),
                        'tipoModel' => 'tipo',
                        'inventarioDefault' => old('cuenta_inventario_id'),
                        'costoDefault' => old('cuenta_costo_id'),
                        'ingresoDefault' => old('cuenta_ingreso_id'),
                        'devolucionDefault' => old('cuenta_devolucion_id'),
                        'activoDefault' => old('activo', true),
                        'cuentas' => $cuentas,
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
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Editar categoría</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/contabilidad/categorias') }}/' + editId" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_id" :value="editId">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Código</label>
                        <input type="text" name="codigo" x-model="editCodigo" required
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="nombre" x-model="editNombre" required
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Tipo</label>
                        <select name="tipo" x-model="editTipo" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="producto">Producto</option>
                            <option value="servicio">Servicio</option>
                        </select>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Cuenta inventarios</label>
                            <select name="cuenta_inventario_id" x-model="editInventario"
                                    class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Selecciona…</option>
                                @foreach($cuentas['inventario'] as $c)
                                    <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Cuenta costo de ventas</label>
                            <select name="cuenta_costo_id" x-model="editCosto"
                                    class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Selecciona…</option>
                                @foreach($cuentas['costo'] as $c)
                                    <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Cuenta de ingreso</label>
                        <select name="cuenta_ingreso_id" x-model="editIngreso" required class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="">Selecciona…</option>
                            @foreach($cuentas['ingreso'] as $c)
                                <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Cuenta de devoluciones</label>
                        <select name="cuenta_devolucion_id" x-model="editDevolucion" required class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="">Selecciona…</option>
                            @foreach($cuentas['devolucion'] as $c)
                                <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
                            @endforeach
                        </select>
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
</x-app-layout>
