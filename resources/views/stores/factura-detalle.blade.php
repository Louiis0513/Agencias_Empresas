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
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Número de Factura</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">#{{ $invoice->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Fecha de Emisión</p>
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
                            <p class="text-sm text-gray-500 dark:text-gray-400">Método de Pago</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                @if($invoice->payment_method === null)
                                    <span class="text-gray-500 dark:text-gray-400">Sin método de pago asociado</span>
                                @elseif($invoice->payment_method == 'CASH')
                                    Efectivo
                                @elseif($invoice->payment_method == 'CARD')
                                    Tarjeta
                                @elseif($invoice->payment_method == 'TRANSFER')
                                    Transferencia
                                @else
                                    Mixto
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Información del Cliente --}}
                    <div class="mb-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Información del Cliente</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nombre</p>
                                <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $invoice->customer ? $invoice->customer->name : 'Cliente Genérico' }}
                                </p>
                            </div>
                            @if($invoice->customer)
                                @if($invoice->customer->email)
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
                                        <p class="text-base text-gray-900 dark:text-gray-100">{{ $invoice->customer->email }}</p>
                                    </div>
                                @endif
                                @if($invoice->customer->phone)
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Teléfono</p>
                                        <p class="text-base text-gray-900 dark:text-gray-100">{{ $invoice->customer->phone }}</p>
                                    </div>
                                @endif
                                @if($invoice->customer->document_number)
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Documento</p>
                                        <p class="text-base text-gray-900 dark:text-gray-100">{{ $invoice->customer->document_number }}</p>
                                    </div>
                                @endif
                                @if($invoice->customer->address)
                                    <div class="md:col-span-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Dirección</p>
                                        <p class="text-base text-gray-900 dark:text-gray-100">{{ $invoice->customer->address }}</p>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Información del Usuario y Tienda --}}
                    <div class="mb-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Información Adicional</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Registrado por</p>
                                <p class="text-base text-gray-900 dark:text-gray-100">
                                    {{ $invoice->user ? $invoice->user->name : 'N/A' }}
                                </p>
                                @if($invoice->user)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $invoice->user->email }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Tienda</p>
                                <p class="text-base text-gray-900 dark:text-gray-100">{{ $store->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Última Actualización</p>
                                <p class="text-base text-gray-900 dark:text-gray-100">{{ $invoice->updated_at->format('d/m/Y H:i') }}</p>
                            </div>
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

                    @php
                        $comprobantesDirectos = $invoice->comprobantesIngresoDirectos->reject(fn ($ci) => $ci->isReversed());
                        $comprobantesCobro = $invoice->accountReceivable
                            ? $invoice->accountReceivable->comprobanteIngresoAplicaciones
                                ->map(fn ($ap) => $ap->comprobanteIngreso)
                                ->filter(fn ($ci) => $ci && !$ci->isReversed())
                            : collect();
                        $comprobantes = $comprobantesDirectos->concat($comprobantesCobro)->unique('id')->values();
                        $mostrarSeccionCobro = $invoice->accountReceivable || $comprobantes->isNotEmpty();
                    @endphp
                    @if($mostrarSeccionCobro)
                    {{-- Cuenta por cobrar y comprobantes de ingreso --}}
                    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4 space-y-4">
                        @if($invoice->accountReceivable)
                            <div>
                                <a href="{{ route('stores.accounts-receivables.show', [$store, $invoice->accountReceivable]) }}"
                                   class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700">
                                    Ver cuenta por cobrar
                                </a>
                            </div>
                        @endif
                        @if($comprobantes->isNotEmpty())
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Comprobante(s) de ingreso</h3>
                                <ul class="space-y-1">
                                    @foreach($comprobantes as $ci)
                                        <li>
                                            <a href="{{ route('stores.comprobantes-ingreso.show', [$store, $ci]) }}"
                                               class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                {{ $ci->number }} — {{ $ci->date->format('d/m/Y') }} — ${{ number_format($ci->total_amount, 2) }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    @endif

                    {{-- Botones de Acción --}}
                    <div class="mt-6 flex justify-end space-x-3 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <button type="button" 
                                disabled
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-md cursor-not-allowed" 
                                title="Próximamente">
                            Imprimir
                        </button>
                        <a href="{{ route('stores.invoices', $store) }}" 
                           class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Volver a Facturas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
