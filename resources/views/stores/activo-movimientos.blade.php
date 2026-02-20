<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Movimientos de Activos — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.activos', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Activos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
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
                <p class="text-sm text-indigo-700 dark:text-indigo-300">Historial de eventos de activos</p>
                <p class="text-indigo-900 dark:text-indigo-100 mt-1">Altas (compras o creación), bajas y cambios de estado. Para dar de baja un activo, abre su hoja de vida y usa «Dar de baja».</p>
            </div>

            <form method="GET" action="{{ route('stores.activos.movimientos', $store) }}" class="mb-6 flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activo</label>
                    <select name="activo_id" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">Todos</option>
                        @foreach($activosParaMovimientos as $a)
                            <option value="{{ $a->id }}" {{ request('activo_id') == $a->id ? 'selected' : '' }}>{{ $a->name }} {{ $a->serial_number ? "({$a->serial_number})" : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                    <select name="type" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">Todos</option>
                        <option value="ALTA" {{ request('type') === 'ALTA' ? 'selected' : '' }}>Alta</option>
                        <option value="BAJA" {{ request('type') === 'BAJA' ? 'selected' : '' }}>Baja</option>
                        <option value="CAMBIO_ESTADO" {{ request('type') === 'CAMBIO_ESTADO' ? 'selected' : '' }}>Cambio estado</option>
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
                @if(request()->anyFilled(['activo_id', 'type', 'fecha_desde', 'fecha_hasta']))
                    <a href="{{ route('stores.activos.movimientos', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Limpiar</a>
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
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Activo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cantidad</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Costo unit.</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($movimientos as $m)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <a href="{{ route('stores.activos.show', [$store, $m->activo]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $m->activo->name ?? '—' }}</a>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    {{ $m->type === 'ALTA' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($m->type === 'BAJA' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300') }}">
                                                    {{ $m->type }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $m->quantity !== null ? $m->quantity : '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $m->unit_cost !== null ? number_format($m->unit_cost, 2) : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $m->description ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $m->user->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $movimientos->links() }}</div>
                    @else
                        <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                            @if(request()->anyFilled(['activo_id', 'type', 'fecha_desde', 'fecha_hasta']))
                                No hay movimientos con los filtros aplicados.
                            @else
                                No hay movimientos de activos. Las altas se registran al crear un activo o aprobar una compra con ítems tipo Activo Fijo.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
