<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Cuentas por Cobrar - {{ $store->name }}
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

            <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
                <p class="text-lg font-bold text-emerald-800 dark:text-emerald-200">Saldo pendiente de cobro: {{ money($saldoPendiente, $store->currency ?? 'COP', false) }}</p>
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <form method="GET" action="{{ route('stores.accounts-receivables', $store) }}" class="mb-6 flex gap-2 flex-wrap">
                        <select name="status" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="">Todos</option>
                            <option value="PENDIENTE" {{ request('status') == 'PENDIENTE' ? 'selected' : '' }}>Pendientes</option>
                            <option value="PARCIAL" {{ request('status') == 'PARCIAL' ? 'selected' : '' }}>Parcial</option>
                            <option value="PAGADO" {{ request('status') == 'PAGADO' ? 'selected' : '' }}>Cobrados</option>
                        </select>
                        <select name="customer_id" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="">Todos los clientes</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Filtrar</button>
                    </form>

                    @if($cuentas->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Factura</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Saldo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Vencimiento</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($cuentas as $ar)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">#{{ $ar->invoice->id }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $ar->customer?->name ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ money($ar->total_amount, $store->currency ?? 'COP', false) }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-100">{{ money($ar->balance, $store->currency ?? 'COP', false) }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $ar->due_date?->format('d/m/Y') ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($ar->status == 'PENDIENTE')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                                @elseif($ar->status == 'PARCIAL')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Parcial</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Cobrado</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('stores.accounts-receivables.show', [$store, $ar]) }}" class="text-brand hover:text-white transition">Ver / Cobrar</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $cuentas->links() }}</div>
                    @else
                        <p class="text-center py-8 text-gray-400">No hay cuentas por cobrar.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
