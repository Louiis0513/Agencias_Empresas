<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Comprobantes de Egreso - {{ $store->name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('stores.comprobantes-egreso.create', $store) }}" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] text-sm">
                    + Nuevo Comprobante
                </a>
                <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    ← Volver al Resumen
                </a>
            </div>
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
                    <form method="GET" action="{{ route('stores.comprobantes-egreso.index', $store) }}" class="mb-6 flex flex-wrap gap-2">
                        <select name="type" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                            <option value="">Todos los tipos</option>
                            <option value="PAGO_CUENTA" {{ request('type') == 'PAGO_CUENTA' ? 'selected' : '' }}>Pago cuenta</option>
                            <option value="GASTO_DIRECTO" {{ request('type') == 'GASTO_DIRECTO' ? 'selected' : '' }}>Gasto directo</option>
                            <option value="MIXTO" {{ request('type') == 'MIXTO' ? 'selected' : '' }}>Mixto</option>
                        </select>
                        <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100" placeholder="Desde">
                        <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100" placeholder="Hasta">
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Filtrar</button>
                    </form>

                    @if($comprobantes->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Número</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Monto</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">A quién</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Usuario</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($comprobantes as $c)
                                        <tr class="{{ $c->isReversed() ? 'border-b border-white/5/50' : '' }}">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">
                                                <a href="{{ route('stores.comprobantes-egreso.show', [$store, $c]) }}" class="text-indigo-600 hover:text-indigo-800">{{ $c->number }}</a>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $c->payment_date->format('d/m/Y') }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">{{ money($c->total_amount, $store->currency ?? 'COP') }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-100">{{ $c->beneficiary_name ?? '—' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($c->type == 'PAGO_CUENTA')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Pago cuenta</span>
                                                @elseif($c->type == 'GASTO_DIRECTO')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Gasto directo</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Mixto</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $c->user->name ?? '—' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($c->isReversed())
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Revertido</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Activo</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('stores.comprobantes-egreso.show', [$store, $c]) }}" class="text-brand hover:text-white transition">Ver detalle</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $comprobantes->links() }}</div>
                    @else
                        <p class="text-gray-400 text-center py-8">No hay comprobantes de egreso.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
