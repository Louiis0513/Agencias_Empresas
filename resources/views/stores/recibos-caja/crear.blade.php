@php
    use App\Models\ComprobanteIngreso;
    $modos = [
        ComprobanteIngreso::MODO_ABONO => 'Pago o abono a deuda',
        ComprobanteIngreso::MODO_ANTICIPO => 'Anticipo',
        ComprobanteIngreso::MODO_OTRO_INGRESO => 'Otro ingreso',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">Crear recibo de caja — {{ $store->name }}</h2>
            <a href="{{ route('stores.contabilidad.recibos-caja', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">Tipos RC</a>
        </div>
    </x-slot>

    <div class="py-8" x-data="reciboCajaForm({
        modoInicial: @js(old('modo', 'abono')),
        tipoId: @js((string) old('tipo_comprobante_id', $tipoDefault?->id)),
        terceroId: @js((string) old('tercero_id', '')),
        bolsilloId: @js((string) old('bolsillo_id', data_get($formasPago->first(), 'id', ''))),
        cuentasUrl: @js(route('stores.recibos-caja.cuentas-pendientes', $store)),
        tipos: @js($tiposRc->map(fn ($t) => [
            'id' => (string) $t->id,
            'codigo' => $t->codigo,
            'nombre' => $t->nombre,
            'siguiente' => $t->siguiente_numero,
            'auto' => (bool) $t->numeracion_automatica,
            'maneja_centro' => (bool) $t->maneja_centro_costos,
            'exige_centro' => (bool) $t->exigeCentroCostos(),
            'centro_default' => $t->centro_costo_default_id ? (string) $t->centro_costo_default_id : '',
        ])->values()),
        formas: @js($formasPago),
        valorInicial: @js((float) old('valor_recibido', 0)),
    })">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('error'))
                <div class="rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('stores.recibos-caja.store', $store) }}" class="space-y-4" @submit="prepararEnvio">
                @csrf
                <input type="hidden" name="bolsillo_id" :value="bolsilloId">
                <input type="hidden" name="valor_recibido" :value="valorRecibido">

                <div class="bg-dark-card border border-white/5 rounded-xl p-4">
                    <p class="text-sm text-gray-400 mb-3">Tipo de registro</p>
                    <div class="flex flex-wrap gap-4">
                        @foreach($modos as $valor => $etiqueta)
                            <label class="inline-flex items-center gap-2 text-sm text-gray-200 cursor-pointer">
                                <input type="radio" name="modo" value="{{ $valor }}" x-model="modo" class="text-brand border-white/20 bg-white/5">
                                {{ $etiqueta }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="bg-dark-card border border-white/5 rounded-xl p-5 space-y-4">
                    <h3 class="text-white font-semibold">Datos generales</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Fecha elaboración *</label>
                            <input type="date" name="date" value="{{ old('date', $fechaHoy) }}" required
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Tipo de documento *</label>
                            <select name="tipo_comprobante_id" x-model="tipoId" required
                                    class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                @foreach($tiposRc as $tipo)
                                    <option value="{{ $tipo->id }}">RC-{{ $tipo->codigo }} — {{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Consecutivo</label>
                            <input type="text" :value="consecutivoPreview" readonly
                                   class="w-full rounded-md border-white/10 bg-white/10 text-gray-300 font-mono">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">
                                Cliente <span x-show="modo !== 'otro_ingreso'">*</span>
                            </label>
                            <select name="tercero_id" x-model="terceroId" @change="cargarCuentas()"
                                    class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                                    :required="modo !== 'otro_ingreso'">
                                <option value="">Busca / elige cliente</option>
                                @foreach($clientes as $cli)
                                    <option value="{{ $cli->id }}">{{ $cli->nombre }} @if($cli->numero_identificacion) ({{ $cli->numero_identificacion }}) @endif</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-sm text-sky-400" x-show="modo === 'abono' || modo === 'anticipo'" x-cloak>
                                Saldo actual COP: <span class="font-mono" x-text="formato(saldoActual)"></span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Forma de pago *</label>
                            <select x-model="bolsilloId" required
                                    class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                <option value="">Elige forma de pago</option>
                                <template x-for="f in formas" :key="f.id">
                                    <option :value="f.id" x-text="f.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div x-show="tipoActual?.maneja_centro" x-cloak class="md:w-1/2">
                        <label class="block text-sm text-gray-400 mb-1">
                            Centro de costos <span x-show="tipoActual?.exige_centro">*</span>
                        </label>
                        <select name="centro_costo_id" x-model="centroCostoId"
                                class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                                :required="!!tipoActual?.exige_centro">
                            <option value="">Elige subcentro</option>
                            @foreach($centros as $centro)
                                <optgroup label="{{ $centro['codigo'] }} — {{ $centro['nombre'] }}">
                                    @foreach($centro['subcentros'] as $sub)
                                        <option value="{{ $sub['id'] }}">{{ $sub['codigo'] }} — {{ $sub['nombre'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Abono: valor + tabla FIFO --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-5 space-y-4" x-show="modo === 'abono'" x-cloak>
                    <div class="flex flex-wrap justify-between gap-4 items-end">
                        <div>
                            <h3 class="text-white font-semibold">Cuentas por cobrar</h3>
                            <p class="text-sm text-gray-400 mt-1">Al ingresar el valor se reparte por vencimiento. Desmarca o ajusta abonos; el resto queda como saldo a favor.</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Valor recibido *</label>
                            <input type="number" step="0.01" min="0" x-model.number="valorRecibido"
                                   @input="repartirFifo()"
                                   class="w-44 rounded-md border-white/10 bg-white/5 text-gray-100 text-right font-mono text-lg">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-white/10 text-gray-400 text-xs uppercase">
                                <tr>
                                    <th class="px-2 py-2 w-10"></th>
                                    <th class="px-2 py-2 text-left">Documento</th>
                                    <th class="px-2 py-2 text-left">Vence</th>
                                    <th class="px-2 py-2 text-right">Saldo</th>
                                    <th class="px-2 py-2 text-right">Pago o abono</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="cargandoCuentas">
                                    <tr><td colspan="5" class="px-2 py-6 text-center text-gray-500">Cargando cartera…</td></tr>
                                </template>
                                <template x-if="!cargandoCuentas && cuotas.length === 0">
                                    <tr><td colspan="5" class="px-2 py-6 text-center text-gray-500">El cliente no tiene documentos con saldos pendientes.</td></tr>
                                </template>
                                <template x-for="(c, idx) in cuotas" :key="c.account_receivable_cuota_id">
                                    <tr class="border-b border-white/5" :class="!c.seleccionada ? 'opacity-60' : ''">
                                        <td class="px-2 py-2">
                                            <input type="checkbox" x-model="c.seleccionada"
                                                   :disabled="!puedeEditarAbonos"
                                                   @change="onToggleCuota(c)"
                                                   class="rounded border-white/20 bg-white/5 text-brand">
                                        </td>
                                        <td class="px-2 py-2 text-gray-200" x-text="c.label"></td>
                                        <td class="px-2 py-2 text-gray-400" x-text="c.due_date || '—'"></td>
                                        <td class="px-2 py-2 text-right font-mono text-gray-300" x-text="formato(c.pending)"></td>
                                        <td class="px-2 py-2 text-right">
                                            <input type="number" step="0.01" min="0" :max="c.pending"
                                                   x-model.number="c.abono"
                                                   :disabled="!puedeEditarAbonos || !c.seleccionada"
                                                   @change="onAbonoManual(c)"
                                                   class="w-36 rounded-md border-white/10 bg-white/5 text-gray-100 text-right font-mono disabled:opacity-40">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <template x-for="(c, idx) in aplicacionesParaEnvio" :key="'ap-'+c.account_receivable_cuota_id">
                        <div>
                            <input type="hidden" :name="'aplicaciones['+idx+'][account_receivable_id]'" :value="c.account_receivable_id">
                            <input type="hidden" :name="'aplicaciones['+idx+'][account_receivable_cuota_id]'" :value="c.account_receivable_cuota_id">
                            <input type="hidden" :name="'aplicaciones['+idx+'][amount]'" :value="c.abono">
                        </div>
                    </template>
                    <div class="flex flex-col items-end gap-1 text-sm">
                        <div class="text-gray-400">Pagos o abonos: <span class="font-mono text-gray-200" x-text="formato(totalAplicaciones)"></span></div>
                        <div class="text-gray-400">Saldo a favor u otros: <span class="font-mono text-emerald-400" x-text="formato(saldoAFavor)"></span></div>
                        <div class="text-white font-semibold">Total recibido: <span class="font-mono" x-text="formato(valorRecibido)"></span></div>
                    </div>
                </div>

                {{-- Anticipo / Otro: solo valor --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-5" x-show="modo !== 'abono'" x-cloak>
                    <div class="flex flex-wrap justify-between gap-4 items-end">
                        <div>
                            <h3 class="text-white font-semibold" x-text="modo === 'anticipo' ? 'Anticipo' : 'Otro ingreso'"></h3>
                            <p class="text-sm text-gray-400 mt-1" x-show="modo === 'anticipo'">El valor completo queda como anticipo del cliente.</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Valor recibido *</label>
                            <input type="number" step="0.01" min="0.01" x-model.number="valorRecibido"
                                   class="w-44 rounded-md border-white/10 bg-white/5 text-gray-100 text-right font-mono text-lg">
                        </div>
                    </div>
                </div>

                <div class="bg-dark-card border border-white/5 rounded-xl p-5 space-y-3">
                    <label class="block text-sm text-gray-400 mb-1">
                        <span x-text="modo === 'otro_ingreso' ? 'Concepto de ingreso *' : 'Observaciones'"></span>
                    </label>
                    <textarea name="notes" rows="2"
                              class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                              :placeholder="modo === 'otro_ingreso' ? 'Ej. Reembolso, ingreso administrativo…' : 'Comentarios adicionales'"
                              :required="modo === 'otro_ingreso'">{{ old('notes') }}</textarea>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <a href="{{ route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'ingresos']) }}" class="text-gray-400 hover:text-white text-sm">Cancelar</a>
                    <button type="submit" class="px-5 py-2.5 bg-brand text-white rounded-xl font-semibold"
                            :disabled="!puedeGuardar">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function reciboCajaForm(cfg) {
            return {
                modo: cfg.modoInicial,
                tipoId: cfg.tipoId,
                terceroId: cfg.terceroId,
                bolsilloId: cfg.bolsilloId || '',
                centroCostoId: '',
                tipos: cfg.tipos,
                formas: cfg.formas,
                cuotas: [],
                saldoActual: 0,
                cargandoCuentas: false,
                valorRecibido: cfg.valorInicial || 0,
                get tipoActual() {
                    return this.tipos.find(t => t.id === String(this.tipoId)) || null;
                },
                get consecutivoPreview() {
                    const t = this.tipoActual;
                    if (!t) return '—';
                    return (t.auto ? '≈ ' : '') + 'RC-' + String(t.siguiente).padStart(4, '0');
                },
                get puedeEditarAbonos() {
                    return (parseFloat(this.valorRecibido) || 0) > 0;
                },
                get totalAplicaciones() {
                    return this.cuotas.reduce((s, c) => s + (parseFloat(c.abono) || 0), 0);
                },
                get saldoAFavor() {
                    return Math.max(0, Math.round(((parseFloat(this.valorRecibido) || 0) - this.totalAplicaciones) * 100) / 100);
                },
                get aplicacionesParaEnvio() {
                    return this.cuotas.filter(c => c.seleccionada && (parseFloat(c.abono) || 0) > 0);
                },
                get puedeGuardar() {
                    const v = parseFloat(this.valorRecibido) || 0;
                    if (v <= 0 || !this.bolsilloId) return false;
                    if (this.modo === 'abono' && this.totalAplicaciones - v > 0.015) return false;
                    return true;
                },
                formato(n) {
                    return (parseFloat(n) || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                round2(n) {
                    return Math.round((parseFloat(n) || 0) * 100) / 100;
                },
                async cargarCuentas() {
                    this.cuotas = [];
                    this.saldoActual = 0;
                    if (!this.terceroId) return;
                    this.cargandoCuentas = true;
                    try {
                        const url = cfg.cuentasUrl + '?tercero_id=' + encodeURIComponent(this.terceroId);
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const json = await res.json();
                        this.saldoActual = parseFloat(json.saldo_actual) || 0;
                        this.cuotas = (json.data || []).map(c => ({
                            ...c,
                            abono: 0,
                            seleccionada: false,
                        }));
                        if (this.modo === 'abono') this.repartirFifo();
                    } catch (e) {
                        this.cuotas = [];
                        this.saldoActual = 0;
                    } finally {
                        this.cargandoCuentas = false;
                    }
                },
                repartirFifo() {
                    let resto = this.round2(this.valorRecibido);
                    const ordenadas = [...this.cuotas].sort((a, b) => {
                        const da = a.due_date || '9999-12-31';
                        const db = b.due_date || '9999-12-31';
                        if (da !== db) return da < db ? -1 : 1;
                        return (a.account_receivable_cuota_id || 0) - (b.account_receivable_cuota_id || 0);
                    });
                    this.cuotas.forEach(c => {
                        c.abono = 0;
                        c.seleccionada = false;
                    });
                    if (resto <= 0) return;
                    for (const c of ordenadas) {
                        if (resto <= 0) break;
                        const pending = this.round2(c.pending);
                        const abono = this.round2(Math.min(pending, resto));
                        if (abono > 0) {
                            c.abono = abono;
                            c.seleccionada = true;
                            resto = this.round2(resto - abono);
                        }
                    }
                },
                onToggleCuota(c) {
                    if (!c.seleccionada) {
                        c.abono = 0;
                    } else if ((parseFloat(c.abono) || 0) <= 0) {
                        // Al marcar sin monto: asignar lo que quede de saldo a favor (hasta pending)
                        const disponible = this.round2(this.saldoAFavor);
                        const abono = this.round2(Math.min(parseFloat(c.pending) || 0, disponible));
                        c.abono = abono > 0 ? abono : 0;
                        if (c.abono <= 0) c.seleccionada = false;
                    }
                },
                onAbonoManual(c) {
                    let abono = this.round2(c.abono);
                    const pending = this.round2(c.pending);
                    if (abono < 0) abono = 0;
                    if (abono > pending) abono = pending;
                    // Tope: no superar valor recibido (otros abonos + este)
                    const otros = this.cuotas
                        .filter(x => x !== c)
                        .reduce((s, x) => s + (parseFloat(x.abono) || 0), 0);
                    const maxPorValor = this.round2((parseFloat(this.valorRecibido) || 0) - otros);
                    if (abono > maxPorValor) abono = Math.max(0, maxPorValor);
                    c.abono = abono;
                    c.seleccionada = abono > 0;
                },
                prepararEnvio(e) {
                    if (!this.puedeGuardar) {
                        e.preventDefault();
                        alert('Indica forma de pago y un valor recibido válido.');
                    }
                },
                init() {
                    this.$watch('tipoId', () => {
                        const t = this.tipoActual;
                        if (t?.centro_default) this.centroCostoId = t.centro_default;
                    });
                    this.$watch('modo', (m) => {
                        if (m === 'abono' && this.terceroId) this.cargarCuentas();
                        if (m !== 'abono') {
                            this.cuotas.forEach(c => { c.abono = 0; c.seleccionada = false; });
                        }
                    });
                    if (this.terceroId) this.cargarCuentas();
                    const t = this.tipoActual;
                    if (t?.centro_default) this.centroCostoId = t.centro_default;
                    if (this.formas.length && !this.bolsilloId) {
                        this.bolsilloId = this.formas[0].id;
                    }
                }
            }
        }
    </script>
</x-app-layout>
