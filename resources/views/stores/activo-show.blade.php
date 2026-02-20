<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Hoja de Vida — {{ $activo->name }}
            </h2>
            <a href="{{ route('stores.activos', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Activos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-wrap justify-between items-start gap-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $activo->name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Serial: {{ $activo->serial_number }} @if($activo->code)— {{ $activo->code }}@endif</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('stores.activos.edit', [$store, $activo]) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">Editar</a>
                            @if($activo->status !== \App\Models\Activo::STATUS_DADO_DE_BAJA && $activo->puedePasarA(\App\Models\Activo::STATUS_DADO_DE_BAJA))
                                <form method="POST" action="{{ route('stores.activos.baja', [$store, $activo]) }}" class="inline" onsubmit="return confirm('¿Dar de baja este activo?');">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-amber-600 text-white text-sm rounded-md hover:bg-amber-700">Dar de baja</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Datos del activo</h4>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Marca / Modelo:</dt> <dd class="inline text-gray-900 dark:text-gray-100">{{ $activo->brand ?? '-' }} {{ $activo->model ? " / {$activo->model}" : '' }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Condición:</dt> <dd class="inline text-gray-900 dark:text-gray-100">{{ $activo->condition ? (\App\Models\Activo::condicionesDisponibles()[$activo->condition] ?? $activo->condition) : '-' }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Estado:</dt> <dd class="inline"><span class="px-2 py-0.5 text-xs font-medium rounded-full
                                @if($activo->status === \App\Models\Activo::STATUS_OPERATIVO) bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                @elseif($activo->status === \App\Models\Activo::STATUS_DADO_DE_BAJA || $activo->status === \App\Models\Activo::STATUS_VENDIDO || $activo->status === \App\Models\Activo::STATUS_DONADO) bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                @else bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200
                                @endif
                            ">{{ \App\Models\Activo::estadosDisponibles()[$activo->status] ?? $activo->status }}</span></dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Valor:</dt> <dd class="inline text-gray-900 dark:text-gray-100">{{ number_format($activo->unit_cost, 2) }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Fecha compra:</dt> <dd class="inline text-gray-900 dark:text-gray-100">{{ $activo->purchase_date?->format('d/m/Y') ?? '-' }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Garantía hasta:</dt> <dd class="inline text-gray-900 dark:text-gray-100">{{ $activo->warranty_expiry?->format('d/m/Y') ?? '-' }}</dd></div>
                            @if($activo->description)
                                <div><dt class="text-gray-500 dark:text-gray-400 block">Descripción:</dt> <dd class="text-gray-900 dark:text-gray-100">{{ $activo->description }}</dd></div>
                            @endif
                        </dl>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Ubicación y responsable</h4>
                        <dl class="space-y-2 text-sm">
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Ubicación:</dt> <dd class="inline text-gray-900 dark:text-gray-100">{{ $activo->locationRelation?->name ?? $activo->location ?? '-' }}</dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400 inline">Asignado a:</dt> <dd class="inline text-gray-900 dark:text-gray-100">{{ $activo->assignedTo?->name ?? '-' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="px-6 pb-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase mb-3">Historial de vida</h4>
                    @if($activo->movimientos->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($activo->movimientos as $mov)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-3 py-2"><span class="px-2 py-0.5 text-xs rounded {{ $mov->type === 'ALTA' ? 'bg-green-100 dark:bg-green-900/50' : ($mov->type === 'BAJA' ? 'bg-red-100 dark:bg-red-900/50' : 'bg-gray-100 dark:bg-gray-700') }}">{{ $mov->type }}</span></td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $mov->description ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $mov->user?->name ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Sin movimientos registrados.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
