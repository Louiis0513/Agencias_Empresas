<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Historial de sesiones de caja — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.cajas', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">← Volver a Caja</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <form method="GET" action="{{ route('stores.cajas.sesiones', $store) }}" class="flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Desde</label>
                        <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Hasta</label>
                        <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Filtrar</button>
                </form>
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/5">
                        <thead class="border-b border-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Apertura</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Abierta por</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cierre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cerrada por</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($sesiones as $s)
                                <tr class="hover:bg-white/5 transition">
                                    <td class="px-4 py-3 text-sm text-gray-100">{{ $s->opened_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $s->user->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $s->closed_at ? $s->closed_at->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $s->closedByUser->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($s->closed_at)
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Cerrada</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Abierta</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('stores.cajas.sesiones.show', [$store, $s]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm">Ver detalle</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No hay sesiones registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">{{ $sesiones->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
