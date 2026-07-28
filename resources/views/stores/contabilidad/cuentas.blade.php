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

    <div class="py-12" x-data="{
        showAuxiliar: false,
        showEdit: false,
        editId: null,
        editNombre: '',
        editActivo: true,
        padreId: @js(old('cuenta_padre_id', '')),
        categoria: @js(old('categoria', '')),
        clase: @js(old('clase', 'Activo')),
        relacion: @js(old('relacion_con', '')),
        vencimientos: @js(old('maneja_vencimientos', 'No maneja vencimiento')),
        nivel: @js(old('nivel_agrupacion', 'Transaccional') ?? 'Transaccional'),
        padres: @js($padres->map(function ($p) {
            $defaults = \App\Models\CuentaContable::defaultsParaCodigoPadre($p->codigo);
            $perfil = \App\Models\CuentaContable::perfilDesdeCodigo($p->codigo);

            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'perfil' => $perfil,
                'disponible' => $perfil === 'disponible',
                'operable' => str_starts_with($p->codigo, '1105') || str_starts_with($p->codigo, '1110') || str_starts_with($p->codigo, '1120'),
                'defaults' => $defaults,
            ];
        })->values()),
        applyDefaults() {
            const p = this.padres.find(x => String(x.id) === String(this.padreId));
            if (!p || !p.defaults) return;
            this.categoria = p.defaults.categoria || '';
            this.relacion = p.defaults.relacion_con || '';
            this.vencimientos = p.defaults.maneja_vencimientos || 'No maneja vencimiento';
            if (p.defaults.clase) this.clase = p.defaults.clase;
        },
        get padreOperable() {
            const p = this.padres.find(x => String(x.id) === String(this.padreId));
            return p && p.operable && this.nivel === 'Transaccional';
        },
        get padrePerfilHint() {
            const p = this.padres.find(x => String(x.id) === String(this.padreId));
            if (!p || !p.perfil) return '';
            const labels = {
                disponible: 'Caja / bancos: puede crear bolsillo si el nivel es Transaccional.',
                inventario: 'Inventarios: cuenta para stock de mercancía.',
                costo: 'Costo de ventas: se usa al vender productos.',
                ingreso: 'Ingresos: se usa al registrar ventas.',
                devolucion: 'Devoluciones en ventas.',
            };
            return labels[p.perfil] || '';
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
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                            @if(request()->anyFilled(['search', 'clase', 'es_auxiliar']))
                                <a href="{{ route('stores.contabilidad.cuentas', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
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

                            @storeCan($store, 'contabilidad.cuentas.create')
                            <button type="button" @click="showAuxiliar = true"
                                    class="inline-flex items-center px-4 py-2 bg-brand text-white text-xs font-semibold uppercase tracking-wider rounded-xl">
                                + Auxiliar
                            </button>
                            @endstoreCan
                        </div>
                    </div>

                    <p class="text-sm text-gray-400 mb-4">
                        La plantilla importa solo códigos de hasta 6 dígitos (clase → grupo → cuenta → subcuenta).
                        Las auxiliares (ej. <span class="text-gray-200 font-mono">11050501</span>, <span class="text-gray-200 font-mono">14350101</span>) las creas tú bajo cada subcuenta.
                        Al elegir el padre, el sistema sugiere categoría y relación según el código PUC.
                    </p>

                    @if($cuentas->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Clase</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($cuentas as $cuenta)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-3 text-sm font-mono text-brand">{{ $cuenta->codigo }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-100">
                                                <span style="padding-left: {{ max(0, min(strlen($cuenta->codigo) - 1, 8)) * 8 }}px">
                                                    {{ $cuenta->nombre }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-400">{{ $cuenta->clase ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm">
                                                @if($cuenta->es_auxiliar)
                                                    <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-900/40 text-indigo-200">Auxiliar</span>
                                                @elseif($cuenta->esTransaccional())
                                                    <span class="px-2 py-0.5 text-xs rounded-full bg-amber-900/40 text-amber-200">Transaccional</span>
                                                @else
                                                    <span class="px-2 py-0.5 text-xs rounded-full bg-gray-700 text-gray-300">Agrupadora</span>
                                                @endif
                                                @if($cuenta->bolsillo)
                                                    <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-emerald-900/40 text-emerald-200">Bolsillo</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                @if($cuenta->activo)
                                                    <span class="text-emerald-400">Activa</span>
                                                @else
                                                    <span class="text-red-400">Inactiva</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                @storeCan($store, 'contabilidad.cuentas.edit')
                                                <button type="button"
                                                    class="text-brand hover:underline"
                                                    @click="showEdit = true; editId = {{ $cuenta->id }}; editNombre = @js($cuenta->nombre); editActivo = {{ $cuenta->activo ? 'true' : 'false' }}">
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
                            {{ $cuentas->links() }}
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-400">
                            <p class="mb-4">Aún no hay cuentas en esta tienda.</p>
                            @storeCan($store, 'contabilidad.cuentas.import')
                            <form method="POST" action="{{ route('stores.contabilidad.cuentas.importar', $store) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Importar PUC base ahora</button>
                            </form>
                            @endstoreCan
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Modal crear auxiliar --}}
        <div x-show="showAuxiliar" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showAuxiliar = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Crear cuenta auxiliar</h3>
                <form method="POST" action="{{ route('stores.contabilidad.cuentas.auxiliar', $store) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Cuenta padre (6 dígitos)</label>
                        <select name="cuenta_padre_id" required x-model="padreId" @change="applyDefaults()"
                                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="">Selecciona…</option>
                            @foreach($padres as $padre)
                                <option value="{{ $padre->id }}">
                                    {{ $padre->codigo }} — {{ $padre->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @if($padres->isEmpty())
                            <p class="text-xs text-amber-400 mt-1">Importa el PUC base primero para tener subcuentas de 6 dígitos.</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre</label>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" required
                               placeholder="Ej. Efectivo mostrador"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Sufijo (opcional, 01–99)</label>
                        <input type="text" name="sufijo" value="{{ old('sufijo') }}" maxlength="2"
                               placeholder="Siguiente automático si lo dejas vacío"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                        <p class="text-xs text-gray-500 mt-1">Código final = padre + sufijo (ej. 110505 + 01 → 11050501)</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Categoría</label>
                            <select name="categoria" x-model="categoria"
                                    class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">—</option>
                                @foreach($categoriasSugeridas as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Clase</label>
                            <select name="clase" x-model="clase" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                @foreach($clases as $claseOpt)
                                    <option value="{{ $claseOpt }}">{{ $claseOpt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Relacionado con</label>
                        <input type="text" name="relacion_con" x-model="relacion"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Maneja vencimientos</label>
                        <select name="maneja_vencimientos" x-model="vencimientos"
                                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="No maneja vencimiento">No maneja vencimiento</option>
                            <option value="Con detalle de vencimientos">Con detalle de vencimientos</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Solo aplica a cartera/proveedores. Inventario, ingresos y costo van sin vencimiento.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Nivel de agrupación</label>
                            <select name="nivel_agrupacion" x-model="nivel" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="Transaccional">Transaccional</option>
                                <option value="">Vacío (sin bolsillo automático)</option>
                            </select>
                        </div>
                        <div class="flex flex-col justify-end gap-2 pb-1">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                                <input type="hidden" name="diferencia_fiscal" value="0">
                                <input type="checkbox" name="diferencia_fiscal" value="1" @checked(old('diferencia_fiscal')) class="rounded border-white/20 bg-white/5">
                                Diferencia fiscal
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                                <input type="hidden" name="activo" value="0">
                                <input type="checkbox" name="activo" value="1" @checked(old('activo', true)) class="rounded border-white/20 bg-white/5">
                                Activa (visible en operaciones si crea bolsillo)
                            </label>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400" x-show="padreOperable">
                        Si el padre es Caja / Bancos / Ahorros y el nivel es Transaccional, se creará un bolsillo en Caja.
                    </p>
                    <p class="text-xs text-indigo-300" x-show="padrePerfilHint" x-text="padrePerfilHint"></p>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showAuxiliar = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl" @disabled($padres->isEmpty())>Crear</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal editar --}}
        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showEdit = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-lg p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Editar cuenta</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/contabilidad/cuentas') }}/' + editId" class="space-y-4">
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
</x-app-layout>
