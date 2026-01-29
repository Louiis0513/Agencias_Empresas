<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $bolsillo->name }} — Movimientos
            </h2>
            <a href="{{ route('stores.cajas', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Caja
            </a>
        </div>
    </x-slot>

    <livewire:create-movimiento-modal :store-id="$store->id" :bolsillo-id="$bolsillo->id" />

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg">
                <div class="flex flex-wrap justify-between items-center gap-4">
                    <div>
                        <p class="text-sm text-indigo-700 dark:text-indigo-300">Saldo actual</p>
                        <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">${{ number_format($bolsillo->saldo, 2) }}</p>
                        @if($bolsillo->detalles)
                            <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">{{ $bolsillo->detalles }}</p>
                        @endif
                        <p class="text-xs text-indigo-600 dark:text-indigo-400">{{ $bolsillo->is_bank_account ? 'Cuenta bancaria' : 'Efectivo' }} · {{ $bolsillo->is_active ? 'Activo' : 'Inactivo' }}</p>
                    </div>
                    <button type="button" x-on:click="$dispatch('open-modal', 'create-movimiento')" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Registrar movimiento
                    </button>
                </div>
            </div>

            <form method="GET" action="{{ route('stores.cajas.bolsillos.show', [$store, $bolsillo]) }}" class="mb-6 flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                    <select name="type" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">Todos</option>
                        <option value="INCOME" {{ request('type') === 'INCOME' ? 'selected' : '' }}>Ingreso</option>
                        <option value="EXPENSE" {{ request('type') === 'EXPENSE' ? 'selected' : '' }}>Egreso</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filtrar</button>
                @if(request()->anyFilled(['type', 'fecha_desde', 'fecha_hasta']))
                    <a href="{{ route('stores.cajas.bolsillos.show', [$store, $bolsillo]) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Limpiar</a>
                @endif
            </form>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($movimientos->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($movimientos as $m)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $m->type === 'INCOME' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                                    {{ $m->type === 'INCOME' ? 'Ingreso' : 'Egreso' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-semibold {{ $m->type === 'INCOME' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                                {{ $m->type === 'INCOME' ? '+' : '-' }}${{ number_format($m->amount, 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                @if($m->reversal_of_account_payable_payment_id)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 mr-1">Reversa</span>
                                                @endif
                                                {{ $m->description ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $m->user->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                @if($m->invoice_id || $m->account_payable_payment_id || $m->reversal_of_account_payable_payment_id)
                                                    <span class="text-gray-400 dark:text-gray-500 text-xs" title="{{ $m->reversal_of_account_payable_payment_id ? 'Reversa de pago' : ($m->invoice_id ? 'Vinculado a factura' : 'Vinculado a pago de cuenta por pagar') }}">—</span>
                                                @else
                                                    <form method="POST" action="{{ route('stores.cajas.movimientos.destroy', [$store, $m]) }}" class="inline" onsubmit="return confirm('¿Eliminar este movimiento? Se revertirá el efecto en el saldo.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Eliminar</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $movimientos->links() }}</div>
                    @else
                        <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                            @if(request()->anyFilled(['type', 'fecha_desde', 'fecha_hasta']))
                                No hay movimientos con los filtros aplicados.
                            @else
                                No hay movimientos en este bolsillo. Registra un ingreso o egreso.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
