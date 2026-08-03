@php
    $cuentaLabel = fn ($cuenta) => $cuenta
        ? $cuenta->codigo.' — '.$cuenta->nombre
        : '—';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Impuestos — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.contabilidad.tipos', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Tipos de comprobante
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        showCreate: {{ $errors->any() && ! old('_edit_id') ? 'true' : 'false' }},
        showEdit: false,
        editId: null,
        editCodigo: '',
        editNombre: '',
        editTipo: @js(\App\Models\Impuesto::TIPO_IVA),
        editTarifa: '',
        editPorValor: false,
        editEnUso: true,
        editVentas: '',
        editCompras: '',
        editDevVentas: '',
        editDevCompras: '',
        openEdit(i) {
            this.editId = i.id;
            this.editCodigo = String(i.codigo);
            this.editNombre = i.nombre;
            this.editTipo = i.tipo;
            this.editTarifa = String(i.tarifa);
            this.editPorValor = !!i.por_valor;
            this.editEnUso = !!i.en_uso;
            this.editVentas = String(i.cuenta_ventas_id || '');
            this.editCompras = String(i.cuenta_compras_id || '');
            this.editDevVentas = String(i.cuenta_devolucion_ventas_id || '');
            this.editDevCompras = String(i.cuenta_devolucion_compras_id || '');
            this.showEdit = true;
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-950/30 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-amber-200">{{ session('warning') }}</div>
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
                Catálogo estilo Siigo: defines tarifas y las cuentas contables de ventas, compras y devoluciones.
                Los tipos (IVA, Retefuente, etc.) son fijos del sistema. Aún no se aplican automáticamente en facturas.
            </div>

            @if($cuentaFiltro)
                <div class="mb-4 rounded-xl border border-violet-500/30 bg-violet-950/30 px-4 py-3 text-sm text-violet-100 flex flex-wrap items-center justify-between gap-2">
                    <span>
                        Mostrando impuestos que usan la cuenta
                        <span class="font-mono font-semibold">{{ $cuentaFiltro->codigo }}</span>
                        — {{ $cuentaFiltro->nombre }}.
                    </span>
                    <a href="{{ route('stores.contabilidad.impuestos', $store) }}"
                       class="text-violet-200 hover:text-white underline text-sm">Quitar filtro</a>
                </div>
            @endif

            @if($cuentas->isEmpty())
                <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-amber-200">
                    No hay cuentas auxiliares transaccionales. Créelas primero en el Plan de cuentas.
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.contabilidad.impuestos', $store) }}" class="flex flex-wrap gap-2 items-end">
                            @if($cuentaFiltro)
                                <input type="hidden" name="cuenta_contable_id" value="{{ $cuentaFiltro->id }}">
                            @endif
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Buscar código, nombre o tipo..."
                                   class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <select name="tipo" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todos los tipos</option>
                                @foreach($tipos as $tipo)
                                    <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ $tipo }}</option>
                                @endforeach
                            </select>
                            <select name="en_uso" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('en_uso') === '1')>En uso</option>
                                <option value="0" @selected(request('en_uso') === '0')>Fuera de uso</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                            @if(request()->anyFilled(['search', 'tipo', 'en_uso', 'cuenta_contable_id']))
                                <a href="{{ route('stores.contabilidad.impuestos', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
                            @endif
                        </form>

                        @storeCan($store, 'contabilidad.impuestos.create')
                        <button type="button" @click="showCreate = true"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white text-xs font-semibold uppercase tracking-wider rounded-xl"
                                @disabled($cuentas->isEmpty())>
                            Crear impuesto
                        </button>
                        @endstoreCan
                    </div>

                    @if($impuestos->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">En uso</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Tipo</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Por valor</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase">Tarifa</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Ventas</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Compras</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Dev. ventas</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Dev. compras</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($impuestos as $impuesto)
                                        <tr class="hover:bg-white/5 transition text-sm text-gray-300">
                                            <td class="px-3 py-3">
                                                @if($impuesto->en_uso)
                                                    <span class="text-emerald-400">Sí</span>
                                                @else
                                                    <span class="text-gray-500">No</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 font-mono text-brand">{{ $impuesto->codigo }}</td>
                                            <td class="px-3 py-3 text-gray-100">{{ $impuesto->nombre }}</td>
                                            <td class="px-3 py-3">{{ $impuesto->tipo }}</td>
                                            <td class="px-3 py-3">{{ $impuesto->por_valor ? 'Sí' : 'No' }}</td>
                                            <td class="px-3 py-3 text-right font-mono">{{ rtrim(rtrim(number_format((float) $impuesto->tarifa, 4, ',', '.'), '0'), ',') }}%</td>
                                            <td class="px-3 py-3 font-mono text-xs" title="{{ $cuentaLabel($impuesto->cuentaVentas) }}">{{ $impuesto->cuentaVentas?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 font-mono text-xs" title="{{ $cuentaLabel($impuesto->cuentaCompras) }}">{{ $impuesto->cuentaCompras?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 font-mono text-xs" title="{{ $cuentaLabel($impuesto->cuentaDevolucionVentas) }}">{{ $impuesto->cuentaDevolucionVentas?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 font-mono text-xs" title="{{ $cuentaLabel($impuesto->cuentaDevolucionCompras) }}">{{ $impuesto->cuentaDevolucionCompras?->codigo ?? '—' }}</td>
                                            <td class="px-3 py-3 text-right">
                                                @storeCan($store, 'contabilidad.impuestos.edit')
                                                <button type="button"
                                                    class="text-brand hover:underline"
                                                    @click="openEdit({
                                                        id: {{ $impuesto->id }},
                                                        codigo: {{ $impuesto->codigo }},
                                                        nombre: @js($impuesto->nombre),
                                                        tipo: @js($impuesto->tipo),
                                                        tarifa: @js((string) $impuesto->tarifa),
                                                        por_valor: {{ $impuesto->por_valor ? 'true' : 'false' }},
                                                        en_uso: {{ $impuesto->en_uso ? 'true' : 'false' }},
                                                        cuenta_ventas_id: {{ $impuesto->cuenta_ventas_id }},
                                                        cuenta_compras_id: {{ $impuesto->cuenta_compras_id }},
                                                        cuenta_devolucion_ventas_id: {{ $impuesto->cuenta_devolucion_ventas_id }},
                                                        cuenta_devolucion_compras_id: {{ $impuesto->cuenta_devolucion_compras_id }}
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
                        <div class="mt-4">{{ $impuestos->links() }}</div>
                    @else
                        <div class="text-center py-12 text-gray-400">
                            <p class="mb-2">Aún no hay impuestos configurados.</p>
                            <p class="text-sm">Crea uno (por ejemplo IVA 19%) y asígnale las cuatro cuentas auxiliares.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Modal crear --}}
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showCreate = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-2xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Crear impuesto</h3>
                <form method="POST" action="{{ route('stores.contabilidad.impuestos.store', $store) }}" class="space-y-4">
                    @csrf
                    @include('stores.contabilidad.partials.impuesto-form', [
                        'codigoDefault' => old('codigo', $codigoSugerido),
                        'nombreDefault' => old('nombre'),
                        'tipoDefault' => old('tipo', \App\Models\Impuesto::TIPO_IVA),
                        'tarifaDefault' => old('tarifa', '19'),
                        'porValorDefault' => old('por_valor', false),
                        'enUsoDefault' => old('en_uso', true),
                        'ventasDefault' => old('cuenta_ventas_id'),
                        'comprasDefault' => old('cuenta_compras_id'),
                        'devVentasDefault' => old('cuenta_devolucion_ventas_id'),
                        'devComprasDefault' => old('cuenta_devolucion_compras_id'),
                        'tipos' => $tipos,
                        'cuentas' => $cuentas,
                        'useAlpineEdit' => false,
                    ])
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl" @disabled($cuentas->isEmpty())>Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal editar --}}
        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showEdit = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-2xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Editar impuesto</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/contabilidad/impuestos') }}/' + editId" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_id" :value="editId">
                    @include('stores.contabilidad.partials.impuesto-form', [
                        'codigoDefault' => '',
                        'nombreDefault' => '',
                        'tipoDefault' => \App\Models\Impuesto::TIPO_IVA,
                        'tarifaDefault' => '',
                        'porValorDefault' => false,
                        'enUsoDefault' => true,
                        'ventasDefault' => null,
                        'comprasDefault' => null,
                        'devVentasDefault' => null,
                        'devComprasDefault' => null,
                        'tipos' => $tipos,
                        'cuentas' => $cuentas,
                        'useAlpineEdit' => true,
                    ])
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEdit = false" class="px-4 py-2 text-gray-300 hover:bg-white/5 rounded-lg">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
