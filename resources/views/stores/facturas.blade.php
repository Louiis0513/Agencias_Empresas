<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Facturas - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO', 'rowId' => 'factura'])
    @livewire('select-batch-variant-modal', ['storeId' => $store->id])
    <livewire:create-invoice-modal :store-id="$store->id" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Mensajes de éxito/error --}}
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
                    {{-- Botón crear factura --}}
                    <div class="mb-6 flex justify-end">
                        <button type="button"
                                x-data=""
                                x-on:click="$dispatch('open-modal', 'create-invoice')"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Factura
                        </button>
                    </div>

                    {{-- Filtros y búsqueda --}}
                    <form method="GET" action="{{ route('stores.invoices', $store) }}" class="mb-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                            {{-- Rango de fechas --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rango de Fechas</label>
                                <div class="flex gap-2">
                                    <input type="date" 
                                           name="fecha_desde" 
                                           value="{{ request('fecha_desde', $rangoFechas['fecha_desde']->format('Y-m-d')) }}" 
                                           class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <span class="self-center text-gray-500">a</span>
                                    <input type="date" 
                                           name="fecha_hasta" 
                                           value="{{ request('fecha_hasta', $rangoFechas['fecha_hasta']->format('Y-m-d')) }}" 
                                           class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                </div>
                            </div>

                            {{-- Filtro Cliente --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cliente</label>
                                <select name="customer_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Todos</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Filtro Estado --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                                <select name="status" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Todos</option>
                                    <option value="PAID" {{ request('status') == 'PAID' ? 'selected' : '' }}>Pagada</option>
                                    <option value="PENDING" {{ request('status') == 'PENDING' ? 'selected' : '' }}>Pendiente</option>
                                    <option value="VOID" {{ request('status') == 'VOID' ? 'selected' : '' }}>Anulada</option>
                                </select>
                            </div>

                            {{-- Filtro Método de Pago --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Método de Pago</label>
                                <select name="payment_method" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Todos</option>
                                    <option value="SIN_METODO" {{ request('payment_method') == 'SIN_METODO' ? 'selected' : '' }}>Sin método de pago</option>
                                    <option value="CASH" {{ request('payment_method') == 'CASH' ? 'selected' : '' }}>Efectivo</option>
                                    <option value="TRANSFER" {{ request('payment_method') == 'TRANSFER' ? 'selected' : '' }}>Transferencia</option>
                                    <option value="MIXED" {{ request('payment_method') == 'MIXED' ? 'selected' : '' }}>Mixto</option>
                                </select>
                            </div>

                            {{-- Filtro Bolsillo --}}
                            @if(isset($bolsillos) && $bolsillos->isNotEmpty())
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bolsillo</label>
                                <select name="bolsillo_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">Todos</option>
                                    @foreach($bolsillos as $b)
                                        <option value="{{ $b->id }}" {{ request('bolsillo_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            {{-- Búsqueda --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar</label>
                                <div class="flex gap-2">
                                    <input type="text" 
                                           name="search" 
                                           value="{{ request('search') }}" 
                                           placeholder="ID o Total" 
                                           class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <button type="submit" 
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                        Buscar
                                    </button>
                                    @if(request()->anyFilled(['search', 'customer_id', 'status', 'payment_method', 'bolsillo_id', 'fecha_desde', 'fecha_hasta']))
                                        <a href="{{ route('stores.invoices', $store) }}" 
                                           class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-400 dark:hover:bg-gray-600">
                                            Limpiar
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </form>

                    {{-- Tabla de facturas --}}
                    @if($invoices->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Método de Pago</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($invoices as $invoice)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $invoice->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $invoice->customer ? $invoice->customer->name : 'Cliente Genérico' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($invoice->status === 'PAID')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Pagada</span>
                                                @elseif($invoice->status === 'PENDING')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Anulada</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                ${{ number_format($invoice->total, 2) }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
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
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('stores.invoices.show', [$store, $invoice]) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                                                    Ver Detalle
                                                </a>
                                                <button type="button" 
                                                        disabled
                                                        class="text-gray-400 cursor-not-allowed mr-3" 
                                                        title="Próximamente">
                                                    Editar
                                                </button>
                                                <button type="button" 
                                                        disabled
                                                        class="text-gray-400 cursor-not-allowed mr-3" 
                                                        title="Próximamente">
                                                    Anular
                                                </button>
                                                <button type="button" 
                                                        disabled
                                                        class="text-gray-400 cursor-not-allowed" 
                                                        title="Próximamente">
                                                    Imprimir
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Paginación --}}
                        <div class="mt-4">
                            {{ $invoices->links() }}
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                            @if(request()->anyFilled(['search', 'customer_id', 'status', 'payment_method', 'bolsillo_id', 'fecha_desde', 'fecha_hasta']))
                                No se encontraron facturas con los filtros aplicados.
                            @else
                                No hay facturas en los últimos 31 días.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
