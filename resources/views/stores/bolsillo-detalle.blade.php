<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ $bolsillo->name }} — Movimientos
            </h2>
            <a href="{{ route('stores.cajas', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Caja
            </a>
        </div>
    </x-slot>

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
                        <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ money($bolsillo->saldo, $store->currency ?? 'COP') }}</p>
                        @if($bolsillo->detalles)
                            <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">{{ $bolsillo->detalles }}</p>
                        @endif
                        <p class="text-xs text-indigo-600 dark:text-indigo-400">{{ $bolsillo->is_bank_account ? 'Cuenta bancaria' : 'Efectivo' }} · {{ $bolsillo->is_active ? 'Activo' : 'Inactivo' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('stores.comprobantes-ingreso.create', $store) }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Comprobante de ingreso
                        </a>
                        <a href="{{ route('stores.comprobantes-egreso.create', $store) }}" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 font-medium">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                            Comprobante de egreso
                        </a>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('stores.cajas.bolsillos.show', [$store, $bolsillo]) }}" class="mb-6 flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                    <select name="type" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                        <option value="">Todos</option>
                        <option value="INCOME" {{ request('type') === 'INCOME' ? 'selected' : '' }}>Ingreso</option>
                        <option value="EXPENSE" {{ request('type') === 'EXPENSE' ? 'selected' : '' }}>Egreso</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                </div>
                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Filtrar</button>
                @if(request()->anyFilled(['type', 'fecha_desde', 'fecha_hasta']))
                    <a href="{{ route('stores.cajas.bolsillos.show', [$store, $bolsillo]) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Limpiar</a>
                @endif
            </form>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    @if($movimientos->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Monto</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Comprobante</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Usuario</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($movimientos as $m)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-3 text-sm text-gray-100">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $m->type === 'INCOME' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                                    {{ $m->type === 'INCOME' ? 'Ingreso' : 'Egreso' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-semibold {{ $m->type === 'INCOME' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                                {{ $m->type === 'INCOME' ? '+' : '-' }}{{ money($m->amount, $store->currency ?? 'COP') }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                @if($m->comprobanteIngreso)
                                                    <a href="{{ route('stores.comprobantes-ingreso.show', [$store, $m->comprobanteIngreso]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $m->comprobanteIngreso->number }}</a>
                                                @elseif($m->comprobanteEgreso)
                                                    <a href="{{ route('stores.comprobantes-egreso.show', [$store, $m->comprobanteEgreso]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $m->comprobanteEgreso->number }}</a>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-400">{{ $m->description ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-400">{{ $m->comprobanteIngreso?->user?->name ?? $m->comprobanteEgreso?->user?->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $movimientos->links() }}</div>
                    @else
                        <p class="text-center text-gray-400 py-8">
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
