<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Compra de productos - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.products', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver a Productos
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

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-col sm:flex-row sm:flex-nowrap gap-4">
                        <form method="GET" action="{{ route('stores.product-purchases', $store) }}" class="flex flex-1 flex-wrap gap-2 min-w-0">
                            <select name="status" class="rounded-md border-white/10 bg-white/5 text-gray-100 min-w-0">
                                <option value="">Todos los estados</option>
                                <option value="BORRADOR" {{ request('status') == 'BORRADOR' ? 'selected' : '' }}>Borrador</option>
                                <option value="APROBADO" {{ request('status') == 'APROBADO' ? 'selected' : '' }}>Aprobado</option>
                                <option value="ANULADO" {{ request('status') == 'ANULADO' ? 'selected' : '' }}>Anulado</option>
                            </select>
                            <select name="payment_status" class="rounded-md border-white/10 bg-white/5 text-gray-100 min-w-0">
                                <option value="">Todos los pagos</option>
                                <option value="PAGADO" {{ request('payment_status') == 'PAGADO' ? 'selected' : '' }}>Pagado</option>
                                <option value="PENDIENTE" {{ request('payment_status') == 'PENDIENTE' ? 'selected' : '' }}>Pendiente</option>
                            </select>
                            <input type="text"
                                   name="proveedor_nombre"
                                   value="{{ request('proveedor_nombre') }}"
                                   placeholder="Buscar por proveedor..."
                                   class="rounded-md border-white/10 bg-white/5 text-gray-100 min-w-0">
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] whitespace-nowrap">Filtrar</button>
                        </form>
                        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto flex-shrink-0">
                            <a href="{{ route('stores.product-purchases.create', $store) }}"
                               class="inline-flex items-center justify-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Nueva compra de productos
                            </a>
                            @storeCan($store, 'product-purchases.view')
                            <a href="{{ route('stores.product-purchases.documento-soporte.create', $store) }}"
                               class="inline-flex items-center justify-center px-4 py-2 border border-white/20 text-gray-200 font-semibold text-xs rounded-xl uppercase tracking-wider hover:bg-white/5">
                                Documento soporte
                            </a>
                            @endstoreCan
                        </div>
                    </div>

                    @if($purchases->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Proveedor</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Pago</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($purchases as $purchase)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">{{ $purchase->id }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $purchase->created_at->format('d/m/Y') }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $purchase->proveedor?->nombre ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">{{ money($purchase->total, $store->currency ?? 'COP') }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($purchase->status == 'BORRADOR')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Borrador</span>
                                                @elseif($purchase->status == 'APROBADO')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aprobado</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Anulado</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($purchase->payment_type == 'CONTADO')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Contado</span>
                                                @elseif($purchase->payment_status == 'PAGADO')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" title="Crédito pagado en abonos">Crédito (Pagado)</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Crédito (Pendiente)</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('stores.purchases.show', [$store, $purchase]) }}" class="text-brand hover:text-white transition mr-3">Ver</a>
                                                @if($purchase->isBorrador())
                                                    <a href="{{ route('stores.product-purchases.edit', [$store, $purchase]) }}" class="text-brand hover:text-white transition mr-3">Editar</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $purchases->links() }}</div>
                    @else
                        <p class="text-gray-400 text-center py-8">No hay compras de productos registradas.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
