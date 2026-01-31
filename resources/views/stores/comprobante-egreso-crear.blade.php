<x-app-layout>
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

    @livewire('select-account-payable-modal', ['storeId' => $store->id])

    <div class="py-12" x-data="comprobanteDestinoSelection()">
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('stores.comprobantes-egreso.store', $store) }}" id="form-comprobante">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                                <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                                <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Opcional">
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Destinos (a qué se paga)</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Cuentas por pagar o gastos directos (taxi, café, etc.)</p>
                            <div id="destinos-container">
                                @php $destinosOld = old('destinos', [['type' => 'CUENTA_POR_PAGAR', 'account_payable_id' => '', 'concepto' => '', 'beneficiario' => '', 'amount' => '']]); @endphp
                                @foreach($destinosOld as $i => $d)
                                <div class="destino-row flex flex-wrap gap-2 mb-2 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                    <select name="destinos[{{ $i }}][type]" class="destino-type w-40 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        <option value="CUENTA_POR_PAGAR" {{ ($d['type'] ?? '') == 'CUENTA_POR_PAGAR' ? 'selected' : '' }}>Cuenta por pagar</option>
                                        <option value="GASTO_DIRECTO" {{ ($d['type'] ?? '') == 'GASTO_DIRECTO' ? 'selected' : '' }}>Gasto directo</option>
                                    </select>
                                    <div class="destino-cuenta flex-1 min-w-[200px] {{ ($d['type'] ?? 'CUENTA_POR_PAGAR') == 'GASTO_DIRECTO' ? 'hidden' : '' }}" data-destino-index="{{ $i }}">
                                        @php
                                            $apSelected = !empty($d['account_payable_id'])
                                                ? \App\Models\AccountPayable::with('purchase.proveedor')->find($d['account_payable_id'])
                                                : null;
                                        @endphp
                                        <input type="hidden" name="destinos[{{ $i }}][account_payable_id]" value="{{ $d['account_payable_id'] ?? '' }}" class="destino-account-payable-id">
                                        <div class="destino-cuenta-display flex gap-2 items-center">
                                            <span class="destino-cuenta-text text-sm text-gray-700 dark:text-gray-300 flex-1 min-w-0 truncate">
                                                @if($apSelected)
                                                    Compra #{{ $apSelected->purchase->id }} - {{ $apSelected->purchase->proveedor?->nombre }} (Saldo: {{ number_format($apSelected->balance, 2) }})
                                                @else
                                                    <span class="text-gray-500">Ninguna seleccionada</span>
                                                @endif
                                            </span>
                                            <button type="button" class="btn-buscar-cuenta shrink-0 px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                                                Buscar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="destino-gasto flex-1 min-w-[200px] space-y-1 {{ ($d['type'] ?? 'CUENTA_POR_PAGAR') == 'CUENTA_POR_PAGAR' ? 'hidden' : '' }}">
                                        <input type="text" name="destinos[{{ $i }}][concepto]" value="{{ $d['concepto'] ?? '' }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Concepto (ej: Taxi)">
                                        <input type="text" name="destinos[{{ $i }}][beneficiario]" value="{{ $d['beneficiario'] ?? '' }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Beneficiario (ej: Juan Pérez)">
                                    </div>
                                    <input type="number" name="destinos[{{ $i }}][amount]" step="0.01" min="0.01" placeholder="Monto" value="{{ $d['amount'] ?? '' }}" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                    <button type="button" class="remove-destino text-red-600 hover:text-red-800 text-sm {{ count($destinosOld) > 1 ? '' : 'hidden' }}">✕</button>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" id="add-destino" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar destino</button>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Orígenes (de qué bolsillos sale el dinero)</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Puede incluir referencia (cheque, transacción bancaria)</p>
                            <div id="origenes-container">
                                @php $origenesOld = old('origenes', [['bolsillo_id' => '', 'reference' => '', 'amount' => '']]); @endphp
                                @foreach($origenesOld as $i => $o)
                                <div class="origen-row flex gap-2 mb-2">
                                    <select name="origenes[{{ $i }}][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                        <option value="">Seleccionar bolsillo</option>
                                        @foreach($bolsillos as $b)
                                            <option value="{{ $b->id }}" {{ (string)($o['bolsillo_id'] ?? '') === (string)$b->id ? 'selected' : '' }}>{{ $b->name }} ({{ number_format($b->saldo, 2) }})</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="origenes[{{ $i }}][reference]" value="{{ $o['reference'] ?? '' }}" class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Cheque/Transacción">
                                    <input type="number" name="origenes[{{ $i }}][amount]" step="0.01" min="0.01" placeholder="Monto" value="{{ $o['amount'] ?? '' }}" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                    <button type="button" class="remove-origen text-red-600 hover:text-red-800 text-sm {{ count($origenesOld) > 1 ? '' : 'hidden' }}">✕</button>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" id="add-origen" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar bolsillo</button>
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-2">La suma de orígenes debe coincidir con la suma de destinos.</p>
                        </div>

                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Registrar Comprobante</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateDestinoWithAccountPayable(detail) {
            if (!detail || typeof detail !== 'object') return;
            const index = String(detail.destinoRowIndex ?? '');
            const cuentaDiv = document.querySelector(`.destino-cuenta[data-destino-index="${index}"]`);
            if (!cuentaDiv) return;
            const hiddenInput = cuentaDiv.querySelector('.destino-account-payable-id');
            const textSpan = cuentaDiv.querySelector('.destino-cuenta-text');
            if (hiddenInput && textSpan) {
                hiddenInput.value = detail.id ?? '';
                const proveedor = detail.proveedorNombre ?? '';
                const balance = parseFloat(detail.balance ?? 0).toFixed(2);
                const purchaseId = detail.purchaseId ?? '';
                textSpan.textContent = `Compra #${purchaseId} - ${proveedor} (Saldo: ${balance})`;
                textSpan.classList.remove('text-gray-500');
            }
        }

        function extractPayloadFromEvent(detail) {
            if (!detail) return null;
            if (Array.isArray(detail) && detail.length > 0) return detail[0];
            if (typeof detail === 'object' && detail !== null) return detail;
            return null;
        }

        window.comprobanteDestinoSelection = function() {
            return {};
        };

        document.addEventListener('livewire:init', function() {
            Livewire.on('account-payable-selected', function(detail) {
                const payload = extractPayloadFromEvent(detail);
                if (payload && typeof payload === 'object') {
                    updateDestinoWithAccountPayable(payload);
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const bolsillos = @json($bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'saldo' => $b->saldo]));

            let destinoIndex = {{ count($destinosOld) }};
            let origenIndex = {{ count($origenesOld) }};

            function toggleDestinoRow(row) {
                const type = row.querySelector('.destino-type').value;
                row.querySelector('.destino-cuenta').classList.toggle('hidden', type !== 'CUENTA_POR_PAGAR');
                row.querySelector('.destino-gasto').classList.toggle('hidden', type !== 'GASTO_DIRECTO');
                const cuentaDiv = row.querySelector('.destino-cuenta');
                if (cuentaDiv) {
                    cuentaDiv.querySelector('.destino-account-payable-id').disabled = type !== 'CUENTA_POR_PAGAR';
                    cuentaDiv.querySelector('.destino-account-payable-id').required = type === 'CUENTA_POR_PAGAR';
                }
                row.querySelector('.destino-gasto input[name*="concepto"]').required = type === 'GASTO_DIRECTO';
            }

            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-buscar-cuenta')) {
                    e.preventDefault();
                    const cuentaDiv = e.target.closest('.destino-cuenta');
                    const index = cuentaDiv?.getAttribute('data-destino-index') ?? '';
                    window.Livewire.dispatch('open-select-account-payable', { destinoRowIndex: String(index) });
                }
            });

            document.getElementById('add-destino').addEventListener('click', function() {
                const container = document.getElementById('destinos-container');
                const row = document.createElement('div');
                row.className = 'destino-row flex flex-wrap gap-2 mb-2 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg';
                row.innerHTML = `
                    <select name="destinos[${destinoIndex}][type]" class="destino-type w-40 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="CUENTA_POR_PAGAR">Cuenta por pagar</option>
                        <option value="GASTO_DIRECTO">Gasto directo</option>
                    </select>
                    <div class="destino-cuenta flex-1 min-w-[200px]" data-destino-index="${destinoIndex}">
                        <input type="hidden" name="destinos[${destinoIndex}][account_payable_id]" value="" class="destino-account-payable-id">
                        <div class="destino-cuenta-display flex gap-2 items-center">
                            <span class="destino-cuenta-text text-sm text-gray-700 dark:text-gray-300 flex-1 min-w-0 truncate text-gray-500">Ninguna seleccionada</span>
                            <button type="button" class="btn-buscar-cuenta shrink-0 px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Buscar</button>
                        </div>
                    </div>
                    <div class="destino-gasto hidden flex-1 min-w-[200px] space-y-1">
                        <input type="text" name="destinos[${destinoIndex}][concepto]" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Concepto (ej: Taxi)">
                        <input type="text" name="destinos[${destinoIndex}][beneficiario]" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Beneficiario">
                    </div>
                    <input type="number" name="destinos[${destinoIndex}][amount]" step="0.01" min="0.01" placeholder="Monto" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                    <button type="button" class="remove-destino text-red-600 hover:text-red-800 text-sm">✕</button>
                `;
                container.appendChild(row);
                row.querySelector('.destino-type').addEventListener('change', () => toggleDestinoRow(row));
                row.querySelector('.remove-destino').addEventListener('click', () => { if (container.children.length > 1) row.remove(); });
                toggleDestinoRow(row);
                destinoIndex++;
            });

            document.getElementById('add-origen').addEventListener('click', function() {
                const container = document.getElementById('origenes-container');
                const row = document.createElement('div');
                row.className = 'origen-row flex gap-2 mb-2';
                row.innerHTML = `
                    <select name="origenes[${origenIndex}][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                        <option value="">Seleccionar bolsillo</option>
                        ${bolsillos.map(b => `<option value="${b.id}">${b.name} (${parseFloat(b.saldo).toFixed(2)})</option>`).join('')}
                    </select>
                    <input type="text" name="origenes[${origenIndex}][reference]" class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Cheque/Transacción">
                    <input type="number" name="origenes[${origenIndex}][amount]" step="0.01" min="0.01" placeholder="Monto" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                    <button type="button" class="remove-origen text-red-600 hover:text-red-800 text-sm">✕</button>
                `;
                container.appendChild(row);
                row.querySelector('.remove-origen').addEventListener('click', () => { if (container.children.length > 1) row.remove(); });
                origenIndex++;
            });

            document.querySelectorAll('.destino-type').forEach(sel => {
                sel.addEventListener('change', function() { toggleDestinoRow(this.closest('.destino-row')); });
            });
            document.querySelectorAll('.destino-row').forEach(toggleDestinoRow);
            document.querySelectorAll('.remove-destino').forEach(btn => {
                btn.classList.remove('hidden');
                btn.addEventListener('click', function() {
                    if (document.getElementById('destinos-container').children.length > 1) this.closest('.destino-row').remove();
                });
            });
            document.querySelectorAll('.remove-origen').forEach(btn => {
                btn.classList.remove('hidden');
                btn.addEventListener('click', function() {
                    if (document.getElementById('origenes-container').children.length > 1) this.closest('.origen-row').remove();
                });
            });
        });
    </script>
</x-app-layout>
