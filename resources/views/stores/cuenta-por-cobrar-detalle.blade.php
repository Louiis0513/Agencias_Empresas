<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Cuenta por Cobrar - Factura #{{ $accountReceivable->invoice->id }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.accounts-receivables', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Cuentas por Cobrar
            </a>
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
                            <p class="text-sm text-gray-500 dark:text-gray-400">Factura</p>
                            <a href="{{ route('stores.invoices.show', [$store, $accountReceivable->invoice]) }}" class="text-lg font-semibold text-indigo-600 hover:text-indigo-800">#{{ $accountReceivable->invoice->id }}</a>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cliente</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $accountReceivable->customer?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total a cobrar</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ number_format($accountReceivable->total_amount, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Saldo pendiente</p>
                            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ number_format($accountReceivable->balance, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Vencimiento</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $accountReceivable->due_date?->format('d/m/Y') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Estado</p>
                            <p class="text-lg font-semibold">
                                @if($accountReceivable->status == 'PENDIENTE')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                @elseif($accountReceivable->status == 'PARCIAL')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Parcial</span>
                                @else
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Cobrado</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($accountReceivable->cuotas->count() > 0)
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Cuotas</h3>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Abonado</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Vence</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($accountReceivable->cuotas as $c)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $c->sequence }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ number_format($c->amount, 2) }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ number_format($c->amount_paid, 2) }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $c->due_date->format('d/m/Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(!$accountReceivable->isPagado())
                        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Cobrar (registrar ingreso y abonar a esta cuenta)</h3>
                            <form method="POST" action="{{ route('stores.accounts-receivables.cobrar', [$store, $accountReceivable]) }}">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                                        <input type="date" name="date" value="{{ date('Y-m-d') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto a cobrar</label>
                                        <input type="number" name="amount" step="0.01" min="0.01" max="{{ $accountReceivable->balance }}" value="{{ old('amount', $accountReceivable->balance) }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                                        <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Opcional">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destino del dinero (bolsillo(s))</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Puede indicar una referencia por línea (ej. caja menor, depósito, transferencia).</p>
                                    <div id="cobro-parts">
                                        <div class="cobro-part-row flex flex-wrap items-end gap-2 mb-2">
                                            <div class="flex-1 min-w-[140px]">
                                                <select name="parts[0][bolsillo_id]" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                                    <option value="">Seleccionar bolsillo</option>
                                                    @foreach($bolsillos as $b)
                                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="number" name="parts[0][amount]" step="0.01" min="0" placeholder="Monto" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                            <input type="text" name="parts[0][reference]" class="w-40 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ref. (opcional)" maxlength="100">
                                        </div>
                                    </div>
                                    <button type="button" id="add-cobro-part" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar otro bolsillo</button>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Monto máximo a cobrar: {{ number_format($accountReceivable->balance, 2) }}</p>
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Registrar Cobro</button>
                            </form>
                        </div>
                    @endif

                    @if($accountReceivable->comprobanteIngresoAplicaciones->count() > 0)
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Historial de cobros</h3>
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Comprobante</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Fecha</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Bolsillos</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($accountReceivable->comprobanteIngresoAplicaciones as $ap)
                                        @php $ci = $ap->comprobanteIngreso; @endphp
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                <a href="{{ route('stores.comprobantes-ingreso.show', [$store, $ci]) }}" class="text-indigo-600 hover:text-indigo-800">{{ $ci->number }}</a>
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $ci->date->format('d/m/Y') }}</td>
                                            <td class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($ap->amount, 2) }}</td>
                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                @foreach($ci->destinos ?? [] as $d)
                                                    {{ $d->bolsillo->name ?? '-' }}: {{ number_format($d->amount, 2) }}@if($d->reference ?? null) <span class="text-gray-500">({{ $d->reference }})</span> @endif<br>
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

    @if(!$accountReceivable->isPagado())
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let partIndex = 1;
            const container = document.getElementById('cobro-parts');
            const bolsillos = @json($bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name]));

            document.getElementById('add-cobro-part').addEventListener('click', function() {
                const div = document.createElement('div');
                div.className = 'cobro-part-row flex flex-wrap items-end gap-2 mb-2';
                div.innerHTML = `
                    <div class="flex-1 min-w-[140px]">
                        <select name="parts[${partIndex}][bolsillo_id]" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                            <option value="">Seleccionar bolsillo</option>
                            ${bolsillos.map(b => `<option value="${b.id}">${b.name}</option>`).join('')}
                        </select>
                    </div>
                    <input type="number" name="parts[${partIndex}][amount]" step="0.01" min="0" placeholder="Monto" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                    <input type="text" name="parts[${partIndex}][reference]" class="w-40 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ref. (opcional)" maxlength="100">
                `;
                container.appendChild(div);
                partIndex++;
            });
        });
    </script>
    @endif
</x-app-layout>
