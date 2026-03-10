<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Cuenta por Pagar - Compra #{{ $accountPayable->purchase->id }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.accounts-payables', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver a Cuentas por Pagar
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-400">Compra</p>
                            <a href="{{ route('stores.purchases.show', [$store, $accountPayable->purchase]) }}" class="text-lg font-semibold text-indigo-600 hover:text-indigo-800">#{{ $accountPayable->purchase->id }}</a>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Proveedor</p>
                            <p class="text-lg font-semibold text-gray-100">{{ $accountPayable->purchase->proveedor?->nombre ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Deuda Original</p>
                            <p class="text-lg font-semibold text-gray-100">{{ money($accountPayable->total_amount, $store->currency ?? 'COP') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Saldo Pendiente</p>
                            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ money($accountPayable->balance, $store->currency ?? 'COP') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Vencimiento</p>
                            <p class="text-lg font-semibold text-gray-100">{{ $accountPayable->due_date?->format('d/m/Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Estado</p>
                            <p class="text-lg font-semibold">
                                @if($accountPayable->status == 'PENDIENTE')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                @elseif($accountPayable->status == 'PARCIAL')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Parcial</span>
                                @else
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Pagado</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    @if(!$accountPayable->isPagado())
                        <div class="mb-6 p-4 border-b border-white/5 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-100 mb-3">Registrar Pago (Abono)</h3>
                            <form method="POST" action="{{ route('stores.accounts-payables.pay', [$store, $accountPayable]) }}">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha del Pago</label>
                                        <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                                        <input type="text" name="notes" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" placeholder="Opcional">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Partes del Pago (puede ser de uno o varios bolsillos)</label>
                                    <p class="text-xs text-gray-400 mb-2">Puede indicar una referencia por línea (ej. caja menor, transferencia, cheque).</p>
                                    <div id="payment-parts">
                                        <div class="payment-part-row flex flex-wrap items-end gap-2 mb-2">
                                            <div class="flex-1 min-w-[140px]">
                                                <select name="parts[0][bolsillo_id]" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" required>
                                                    <option value="">Seleccionar bolsillo</option>
                                                    @foreach($bolsillos as $b)
                                                        <option value="{{ $b->id }}">{{ $b->name }} ({{ money($b->saldo, $store->currency ?? 'COP', false) }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="number" name="parts[0][amount]" step="0.01" min="0.01" placeholder="Monto" class="w-28 rounded-md border-white/10 bg-white/5 text-gray-100" required>
                                            <input type="text" name="parts[0][reference]" class="w-40 rounded-md border-white/10 bg-white/5 text-gray-100 text-sm" placeholder="Ref. (opcional)" maxlength="100">
                                        </div>
                                    </div>
                                    <button type="button" id="add-part" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar otro bolsillo</button>
                                </div>
                                <p class="text-xs text-gray-400 mb-4">Monto máximo: {{ money($accountPayable->balance, $store->currency ?? 'COP', false) }}</p>
                                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Registrar Pago</button>
                            </form>
                        </div>
                    @endif

                    @php
                        $comprobantesPagos = $accountPayable->comprobanteDestinos->map(fn($d) => $d->comprobanteEgreso)->unique('id')->filter(fn($c) => $c);
                    @endphp
                    @if($comprobantesPagos->count() > 0)
                        <div>
                            <h3 class="text-sm font-medium text-gray-100 mb-2">Historial de Pagos</h3>
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Comprobante</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Fecha</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Monto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Bolsillos</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($comprobantesPagos as $comprobante)
                                        @php
                                            $destino = $accountPayable->comprobanteDestinos->firstWhere('comprobante_egreso_id', $comprobante->id);
                                            $monto = $destino ? $destino->amount : $comprobante->total_amount;
                                        @endphp
                                        <tr class="{{ $comprobante->isReversed() ? 'border-b border-white/5/50' : '' }}">
                                            <td class="px-3 py-2 text-sm text-gray-100">
                                                <a href="{{ route('stores.comprobantes-egreso.show', [$store, $comprobante]) }}" class="text-indigo-600 hover:text-indigo-800">{{ $comprobante->number }}</a>
                                                @if($comprobante->isReversed())
                                                    <span class="ml-1 px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Revertido</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-100">{{ $comprobante->payment_date->format('d/m/Y') }}</td>
                                            <td class="px-3 py-2 text-sm font-medium text-gray-100">{{ money($monto, $store->currency ?? 'COP') }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-100">
                                                @foreach($comprobante->origenes as $origen)
                                                    {{ $origen->bolsillo->name }}: {{ money($origen->amount, $store->currency ?? 'COP', false) }}
                                                    @if($origen->reference) <span class="text-gray-500">({{ $origen->reference }})</span> @endif<br>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!$accountPayable->isPagado())
    @php
        $bolsillosForJs = $bolsillos->map(fn($b) => [
            'id' => $b->id,
            'name' => $b->name,
            'saldo' => $b->saldo,
        ])->values()->all();
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let partIndex = 1;
            const container = document.getElementById('payment-parts');
            const bolsillos = @json($bolsillosForJs);

            document.getElementById('add-part').addEventListener('click', function() {
                const div = document.createElement('div');
                div.className = 'payment-part-row flex flex-wrap items-end gap-2 mb-2';
                div.innerHTML = `
                    <div class="flex-1 min-w-[140px]">
                        <select name="parts[${partIndex}][bolsillo_id]" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" required>
                            <option value="">Seleccionar bolsillo</option>
                            ${bolsillos.map(b => `<option value="${b.id}">${b.name} (${parseFloat(b.saldo).toFixed(2)})</option>`).join('')}
                        </select>
                    </div>
                    <input type="number" name="parts[${partIndex}][amount]" step="0.01" min="0.01" placeholder="Monto" class="w-28 rounded-md border-white/10 bg-white/5 text-gray-100" required>
                    <input type="text" name="parts[${partIndex}][reference]" class="w-40 rounded-md border-white/10 bg-white/5 text-gray-100 text-sm" placeholder="Ref. (opcional)" maxlength="100">
                `;
                container.appendChild(div);
                partIndex++;
            });
        });
    </script>
    @endif
</x-app-layout>
