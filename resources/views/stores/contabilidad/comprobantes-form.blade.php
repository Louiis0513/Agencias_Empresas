@php
    $editando = $comprobante !== null;
    $tipoInicial = old('tipo_comprobante_id', $comprobante?->tipo_comprobante_id ?? $tipos->first()?->id);
    $centrosLookup = collect($centrosCosto ?? []);
    $subcentroACentro = [];
    foreach ($centrosLookup as $centro) {
        foreach ($centro['subcentros'] as $sub) {
            $subcentroACentro[(string) $sub['id']] = (string) $centro['id'];
        }
    }
    $lineasIniciales = old('lineas');
    if ($lineasIniciales === null && $comprobante) {
        $lineasIniciales = $comprobante->movimientos->map(function ($linea) use ($subcentroACentro) {
            $subId = $linea->centro_costo_id ? (string) $linea->centro_costo_id : '';

            return [
                'cuenta_contable_id' => (string) $linea->cuenta_contable_id,
                'tercero_id' => $linea->tercero_id ? (string) $linea->tercero_id : '',
                'centro_id' => $subId !== '' ? ($subcentroACentro[$subId] ?? '') : '',
                'centro_costo_id' => $subId,
                'detalle_contable' => $linea->detalle_contable ?? '',
                'descripcion' => $linea->descripcion ?? '',
                'debito' => (string) $linea->debito,
                'credito' => (string) $linea->credito,
            ];
        })->values()->all();
    } elseif (is_array($lineasIniciales)) {
        $lineasIniciales = collect($lineasIniciales)->map(function ($linea) use ($subcentroACentro) {
            $subId = (string) ($linea['centro_costo_id'] ?? '');
            $centroId = (string) ($linea['centro_id'] ?? '');
            if ($centroId === '' && $subId !== '') {
                $centroId = $subcentroACentro[$subId] ?? '';
            }

            return [
                'cuenta_contable_id' => (string) ($linea['cuenta_contable_id'] ?? ''),
                'tercero_id' => (string) ($linea['tercero_id'] ?? ''),
                'centro_id' => $centroId,
                'centro_costo_id' => $subId,
                'detalle_contable' => (string) ($linea['detalle_contable'] ?? ''),
                'descripcion' => (string) ($linea['descripcion'] ?? ''),
                'debito' => (string) ($linea['debito'] ?? ''),
                'credito' => (string) ($linea['credito'] ?? ''),
            ];
        })->values()->all();
    }
    $lineasIniciales ??= null;
    if ($lineasIniciales === null) {
        $tipoParaDefault = $tipos->firstWhere('id', (int) $tipoInicial);
        $defaultSubId = $tipoParaDefault?->centro_costo_default_id
            ? (string) $tipoParaDefault->centro_costo_default_id
            : '';
        $defaultCentroId = $defaultSubId !== '' ? ($subcentroACentro[$defaultSubId] ?? '') : '';
        $lineaVacia = [
            'cuenta_contable_id' => '',
            'tercero_id' => '',
            'centro_id' => $defaultCentroId,
            'centro_costo_id' => $defaultSubId,
            'detalle_contable' => '',
            'descripcion' => '',
            'debito' => '',
            'credito' => '',
        ];
        $lineasIniciales = [$lineaVacia, $lineaVacia];
    }
    $tiposJs = $tipos->map(fn ($tipo) => [
        'id' => (string) $tipo->id,
        'prefijo' => $tipo->prefijo,
        'numeracion_automatica' => $tipo->numeracion_automatica,
        'siguiente_numero' => $tipo->siguiente_numero,
        'maneja_centro_costos' => $tipo->maneja_centro_costos,
        'centro_costo_obligatorio' => $tipo->centro_costo_obligatorio,
        'centro_costo_default_id' => $tipo->centro_costo_default_id ? (string) $tipo->centro_costo_default_id : '',
        'centro_default_padre_id' => $tipo->centro_costo_default_id
            ? ($subcentroACentro[(string) $tipo->centro_costo_default_id] ?? '')
            : '',
    ])->values();
    $monedaCodigo = strtoupper($store->currency ?: 'COP');
    $monedaEtiqueta = $monedaCodigo === 'COP' ? 'COP — Peso colombiano' : $monedaCodigo;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $editando ? 'Editar asiento manual' : 'Nuevo asiento manual CC' }}</h2>
                <p class="mt-1 text-sm text-gray-400">El comprobante se guarda como borrador; se contabiliza desde el detalle.</p>
            </div>
            <a href="{{ route('stores.contabilidad.comprobantes', $store) }}" class="text-sm text-gray-400 hover:text-brand">← Volver</a>
        </div>
    </x-slot>

    <div class="py-10"
         x-data="{
            tipos: @js($tiposJs),
            centros: @js($centrosLookup->values()),
            tipoId: @js((string) $tipoInicial),
            lineas: @js($lineasIniciales),
            nuevaLinea() {
                const tipo = this.tipoSeleccionado;
                const subId = tipo?.centro_costo_default_id || '';
                const centroId = tipo?.centro_default_padre_id || '';
                return {
                    cuenta_contable_id: '',
                    tercero_id: '',
                    centro_id: this.manejaCentroCostos ? centroId : '',
                    centro_costo_id: this.manejaCentroCostos ? subId : '',
                    detalle_contable: '',
                    descripcion: '',
                    debito: '',
                    credito: ''
                };
            },
            agregar() { this.lineas.push(this.nuevaLinea()); },
            quitar(index) { if (this.lineas.length > 2) this.lineas.splice(index, 1); },
            valor(value) {
                const parsed = Number.parseFloat(value);
                return Number.isFinite(parsed) ? parsed : 0;
            },
            total(campo) { return this.lineas.reduce((sum, linea) => sum + this.valor(linea[campo]), 0); },
            get totalDebito() { return this.total('debito'); },
            get totalCredito() { return this.total('credito'); },
            get diferencia() { return this.totalDebito - this.totalCredito; },
            get cuadrado() { return this.totalDebito > 0 && Math.abs(this.diferencia) < 0.005; },
            get tipoSeleccionado() { return this.tipos.find(tipo => tipo.id === String(this.tipoId)); },
            get numeroManual() { return this.tipoSeleccionado && !this.tipoSeleccionado.numeracion_automatica; },
            get consecutivoAutomatico() {
                if (!this.tipoSeleccionado || !this.tipoSeleccionado.numeracion_automatica) return '';
                return this.tipoSeleccionado.prefijo + '-' + String(this.tipoSeleccionado.siguiente_numero).padStart(4, '0');
            },
            get manejaCentroCostos() { return !!this.tipoSeleccionado?.maneja_centro_costos; },
            get centroObligatorio() { return this.manejaCentroCostos && !!this.tipoSeleccionado?.centro_costo_obligatorio; },
            subcentrosDe(linea) {
                const centro = this.centros.find(c => String(c.id) === String(linea.centro_id));
                return centro ? centro.subcentros : [];
            },
            onCentroChange(linea) {
                linea.centro_costo_id = '';
            },
            moneda(valor) { return new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(valor); }
         }">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">
                    <ul class="list-inside list-disc text-sm">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @if($cuentas->isEmpty())
                <div class="rounded-xl border border-amber-500/30 bg-amber-950/30 px-4 py-3 text-amber-200">
                    No hay cuentas auxiliares transaccionales activas. Créelas primero en Cuentas contables.
                </div>
            @endif

            <form method="POST"
                  action="{{ $editando
                    ? route('stores.contabilidad.comprobantes.update', [$store, $comprobante])
                    : route('stores.contabilidad.comprobantes.store', $store) }}"
                  class="space-y-5">
                @csrf
                @if($editando) @method('PUT') @endif

                <section class="rounded-xl border border-white/5 bg-dark-card p-6">
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Tipo de comprobante</label>
                            <select name="tipo_comprobante_id" x-model="tipoId" required
                                    class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                @foreach($tipos as $tipo)
                                    <option value="{{ $tipo->id }}">{{ $tipo->familia }}-{{ $tipo->codigo }} — {{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Consecutivo</label>
                            <div x-show="!numeroManual"
                                 class="flex h-[42px] items-center rounded-md border border-white/10 bg-white/[0.03] px-3 text-sm text-gray-200">
                                <span class="font-mono" x-text="consecutivoAutomatico"></span>
                                <span class="ml-2 text-xs text-gray-500">(automático al contabilizar)</span>
                            </div>
                            <input x-show="numeroManual" x-cloak name="numero"
                                   value="{{ old('numero', $comprobante?->numero) }}"
                                   :required="numeroManual"
                                   placeholder="Número manual"
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Fecha de elaboración</label>
                            <input type="date" name="fecha" required
                                   value="{{ old('fecha', $comprobante?->fecha?->toDateString() ?? now()->toDateString()) }}"
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-gray-400">Moneda</label>
                            <div class="flex h-[42px] items-center rounded-md border border-white/10 bg-white/[0.03] px-3 text-sm text-gray-200">
                                {{ $monedaEtiqueta }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                    <div class="flex items-center justify-between border-b border-white/5 p-4">
                        <div>
                            <h3 class="font-semibold text-gray-100">Líneas del asiento</h3>
                            <p class="text-xs text-gray-500">El tercero, detalle y descripción corresponden a cada cuenta afectada.</p>
                            <p class="mt-1 text-xs text-amber-300/80" x-show="manejaCentroCostos && centros.length === 0" x-cloak>
                                Este tipo exige centro de costo, pero no hay centros activos.
                                <a href="{{ route('stores.contabilidad.centros-costo', $store) }}" class="underline">Crear centros</a>
                            </p>
                        </div>
                        <button type="button" @click="agregar()" class="rounded-lg bg-white/10 px-3 py-2 text-sm text-gray-100 hover:bg-white/15">+ Línea</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[1900px] w-full">
                            <thead class="border-b border-white/5 text-left text-xs uppercase text-gray-400">
                                <tr>
                                    <th class="px-3 py-3 w-12">#</th>
                                    <th class="px-3 py-3 min-w-72">Cuenta auxiliar</th>
                                    <th class="px-3 py-3 min-w-56">Tercero</th>
                                    <th class="px-3 py-3 min-w-56">Detalle contable</th>
                                    <th class="px-3 py-3 min-w-56">Descripción</th>
                                    <th class="px-3 py-3 min-w-64">Centro de costo</th>
                                    <th class="px-3 py-3 w-40 text-right">Débito</th>
                                    <th class="px-3 py-3 w-40 text-right">Crédito</th>
                                    <th class="px-3 py-3 w-16"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <template x-for="(linea, index) in lineas" :key="index">
                                    <tr>
                                        <td class="px-3 py-3 text-sm text-gray-500" x-text="index + 1"></td>
                                        <td class="px-3 py-3">
                                            <select x-model="linea.cuenta_contable_id"
                                                    :name="`lineas[${index}][cuenta_contable_id]`" required
                                                    class="w-full rounded-md border-white/10 bg-white/5 text-sm text-gray-100">
                                                <option value="">Seleccione...</option>
                                                @foreach($cuentas as $cuenta)
                                                    <option value="{{ $cuenta->id }}">{{ $cuenta->codigo }} — {{ $cuenta->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-3">
                                            <select x-model="linea.tercero_id" :name="`lineas[${index}][tercero_id]`"
                                                    class="w-full rounded-md border-white/10 bg-white/5 text-sm text-gray-100">
                                                <option value="">Sin tercero</option>
                                                @foreach($terceros as $tercero)
                                                    <option value="{{ $tercero->id }}">{{ $tercero->numero_identificacion }} — {{ $tercero->nombre }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-3">
                                            <input x-model="linea.detalle_contable"
                                                   :name="`lineas[${index}][detalle_contable]`" maxlength="255"
                                                   placeholder="Referencia, vencimiento..."
                                                   class="w-full rounded-md border-white/10 bg-white/5 text-sm text-gray-100">
                                        </td>
                                        <td class="px-3 py-3">
                                            <input x-model="linea.descripcion" :name="`lineas[${index}][descripcion]`" maxlength="255"
                                                   placeholder="Concepto de la línea"
                                                   class="w-full rounded-md border-white/10 bg-white/5 text-sm text-gray-100">
                                        </td>
                                        <td class="px-3 py-3">
                                            <div x-show="!manejaCentroCostos" class="text-sm text-gray-500">No aplica</div>
                                            <div x-show="manejaCentroCostos" x-cloak class="grid gap-2">
                                                <select x-model="linea.centro_id" @change="onCentroChange(linea)"
                                                        :required="centroObligatorio"
                                                        class="w-full rounded-md border-white/10 bg-white/5 text-sm text-gray-100">
                                                    <option value="">Centro...</option>
                                                    <template x-for="centro in centros" :key="centro.id">
                                                        <option :value="String(centro.id)" x-text="centro.codigo + ' — ' + centro.nombre"></option>
                                                    </template>
                                                </select>
                                                <select x-model="linea.centro_costo_id"
                                                        :name="`lineas[${index}][centro_costo_id]`"
                                                        :required="centroObligatorio"
                                                        class="w-full rounded-md border-white/10 bg-white/5 text-sm text-gray-100">
                                                    <option value="">Subcentro...</option>
                                                    <template x-for="sub in subcentrosDe(linea)" :key="sub.id">
                                                        <option :value="String(sub.id)" x-text="sub.codigo + ' — ' + sub.nombre"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <input type="number" min="0" step="0.01" x-model="linea.debito"
                                                   @input="if (valor(linea.debito) > 0) linea.credito = ''"
                                                   :name="`lineas[${index}][debito]`"
                                                   class="w-full rounded-md border-white/10 bg-white/5 text-right font-mono text-gray-100">
                                        </td>
                                        <td class="px-3 py-3">
                                            <input type="number" min="0" step="0.01" x-model="linea.credito"
                                                   @input="if (valor(linea.credito) > 0) linea.debito = ''"
                                                   :name="`lineas[${index}][credito]`"
                                                   class="w-full rounded-md border-white/10 bg-white/5 text-right font-mono text-gray-100">
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <button type="button" @click="quitar(index)" x-show="lineas.length > 2"
                                                    class="text-red-400 hover:text-red-300">×</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="border-t border-white/10 font-mono">
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-right text-sm font-semibold text-gray-300">Totales</td>
                                    <td class="px-3 py-4 text-right text-gray-100" x-text="'$ ' + moneda(totalDebito)"></td>
                                    <td class="px-3 py-4 text-right text-gray-100" x-text="'$ ' + moneda(totalCredito)"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl border border-white/5 bg-dark-card p-6">
                    <label class="mb-1 block text-sm text-gray-400">Observaciones</label>
                    <textarea name="descripcion" rows="3" required maxlength="2000"
                              class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                              placeholder="Explique el hecho económico o agregue notas generales del comprobante...">{{ old('descripcion', $comprobante?->descripcion) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Esta nota aplica al comprobante completo; no reemplaza la descripción de cada línea.</p>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-white/5 bg-dark-card p-4">
                    <div>
                        <span class="text-sm" :class="cuadrado ? 'text-emerald-300' : 'text-amber-300'"
                              x-text="cuadrado ? 'Asiento cuadrado' : 'Diferencia: $ ' + moneda(Math.abs(diferencia))"></span>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ $editando
                            ? route('stores.contabilidad.comprobantes.show', [$store, $comprobante])
                            : route('stores.contabilidad.comprobantes', $store) }}"
                           class="rounded-lg px-4 py-2 text-sm text-gray-300 hover:bg-white/5">Cancelar</a>
                        <button type="submit" :disabled="!cuadrado || {{ $cuentas->isEmpty() ? 'true' : 'false' }}"
                                class="rounded-xl bg-brand px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">
                            Guardar borrador
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
