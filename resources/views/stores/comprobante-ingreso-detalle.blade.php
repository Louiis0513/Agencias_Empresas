<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Comprobante {{ $comprobanteIngreso->number }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.comprobantes-ingreso.index', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">← Volver</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Número</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobanteIngreso->number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Fecha</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobanteIngreso->date->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Tipo</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            @if($comprobanteIngreso->type === 'COBRO_CUENTA')
                                <span class="px-2 py-1 text-sm font-medium rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">Cobro a cuenta por cobrar</span>
                            @else
                                <span class="px-2 py-1 text-sm font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Ingreso manual</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Monto total</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($comprobanteIngreso->total_amount, 2) }}</p>
                    </div>
                    @if($comprobanteIngreso->customer)
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cliente</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $comprobanteIngreso->customer->name }}</p>
                        </div>
                    @endif
                    @if($comprobanteIngreso->notes)
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Notas</p>
                            <p class="text-gray-900 dark:text-gray-100">{{ $comprobanteIngreso->notes }}</p>
                        </div>
                    @endif
                </div>

                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Destino del dinero (bolsillos)</h3>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Bolsillo</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($comprobanteIngreso->destinos as $d)
                                <tr>
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $d->bolsillo->name ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format($d->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($comprobanteIngreso->aplicaciones->count() > 0)
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Aplicado a cuenta(s) por cobrar</h3>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Factura</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto aplicado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($comprobanteIngreso->aplicaciones as $ap)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                            <a href="{{ route('stores.accounts-receivables.show', [$store, $ap->accountReceivable]) }}" class="text-indigo-600 hover:text-indigo-800">Factura #{{ $ap->accountReceivable->invoice->id }}</a>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ number_format($ap->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
