<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center" @if(!$comprobante->isReversed()) x-data="anularComprobanteModal()" x-init="init()" @endif>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Comprobante {{ $comprobante->number }} - {{ $store->name }}
            </h2>
            <div class="flex items-center gap-3">
                @if(!$comprobante->isReversed())
                    <a href="{{ route('stores.comprobantes-egreso.edit', [$store, $comprobante]) }}"
                       class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">
                        Editar comprobante
                    </a>
                    <button type="button"
                            @click="$refs.modalAnular?.showModal()"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                        Anular comprobante
                    </button>
                @endif
                <a href="{{ route('stores.comprobantes-egreso.index', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    ← Volver a Comprobantes
                </a>
            </div>
            @if(!$comprobante->isReversed())
            {{-- Modal Anular (dentro del header para compartir x-data) --}}
            <dialog x-ref="modalAnular" class="rounded-lg shadow-xl max-w-lg w-full p-0 backdrop:bg-black/50"
                    @click.self="$refs.modalAnular.close()">
                <div class="bg-white dark:bg-gray-800 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Anular comprobante {{ $comprobante->number }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Indica a qué bolsillos se devolverá el dinero del reverso. La suma debe coincidir con el total: <strong>{{ number_format($comprobante->total_amount, 2) }}</strong>
                    </p>
                    <form method="POST" action="{{ route('stores.comprobantes-egreso.anular', [$store, $comprobante]) }}" id="form-anular">
                        @csrf
                        <div class="space-y-2 mb-4" id="origenes-reverso-container">
                            @foreach($comprobante->origenes as $i => $o)
                            <div class="origen-reverso-row flex gap-2 items-center">
                                <select name="origenes[{{ $i }}][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                                    <option value="">Seleccionar bolsillo</option>
                                    @foreach($bolsillos as $b)
                                        <option value="{{ $b->id }}" {{ $o->bolsillo_id == $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ number_format($b->saldo, 2) }})</option>
                                    @endforeach
                                </select>
                                <input type="text" name="origenes[{{ $i }}][reference]" value="{{ $o->reference }}" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ref.">
                                <input type="number" name="origenes[{{ $i }}][amount]" step="0.01" min="0.01" value="{{ $o->amount }}" class="w-24 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm origen-amount" required placeholder="Monto">
                                <button type="button" class="remove-origen-reverso text-red-600 hover:text-red-800 text-sm {{ $comprobante->origenes->count() > 1 ? '' : 'hidden' }}">✕</button>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" id="add-origen-reverso" class="mb-4 text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">+ Agregar bolsillo</button>
                        <p class="text-xs text-amber-600 dark:text-amber-400 mb-4" x-show="!sumaCoincide" x-transition>
                            La suma de los montos (<span x-text="sumaOrigenes.toFixed(2)"></span>) debe coincidir con el total ({{ number_format($comprobante->total_amount, 2) }}).
                        </p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="$refs.modalAnular.close()" class="px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                                Cancelar
                            </button>
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium disabled:opacity-50"
                                    :disabled="!sumaCoincide">
                                Confirmar anulación
                            </button>
                        </div>
                    </form>
                </div>
            </dialog>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Número</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobante->number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Fecha</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobante->payment_date->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Monto total</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($comprobante->total_amount, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">A quién</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobante->beneficiary_name ?? '—' }}</p>
                        </div>
                        @if($comprobante->proveedor)
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Proveedor</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobante->proveedor->nombre }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Tipo</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                @if($comprobante->type == 'PAGO_CUENTA') Pago cuenta
                                @elseif($comprobante->type == 'GASTO_DIRECTO') Gasto directo
                                @else Mixto
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Registrado por</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobante->user->name ?? '—' }}</p>
                        </div>
                        @if($comprobante->notes)
                            <div class="md:col-span-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Notas</p>
                                <p class="text-gray-900 dark:text-gray-100">{{ $comprobante->notes }}</p>
                            </div>
                        @endif
                        @if($comprobante->isReversed())
                            <div class="md:col-span-2">
                                <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Revertido</span>
                            </div>
                        @endif
                    </div>

                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Destinos (a qué se destinó el dinero)</h3>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Detalle</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($comprobante->destinos as $d)
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            @if($d->isCuentaPorPagar())
                                                <span class="text-blue-600">Cuenta por pagar</span>
                                            @else
                                                <span class="text-green-600">Gasto directo</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            @if($d->isCuentaPorPagar() && $d->accountPayable)
                                                <a href="{{ route('stores.accounts-payables.show', [$store, $d->accountPayable]) }}" class="text-indigo-600 hover:text-indigo-800">
                                                    Compra #{{ $d->accountPayable->purchase->id ?? $d->account_payable_id }} - {{ $d->accountPayable->purchase->proveedor->nombre ?? 'Proveedor' }}
                                                </a>
                                            @else
                                                {{ $d->concepto ?? 'Gasto' }} @if($d->beneficiario)({{ $d->beneficiario }})@endif
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm font-medium text-right text-gray-900 dark:text-gray-100">{{ number_format($d->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Orígenes (de qué bolsillos salió)</h3>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Bolsillo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Referencia (cheque/transacción)</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($comprobante->origenes as $o)
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $o->bolsillo->name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $o->reference ?? '—' }}</td>
                                        <td class="px-3 py-2 text-sm font-medium text-right text-gray-900 dark:text-gray-100">{{ number_format($o->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @if(!$comprobante->isReversed())
    <script>
        function anularComprobanteModal() {
            const totalComprobante = {{ $comprobante->total_amount }};
            const bolsillos = @json($bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'saldo' => $b->saldo]));
            const origenesInit = @json($comprobante->origenes->map(fn($o) => ['bolsillo_id' => $o->bolsillo_id, 'amount' => (float)$o->amount, 'reference' => $o->reference]));

            return {
                open: false,
                get sumaOrigenes() {
                    return Array.from(document.querySelectorAll('.origen-amount'))
                        .reduce((sum, el) => sum + parseFloat(el?.value || 0), 0);
                },
                get sumaCoincide() {
                    return Math.abs(this.sumaOrigenes - totalComprobante) < 0.01;
                },
                abrirModal() {
                    this.$refs.modalAnular.showModal();
                },
                init() {
                    this.$nextTick(() => this.bindOrigenesReverso());
                },
                bindOrigenesReverso() {
                    const container = document.getElementById('origenes-reverso-container');
                    const addBtn = document.getElementById('add-origen-reverso');
                    if (!container || !addBtn) return;

                    addBtn.onclick = () => {
                        const idx = container.querySelectorAll('.origen-reverso-row').length;
                        const row = document.createElement('div');
                        row.className = 'origen-reverso-row flex gap-2 items-center';
                        row.innerHTML = `
                            <select name="origenes[${idx}][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                                <option value="">Seleccionar bolsillo</option>
                                ${bolsillos.map(b => `<option value="${b.id}">${b.name} (${parseFloat(b.saldo).toFixed(2)})</option>`).join('')}
                            </select>
                            <input type="text" name="origenes[${idx}][reference]" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ref.">
                            <input type="number" name="origenes[${idx}][amount]" step="0.01" min="0.01" class="w-24 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm origen-amount" required placeholder="Monto">
                            <button type="button" class="remove-origen-reverso text-red-600 hover:text-red-800 text-sm">✕</button>
                        `;
                        container.appendChild(row);
                        this.toggleRemoveButtons();
                        row.querySelector('.remove-origen-reverso').onclick = () => { row.remove(); this.toggleRemoveButtons(); };
                    };

                    container.querySelectorAll('.remove-origen-reverso').forEach(btn => {
                        btn.onclick = () => {
                            btn.closest('.origen-reverso-row').remove();
                            this.toggleRemoveButtons();
                        };
                    });
                },
                toggleRemoveButtons() {
                    const rows = document.querySelectorAll('.origen-reverso-row');
                    rows.forEach((r, i) => {
                        const btn = r.querySelector('.remove-origen-reverso');
                        if (btn) btn.classList.toggle('hidden', rows.length <= 1);
                    });
                }
            };
        }
    </script>
    @endif
</x-app-layout>
