<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Sesión de caja — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.cajas.sesiones', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">← Volver al historial</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl p-6">
                <h3 class="text-lg font-medium text-gray-100 mb-4">Cabecera</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-400">Apertura</dt>
                        <dd class="font-medium text-gray-100">{{ $sesionCaja->opened_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">Abierta por</dt>
                        <dd class="font-medium text-gray-100">{{ $sesionCaja->user->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">Cierre</dt>
                        <dd class="font-medium text-gray-100">{{ $sesionCaja->closed_at ? $sesionCaja->closed_at->format('d/m/Y H:i') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">Cerrada por</dt>
                        <dd class="font-medium text-gray-100">{{ $sesionCaja->closedByUser->name ?? '—' }}</dd>
                    </div>
                    @if($sesionCaja->nota_apertura)
                        <div class="md:col-span-2">
                            <dt class="text-gray-400">Nota apertura</dt>
                            <dd class="text-gray-100">{{ $sesionCaja->nota_apertura }}</dd>
                        </div>
                    @endif
                    @if($sesionCaja->nota_cierre)
                        <div class="md:col-span-2">
                            <dt class="text-gray-400">Nota cierre</dt>
                            <dd class="text-gray-100">{{ $sesionCaja->nota_cierre }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl p-6">
                <h3 class="text-lg font-medium text-gray-100 mb-4">Detalle por bolsillo</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/5">
                        <thead class="border-b border-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Bolsillo</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Saldo esperado apertura</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Saldo físico apertura</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Saldo esperado cierre</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Saldo físico cierre</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($sesionCaja->detalles as $d)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-100">{{ $d->bolsillo->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ money($d->saldo_esperado_apertura, $store->currency ?? 'COP') }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ money($d->saldo_fisico_apertura, $store->currency ?? 'COP') }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $d->saldo_esperado_cierre !== null ? money($d->saldo_esperado_cierre, $store->currency ?? 'COP') : '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $d->saldo_fisico_cierre !== null ? money($d->saldo_fisico_cierre, $store->currency ?? 'COP') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
