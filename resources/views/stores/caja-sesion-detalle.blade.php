<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Sesión de caja — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.cajas.sesiones', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">← Volver al historial</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Cabecera</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Apertura</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $sesionCaja->opened_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Abierta por</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $sesionCaja->user->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cierre</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $sesionCaja->closed_at ? $sesionCaja->closed_at->format('d/m/Y H:i') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cerrada por</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $sesionCaja->closedByUser->name ?? '—' }}</dd>
                    </div>
                    @if($sesionCaja->nota_apertura)
                        <div class="md:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Nota apertura</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $sesionCaja->nota_apertura }}</dd>
                        </div>
                    @endif
                    @if($sesionCaja->nota_cierre)
                        <div class="md:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Nota cierre</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $sesionCaja->nota_cierre }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Detalle por bolsillo</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bolsillo</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo esperado apertura</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo físico apertura</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo esperado cierre</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo físico cierre</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($sesionCaja->detalles as $d)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $d->bolsillo->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">${{ number_format($d->saldo_esperado_apertura, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">${{ number_format($d->saldo_fisico_apertura, 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $d->saldo_esperado_cierre !== null ? '$'.number_format($d->saldo_esperado_cierre, 2) : '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $d->saldo_fisico_cierre !== null ? '$'.number_format($d->saldo_fisico_cierre, 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
