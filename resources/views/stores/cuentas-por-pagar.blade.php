<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Cuentas por Pagar - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

            <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <p class="text-lg font-bold text-amber-800 dark:text-amber-200">Deuda total pendiente: {{ number_format($deudaTotal, 2) }}</p>
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <form method="GET" action="{{ route('stores.accounts-payables', $store) }}" class="mb-6 flex gap-2">
                        <select name="status" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="">Todos</option>
                            <option value="pendientes" {{ request('status') == 'pendientes' ? 'selected' : '' }}>Pendientes</option>
                            <option value="PAGADO" {{ request('status') == 'PAGADO' ? 'selected' : '' }}>Pagados</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Filtrar</button>
                    </form>

                    @if($accountsPayables->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Compra</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Proveedor</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Saldo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Vencimiento</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($accountsPayables as $ap)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">#{{ $ap->purchase->id }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $ap->purchase->proveedor?->nombre ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ number_format($ap->total_amount, 2) }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-100">{{ number_format($ap->balance, 2) }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $ap->due_date?->format('d/m/Y') ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($ap->status == 'PENDIENTE')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                                @elseif($ap->status == 'PARCIAL')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Parcial</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Pagado</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('stores.accounts-payables.show', [$store, $ap]) }}" class="text-brand hover:text-white transition">Ver / Pagar</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $accountsPayables->links() }}</div>
                    @else
                        <p class="text-gray-400 text-center py-8">No hay cuentas por pagar.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
