@php
    $cuentaLabel = fn ($cuenta) => $cuenta
        ? $cuenta->codigo.' — '.$cuenta->nombre
        : '—';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Formas de pago — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.contabilidad.impuestos', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Impuestos
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        showCreate: {{ $errors->any() && ! old('_edit_id') ? 'true' : 'false' }},
        showEdit: false,
        editId: null,
        editCodigo: '',
        editNombre: '',
        editAplicaA: @js(\App\Models\FormaPago::APLICA_AMBOS),
        editCuenta: '',
        editMedioDian: '',
        editEnUso: true,
        editPagoLinea: false,
        openEdit(f) {
            this.editId = f.id;
            this.editCodigo = String(f.codigo);
            this.editNombre = f.nombre;
            this.editAplicaA = f.aplica_a;
            this.editCuenta = String(f.cuenta_contable_id || '');
            this.editMedioDian = f.medio_pago_dian || '';
            this.editEnUso = !!f.en_uso;
            this.editPagoLinea = !!f.es_pago_en_linea;
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
                Catálogo estilo Siigo: cada forma de pago apunta a una cuenta auxiliar del PUC (varias formas pueden compartir la misma cuenta).
                El medio DIAN es el código fiscal para documentos electrónicos. Aún no se usa automáticamente en facturas/POS.
            </div>

            @if($cuentas->isEmpty())
                <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-amber-200">
                    No hay cuentas auxiliares transaccionales. Créelas primero en el
                    <a href="{{ route('stores.contabilidad.cuentas', $store) }}" class="underline">Plan de cuentas</a>
                    o importa el PUC base para poder generar los defaults.
                </div>
            @endif

            <div class="mb-4 inline-flex rounded-xl border border-white/10 bg-white/5 p-1">
                <a href="{{ route('stores.contabilidad.formas-pago', $store) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium {{ ! $tabLinea ? 'bg-brand text-white' : 'text-gray-400 hover:text-white' }}">
                    General
                </a>
                <a href="{{ route('stores.contabilidad.formas-pago', ['store' => $store, 'tab' => 'linea']) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium {{ $tabLinea ? 'bg-brand text-white' : 'text-gray-400 hover:text-white' }}">
                    Pago en línea
                </a>
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <form method="GET" action="{{ route('stores.contabilidad.formas-pago', $store) }}" class="flex flex-wrap gap-2 items-end">
                            @if($tabLinea)
                                <input type="hidden" name="tab" value="linea">
                            @endif
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Buscar código, nombre o medio DIAN..."
                                   class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <select name="aplica_a" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todos los alcances</option>
                                @foreach($aplicaAOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(request('aplica_a') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="en_uso" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Todos</option>
                                <option value="1" @selected(request('en_uso') === '1')>En uso</option>
                                <option value="0" @selected(request('en_uso') === '0')>Fuera de uso</option>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                            @if(request()->anyFilled(['search', 'aplica_a', 'en_uso', 'cuenta_contable_id']))
                                <a href="{{ route('stores.contabilidad.formas-pago', array_filter(['store' => $store, 'tab' => $tabLinea ? 'linea' : null])) }}"
                                   class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
                            @endif
                        </form>

                        @storeCan($store, 'contabilidad.formas-pago.create')
                        <button type="button" @click="showCreate = true; editPagoLinea = {{ $tabLinea ? 'true' : 'false' }}"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white text-xs font-semibold uppercase tracking-wider rounded-xl"
                                @disabled($cuentas->isEmpty())>
                            Crear forma de pago
                        </button>
                        @endstoreCan
                    </div>

                    @if($formasPago->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">En uso</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Aplica a</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cuenta contable</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Medio D. Electrónico</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($formasPago as $forma)
                                        <tr class="hover:bg-white/5 transition text-sm text-gray-300">
                                            <td class="px-3 py-3">
                                                @if($forma->en_uso)
                                                    <span class="text-emerald-400">Sí</span>
                                                @else
                                                    <span class="text-gray-500">No</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 font-mono text-brand">{{ $forma->codigo }}</td>
                                            <td class="px-3 py-3 text-gray-100">{{ $forma->nombre }}</td>
                                            <td class="px-3 py-3 text-xs">{{ $forma->labelAplicaA() }}</td>
                                            <td class="px-3 py-3 font-mono text-xs" title="{{ $cuentaLabel($forma->cuentaContable) }}">
                                                {{ $forma->cuentaContable?->codigo ?? '—' }}
                                                @if($forma->cuentaContable)
                                                    <span class="text-gray-500">— {{ $forma->cuentaContable->nombre }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-xs">{{ $forma->labelMedioPagoDian() ?? '—' }}</td>
                                            <td class="px-3 py-3 text-right">
                                                @storeCan($store, 'contabilidad.formas-pago.edit')
                                                <button type="button"
                                                    class="text-brand hover:underline"
                                                    @click="openEdit({
                                                        id: {{ $forma->id }},
                                                        codigo: {{ $forma->codigo }},
                                                        nombre: @js($forma->nombre),
                                                        aplica_a: @js($forma->aplica_a),
                                                        cuenta_contable_id: {{ $forma->cuenta_contable_id }},
                                                        medio_pago_dian: @js($forma->medio_pago_dian),
                                                        en_uso: {{ $forma->en_uso ? 'true' : 'false' }},
                                                        es_pago_en_linea: {{ $forma->es_pago_en_linea ? 'true' : 'false' }}
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
                        <div class="mt-4">{{ $formasPago->links() }}</div>
                    @else
                        <div class="text-center py-12 text-gray-400">
                            <p class="mb-2">No hay formas de pago en esta pestaña.</p>
                            <p class="text-sm">Crea una o importa el PUC base para generar Efectivo, Transferencia y Crédito.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showCreate = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-2xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Crear forma de pago</h3>
                <form method="POST" action="{{ route('stores.contabilidad.formas-pago.store', $store) }}" class="space-y-4">
                    @csrf
                    @include('stores.contabilidad.partials.forma-pago-form', [
                        'codigoDefault' => old('codigo', $codigoSugerido),
                        'nombreDefault' => old('nombre'),
                        'aplicaADefault' => old('aplica_a', \App\Models\FormaPago::APLICA_AMBOS),
                        'cuentaDefault' => old('cuenta_contable_id'),
                        'medioDefault' => old('medio_pago_dian', '10'),
                        'enUsoDefault' => old('en_uso', true),
                        'pagoLineaDefault' => old('es_pago_en_linea', $tabLinea),
                        'aplicaAOptions' => $aplicaAOptions,
                        'mediosDian' => $mediosDian,
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

        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" style="display: none;">
            <div class="absolute inset-0 bg-black/60" @click="showEdit = false"></div>
            <div class="relative bg-dark-card border border-white/10 rounded-xl w-full max-w-2xl p-6 shadow-xl my-8">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Editar forma de pago</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/contabilidad/formas-pago') }}/' + editId" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_id" :value="editId">
                    @include('stores.contabilidad.partials.forma-pago-form', [
                        'codigoDefault' => '',
                        'nombreDefault' => '',
                        'aplicaADefault' => \App\Models\FormaPago::APLICA_AMBOS,
                        'cuentaDefault' => null,
                        'medioDefault' => null,
                        'enUsoDefault' => true,
                        'pagoLineaDefault' => false,
                        'aplicaAOptions' => $aplicaAOptions,
                        'mediosDian' => $mediosDian,
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
