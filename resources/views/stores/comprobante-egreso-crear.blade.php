<x-app-layout>
    @livewire('select-account-payable-modal', ['storeId' => $store->id])
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Nuevo Comprobante de Egreso - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.comprobantes-egreso.index', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Comprobantes
            </a>
        </div>
    </x-slot>

    @php
        $oldDestinos = old('destinos', []);
        $itemsLibresInit = array_values(array_filter($oldDestinos, fn($d) => empty($d['account_payable_id'] ?? null)));
        $itemsLibresInit = !empty($itemsLibresInit) ? $itemsLibresInit : [['concepto' => '', 'beneficiario' => '', 'amount' => '']];
    @endphp
    <div class="py-12" x-data="comprobanteEgresoFlow()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('stores.comprobantes-egreso.store', $store) }}" id="form-comprobante"
                  x-ref="form"
                  @submit="onSubmit($event)">
                @csrf
                <input type="hidden" name="proveedor_id" :value="(proveedorId && proveedorId !== 'null') ? proveedorId : ''">

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                                <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                                <input type="text" name="notes" value="{{ old('notes') }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Opcional">
                            </div>
                        </div>

                        {{-- Fase A y B: Buscar factura (define proveedor) + Añadir más --}}
                        <div class="border-l-4 border-indigo-500 pl-4">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">¿Qué facturas pago?</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                Selecciona la primera factura para definir el proveedor. Luego puedes agregar más del mismo proveedor.
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                {{-- Estado inicial: buscar factura --}}
                                <button type="button"
                                        x-show="cuentasSeleccionadas.length === 0"
                                        @click="openBuscarFacturaModal()"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                                    Buscar factura por número o proveedor
                                </button>
                                {{-- Modo pago a proveedor (con facturas): añadir más cuentas --}}
                                <button type="button"
                                        x-show="cuentasSeleccionadas.length > 0"
                                        @click="openAddCuentaModal()"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                                    + Añadir cuenta por pagar
                                </button>
                                <span x-show="proveedorId" x-cloak class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    Proveedor: <span x-text="proveedorNombre || '—'"></span>
                                </span>
                            </div>
                            <div x-show="cuentasSeleccionadas.length === 0" class="text-sm text-gray-500 dark:text-gray-400 py-4">
                                No hay facturas. Haz clic en "Buscar factura" para pagar a un proveedor, o complete los gastos directos más abajo.
                            </div>
                            <div x-show="cuentasSeleccionadas.length > 0" class="space-y-2">
                                <template x-for="(cuenta, i) in cuentasSeleccionadas" :key="cuenta.id">
                                    <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                        <span class="flex-1 min-w-[200px] text-sm text-gray-700 dark:text-gray-300">
                                            Compra #<span x-text="cuenta.purchase_id"></span> - Saldo: <span x-text="formatNumber(cuenta.balance)"></span>
                                            <span x-show="cuenta.due_date" class="text-gray-500">(Vence: <span x-text="cuenta.due_date"></span>)</span>
                                        </span>
                                        <input type="number" step="0.01" min="0" :max="cuenta.balance"
                                               x-model="cuenta.amount"
                                               placeholder="Monto"
                                               class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                        <button type="button" @click="removeCuentaSeleccionada(cuenta.id)"
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">✕ Quitar</button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Gasto directo - Ítems libres (sin factura). x-if elimina del DOM cuando hay facturas, así no se envían inputs vacíos. --}}
                        <template x-if="!proveedorId">
                        <div class="border-l-4 border-indigo-500 pl-4">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">¿Qué gastos registro? (Gasto directo)</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Agrega ítems libres (taxi, café, etc.)</p>
                            <p class="text-xs font-medium text-amber-600 dark:text-amber-400 mb-3">Debe indicar el concepto para cada ítem de gasto directo.</p>
                            <div class="space-y-2" x-ref="itemsLibresContainer">
                                <div class="flex flex-wrap gap-2 px-3 pb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    <span class="flex-1 min-w-[150px]">Concepto *</span>
                                    <span class="w-32">Beneficiario</span>
                                    <span class="w-28">Monto *</span>
                                    <span class="w-8"></span>
                                </div>
                                <template x-for="(item, i) in itemsLibres" :key="i">
                                    <div class="flex flex-wrap gap-2 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                        <input type="text" x-model="item.concepto" placeholder="Ej: Taxi a la oficina"
                                               class="flex-1 min-w-[150px] rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm"
                                               :name="proveedorId ? '' : 'destinos[' + i + '][concepto]'"
                                               :disabled="!!proveedorId">
                                        <input type="text" x-model="item.beneficiario" placeholder="Beneficiario (opcional)"
                                               class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm"
                                               :name="proveedorId ? '' : 'destinos[' + i + '][beneficiario]'"
                                               :disabled="!!proveedorId">
                                        <input type="number" x-model="item.amount" step="0.01" min="0.01" placeholder="Monto"
                                               class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm"
                                               :name="proveedorId ? '' : 'destinos[' + i + '][amount]'"
                                               :disabled="!!proveedorId">
                                        <button type="button" @click="removeItemLibre(i)" class="text-red-600 hover:text-red-800 text-sm"
                                                x-show="itemsLibres.length > 1">✕</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="addItemLibre()"
                                    class="mt-2 text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                + Agregar ítem libre
                            </button>
                        </div>
                        </template>

                        {{-- Fase C: ¿Con qué pago? --}}
                        <div class="border-l-4 border-indigo-500 pl-4" x-show="totalDestinos > 0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Fase C: ¿De qué bolsillos sale el dinero?</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">La suma debe coincidir con el total a pagar: <strong x-text="formatNumber(totalDestinos)"></strong></p>
                            <div id="origenes-container" class="space-y-2">
                                @php $origenesOld = old('origenes', [['bolsillo_id' => '', 'reference' => '', 'amount' => '']]); @endphp
                                @foreach($origenesOld as $i => $o)
                                <div class="origen-row flex gap-2">
                                    <select name="origenes[{{ $i }}][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                        <option value="">Seleccionar bolsillo</option>
                                        @foreach($bolsillos as $b)
                                            <option value="{{ $b->id }}" {{ (string)($o['bolsillo_id'] ?? '') === (string)$b->id ? 'selected' : '' }}>{{ $b->name }} ({{ number_format($b->saldo, 2) }})</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="origenes[{{ $i }}][reference]" value="{{ $o['reference'] ?? '' }}" class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Cheque/Trans.">
                                    <input type="number" name="origenes[{{ $i }}][amount]" step="0.01" min="0.01" value="{{ $o['amount'] ?? '' }}" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required placeholder="Monto">
                                    <button type="button" class="remove-origen text-red-600 hover:text-red-800 text-sm {{ count($origenesOld) > 1 ? '' : 'hidden' }}">✕</button>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" id="add-origen" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">+ Agregar bolsillo</button>
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400" x-show="totalDestinos > 0 && Math.abs(totalDestinos - totalOrigenes) > 0.01">
                                La suma de orígenes (<span x-text="formatNumber(totalOrigenes)"></span>) debe coincidir con el total.
                            </p>
                        </div>

                        <div class="pt-4">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="totalDestinos <= 0">
                                Registrar Comprobante
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function comprobanteEgresoFlow() {
            const storeId = {{ $store->id }};
            const bolsillos = @json($bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'saldo' => $b->saldo]));
            @php
                $oldProv = ($proveedorIdInit !== null && $proveedorIdInit !== '') ? $proveedores->firstWhere('id', $proveedorIdInit) : null;
                $initProvNombre = $oldProv?->nombre ?? (($proveedorIdInit === null || $proveedorIdInit === '') && !empty($cuentasSeleccionadasInit) ? 'Sin proveedor' : '');
                $initProvId = empty($cuentasSeleccionadasInit) ? '' : ($proveedorIdInit === null || $proveedorIdInit === '' ? 'null' : (string)$proveedorIdInit);
            @endphp
            const initProveedorNombre = @json($initProvNombre);
            const cuentasSeleccionadasInit = @json($cuentasSeleccionadasInit ?? []);
            const proveedorIdInit = @json($initProvId);

            return {
                proveedorId: proveedorIdInit,
                proveedorNombre: initProveedorNombre,
                cuentasSeleccionadas: cuentasSeleccionadasInit,
                itemsLibres: @json($itemsLibresInit),

                get totalDestinos() {
                    if (this.proveedorId) {
                        return this.cuentasSeleccionadas
                            .filter(c => parseFloat(c.amount) > 0)
                            .reduce((sum, c) => sum + parseFloat(c.amount || 0), 0);
                    }
                    return this.itemsLibres.reduce((sum, i) => sum + parseFloat(i.amount || 0), 0);
                },
                get totalOrigenes() {
                    return Array.from(document.querySelectorAll('#origenes-container input[name*="[amount]"]'))
                        .reduce((sum, el) => sum + parseFloat(el.value || 0), 0);
                },

                init() {
                    this.bindOrigenes();
                    this.bindLivewireEvents();
                },

                openBuscarFacturaModal() {
                    Livewire.dispatch('open-select-account-payable-for-comprobante', {});
                },

                openAddCuentaModal() {
                    if (!this.proveedorId || this.cuentasSeleccionadas.length === 0) return;
                    const provId = this.proveedorId === 'null' ? null : parseInt(this.proveedorId);
                    Livewire.dispatch('open-select-account-payable-for-comprobante', {
                        proveedor_id: provId,
                        selected_ids: this.cuentasSeleccionadas.map(c => c.id)
                    });
                },

                bindLivewireEvents() {
                    Livewire.on('account-payable-selected-for-comprobante', (payload) => {
                        const p = Array.isArray(payload) ? payload[0] : payload;
                        if (!p?.id) return;
                        const pProvId = p.proveedorId == null ? 'null' : String(p.proveedorId);
                        if (this.proveedorId && pProvId !== this.proveedorId) {
                            alert('Solo puedes agregar facturas del mismo proveedor (' + this.proveedorNombre + ').');
                            return;
                        }
                        if (!this.cuentasSeleccionadas.some(c => c.id == p.id)) {
                            if (!this.proveedorId) {
                                this.proveedorId = pProvId;
                                this.proveedorNombre = (p.proveedorId == null ? 'Sin proveedor' : (p.proveedorNombre || ''));
                            }
                            this.cuentasSeleccionadas.push({
                                id: p.id,
                                purchase_id: p.purchaseId,
                                balance: p.balance,
                                due_date: p.dueDate || null,
                                amount: parseFloat(p.balance) || 0
                            });
                        }
                    });
                },

                removeCuentaSeleccionada(id) {
                    this.cuentasSeleccionadas = this.cuentasSeleccionadas.filter(c => c.id != id);
                    if (this.cuentasSeleccionadas.length === 0) {
                        this.proveedorId = '';
                        this.proveedorNombre = '';
                    }
                },

                addItemLibre() {
                    this.itemsLibres.push({ concepto: '', beneficiario: '', amount: '' });
                },
                removeItemLibre(i) {
                    if (this.itemsLibres.length > 1) this.itemsLibres.splice(i, 1);
                },

                formatNumber(n) {
                    return parseFloat(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 2 });
                },

                onSubmit(e) {
                    if (this.proveedorId) {
                        e.preventDefault();
                        const selected = this.cuentasSeleccionadas.filter(c => parseFloat(c.amount) > 0);
                        if (selected.length === 0) {
                            alert('Agrega al menos una factura con monto desde el modal "Añadir cuenta por pagar".');
                            return;
                        }
                        const form = this.$refs.form;
                        form.querySelectorAll('input[name^="destinos"]').forEach(el => el.remove());
                        selected.forEach((c, i) => {
                            const inp1 = document.createElement('input');
                            inp1.type = 'hidden';
                            inp1.name = `destinos[${i}][account_payable_id]`;
                            inp1.value = c.id;
                            const inp2 = document.createElement('input');
                            inp2.type = 'hidden';
                            inp2.name = `destinos[${i}][amount]`;
                            inp2.value = c.amount;
                            form.appendChild(inp1);
                            form.appendChild(inp2);
                        });
                        form.submit();
                    }
                },

                bindOrigenes() {
                    const container = document.getElementById('origenes-container');
                    const addBtn = document.getElementById('add-origen');
                    let origenIndex = container.querySelectorAll('.origen-row').length;

                    addBtn?.addEventListener('click', () => {
                        const row = document.createElement('div');
                        row.className = 'origen-row flex gap-2';
                        row.innerHTML = `
                            <select name="origenes[${origenIndex}][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                <option value="">Seleccionar bolsillo</option>
                                ${bolsillos.map(b => `<option value="${b.id}">${b.name} (${parseFloat(b.saldo).toFixed(2)})</option>`).join('')}
                            </select>
                            <input type="text" name="origenes[${origenIndex}][reference]" class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Cheque/Trans.">
                            <input type="number" name="origenes[${origenIndex}][amount]" step="0.01" min="0.01" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required placeholder="Monto">
                            <button type="button" class="remove-origen text-red-600 hover:text-red-800 text-sm">✕</button>
                        `;
                        container.appendChild(row);
                        row.querySelector('.remove-origen').addEventListener('click', () => {
                            if (container.querySelectorAll('.origen-row').length > 1) row.remove();
                        });
                        document.querySelectorAll('.remove-origen').forEach(btn => btn.classList.remove('hidden'));
                        origenIndex++;
                    });

                    document.querySelectorAll('.remove-origen').forEach(btn => {
                        btn.addEventListener('click', function() {
                            if (container.querySelectorAll('.origen-row').length > 1) this.closest('.origen-row').remove();
                        });
                    });
                }
            };
        }
    </script>
</x-app-layout>
