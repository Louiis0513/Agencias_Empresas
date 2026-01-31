<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Comprobante {{ $comprobante->number }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.comprobantes-egreso.index', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Comprobantes
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

                    @if(!$comprobante->isReversed())
                        <form method="POST" action="{{ route('stores.comprobantes-egreso.reversar', [$store, $comprobante]) }}" class="inline" onsubmit="return confirm('¿Reversar este comprobante? Se registrarán ingresos en caja y se restaurarán los saldos de las cuentas por pagar afectadas.');">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">Reversar comprobante</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
