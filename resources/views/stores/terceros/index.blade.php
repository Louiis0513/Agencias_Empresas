<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Terceros</h2>
                <p class="text-sm text-gray-400">{{ $store->name }}</p>
            </div>
            @storeCan($store, 'terceros.create')
                <a href="{{ route('stores.terceros.create', $store) }}" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white">Crear tercero</a>
            @endstoreCan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-5 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-950/40 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded-xl border border-red-500/30 bg-red-950/40 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif

            <div class="overflow-hidden rounded-xl border border-white/5 bg-dark-card">
                <div class="p-6">
                    <form method="GET" action="{{ route('stores.terceros', $store) }}" class="mb-6 grid gap-3 md:grid-cols-[1fr_180px_160px_auto]">
                        <input name="search" value="{{ request('search') }}" placeholder="Nombre, documento, email o teléfono…" class="rounded-xl border-white/10 bg-white/5 text-gray-100">
                        <select name="rol" class="rounded-xl border-white/10 bg-white/5 text-gray-100">
                            <option value="">Todos los roles</option>
                            @foreach(\App\Models\Tercero::ROLES as $rol)
                                <option value="{{ $rol }}" @selected(request('rol') === $rol)>{{ ucfirst($rol) }}</option>
                            @endforeach
                        </select>
                        <select name="activo" class="rounded-xl border-white/10 bg-white/5 text-gray-100">
                            <option value="">Todos los estados</option>
                            <option value="1" @selected(request('activo') === '1')>Activos</option>
                            <option value="0" @selected(request('activo') === '0')>Inactivos</option>
                        </select>
                        <div class="flex gap-2">
                            <button class="rounded-xl bg-brand px-4 py-2 text-white">Filtrar</button>
                            @if(request()->anyFilled(['search', 'rol', 'activo']))
                                <a href="{{ route('stores.terceros', $store) }}" class="rounded-xl bg-white/5 px-4 py-2 text-gray-300">Limpiar</a>
                            @endif
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-3 py-3">Nombre</th>
                                    <th class="px-3 py-3">Identificación</th>
                                    <th class="px-3 py-3">Contacto</th>
                                    <th class="px-3 py-3">Roles</th>
                                    <th class="px-3 py-3">Estado</th>
                                    <th class="px-3 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($terceros as $tercero)
                                    <tr class="hover:bg-white/5">
                                        <td class="px-3 py-4">
                                            <div class="font-medium text-gray-100">{{ $tercero->nombre }}</div>
                                            <div class="text-xs text-gray-500">{{ $tercero->nombre_comercial }}</div>
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-300">{{ $tercero->tipo_identificacion }} {{ $tercero->numero_identificacion ?: '—' }}</td>
                                        <td class="px-3 py-4 text-sm text-gray-300">
                                            <div>{{ $tercero->email ?: '—' }}</div>
                                            <div class="text-xs text-gray-500">{{ $tercero->telefono }}</div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($tercero->roles->where('activo', true) as $rol)
                                                    <span class="rounded-full bg-brand/15 px-2 py-1 text-xs text-brand">{{ ucfirst($rol->rol) }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 text-sm {{ $tercero->activo ? 'text-emerald-400' : 'text-red-400' }}">{{ $tercero->activo ? 'Activo' : 'Inactivo' }}</td>
                                        <td class="px-3 py-4 text-right">
                                            <a href="{{ route('stores.terceros.show', [$store, $tercero]) }}" class="text-sm text-brand hover:underline">Ver / editar</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-3 py-12 text-center text-gray-500">No hay terceros para estos filtros.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-5">{{ $terceros->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
