<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Compra #{{ $purchase->id }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.purchases', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Compras
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
                            <p class="text-sm text-gray-500 dark:text-gray-400">Proveedor</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $purchase->proveedor?->nombre ?? 'Sin proveedor' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Estado</p>
                            <p class="text-lg font-semibold">
                                @if($purchase->status == 'BORRADOR')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Borrador</span>
                                @elseif($purchase->status == 'APROBADO')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aprobado</span>
                                @else
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Anulado</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Forma de Pago</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $purchase->payment_status == 'PAGADO' ? 'Pagado (Contado)' : 'Pendiente (Crédito)' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Factura Externa</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $purchase->invoice_number ?? '-' }}</p>
                            @if($purchase->invoice_date)
                                <p class="text-sm text-gray-500">{{ $purchase->invoice_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mb-6 overflow-x-auto">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Detalle de la Compra</h3>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Descripción</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cantidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Costo Unit.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($purchase->details as $d)
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->description }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->item_type == 'INVENTARIO' ? 'Inventario' : 'Activo Fijo' }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->quantity }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ number_format($d->unit_cost, 2) }}</td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format($d->subtotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-2 text-right">
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Total: {{ number_format($purchase->total, 2) }}</p>
                        </div>
                    </div>

                    @if($purchase->isBorrador())
                        <div class="flex gap-3">
                            <a href="{{ route('stores.purchases.edit', [$store, $purchase]) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Editar</a>
                            <form method="POST" action="{{ route('stores.purchases.approve', [$store, $purchase]) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700" onclick="return confirm('¿Aprobar esta compra? Se sumará al inventario los productos tipo INVENTARIO.');">Aprobar Compra</button>
                            </form>
                            <form method="POST" action="{{ route('stores.purchases.void', [$store, $purchase]) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700" onclick="return confirm('¿Anular esta compra?');">Anular</button>
                            </form>
                        </div>
                    @endif

                    @if($purchase->accountPayable && !$purchase->accountPayable->isPagado())
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Cuenta por pagar: Saldo pendiente {{ number_format($purchase->accountPayable->balance, 2) }}</p>
                            <a href="{{ route('stores.accounts-payables.show', [$store, $purchase->accountPayable]) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Ir a Cuentas por Pagar →</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
