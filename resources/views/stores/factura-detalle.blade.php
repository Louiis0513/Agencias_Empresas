<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Factura #{{ $invoice->id }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.invoices', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Facturas
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Información de la factura --}}
                    <div class="mb-6 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Fecha</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $invoice->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Estado</p>
                            <p class="text-lg font-semibold">
                                @if($invoice->status == 'PAID')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Pagada</span>
                                @elseif($invoice->status == 'PENDING')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                @else
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Anulada</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cliente</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $invoice->customer ? $invoice->customer->name : 'Cliente Genérico' }}
                            </p>
                            @if($invoice->customer)
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $invoice->customer->email }}</p>
                                @if($invoice->customer->phone)
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $invoice->customer->phone }}</p>
                                @endif
                            @endif
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Método de Pago</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                @if($invoice->payment_method == 'CASH')
                                    Efectivo
                                @elseif($invoice->payment_method == 'CARD')
                                    Tarjeta
                                @else
                                    Transferencia
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Detalles de la factura --}}
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Detalles</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producto</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Precio Unitario</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($invoice->details as $detail)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $detail->product_name }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">${{ number_format($detail->unit_price, 2) }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $detail->quantity }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">${{ number_format($detail->subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Totales --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex justify-end">
                            <div class="w-64 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Subtotal:</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($invoice->subtotal, 2) }}</span>
                                </div>
                                @if($invoice->tax > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Impuesto:</span>
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($invoice->tax, 2) }}</span>
                                    </div>
                                @endif
                                @if($invoice->discount > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Descuento:</span>
                                        <span class="font-semibold text-red-600 dark:text-red-400">-${{ number_format($invoice->discount, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2">
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">Total:</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-gray-100">${{ number_format($invoice->total, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
