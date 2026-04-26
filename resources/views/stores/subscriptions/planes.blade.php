<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Planes actuales - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <a href="{{ route('stores.subscriptions.plans.designer', $store) }}"
                           class="inline-flex items-center px-4 py-2 bg-white/10 text-white font-semibold text-xs rounded-xl uppercase tracking-wider hover:bg-white/20">
                            Diseñador de planes
                        </a>
                        <button type="button" x-on:click="$dispatch('open-modal', 'create-store-plan')"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Crear plan
                        </button>
                    </div>

                    @if($plans->count() > 0)
                        <form method="POST" action="{{ route('stores.subscriptions.plans.visibility', $store) }}" class="overflow-x-auto">
                            @csrf
                            @method('PUT')
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Precio</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Duración (días)</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Límite diario</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Límite total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Mostrar en panel</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($plans as $plan)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-100">{{ $plan->name }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate" title="{{ $plan->description }}">{{ $plan->description ? \Illuminate\Support\Str::limit($plan->description, 50) : '—' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-100">{{ money($plan->price, $store->currency ?? 'COP', false) }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-100">{{ $plan->duration_days }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-100">{{ $plan->daily_entries_limit !== null ? $plan->daily_entries_limit : 'Ilimitado' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-100">{{ $plan->total_entries_limit !== null ? $plan->total_entries_limit : 'Ilimitado' }}</td>
                                            <td class="px-4 py-4">
                                                <label class="flex items-center gap-2 text-sm text-gray-300">
                                                    <input type="checkbox" name="store_plan_ids[]" value="{{ $plan->id }}" {{ $plan->in_showcase ? 'checked' : '' }} class="rounded border-white/10 bg-white/5 text-brand focus:ring-brand">
                                                    <span>Panel Suscripciones</span>
                                                </label>
                                            </td>
                                            <td class="px-4 py-4 text-right text-sm font-medium whitespace-nowrap">
                                                <button type="button"
                                                        x-on:click="$dispatch('open-edit-store-plan-modal', { id: {{ $plan->id }} })"
                                                        class="text-brand hover:text-white transition mr-3 font-medium">
                                                    Editar
                                                </button>
                                                <button type="button"
                                                        onclick="if(confirm('¿Estás seguro de eliminar este plan?')) document.getElementById('delete-plan-{{ $plan->id }}').submit();"
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 font-medium">
                                                    Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider">
                                    Guardar visibilidad en panel
                                </button>
                            </div>
                        </form>
                        @foreach($plans as $plan)
                            <form id="delete-plan-{{ $plan->id }}" method="POST" action="{{ route('stores.subscriptions.plans.destroy', [$store, $plan]) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                    @else
                        <p class="text-center text-gray-400 py-8">
                            No hay planes creados.
                            <button type="button" x-on:click="$dispatch('open-modal', 'create-store-plan')" class="text-indigo-600 dark:text-indigo-400 hover:underline bg-transparent border-0 p-0 cursor-pointer font-inherit">Crear el primero</button>
                        </p>
                    @endif
                </div>
            </div>
        </div>
        @livewire('create-store-plan-modal', ['storeId' => $store->id])
        @livewire('edit-store-plan-modal', ['storeId' => $store->id])
    </div>
</x-app-layout>
