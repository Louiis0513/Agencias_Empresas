<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Grupos de atributos - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <livewire:create-attribute-group-modal :store-id="$store->id" />
    <livewire:edit-attribute-group-modal :store-id="$store->id" />
    <livewire:create-attribute-modal :store-id="$store->id" :from-groups-page="true" />
    <livewire:edit-attribute-modal :store-id="$store->id" :from-groups-page="true" />

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
                </div>
            @endif

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    Crea <strong>grupos de atributos</strong> (ej. Talla, Marca) y añade <strong>atributos</strong> a cada grupo.
                    Indica si cada atributo es <strong>requerido</strong> o no. Luego asígnalos a las categorías desde Categorías → [categoría] → Atributos.
                </p>
            </div>

            <div class="flex gap-3 justify-end">
                <button type="button"
                        x-on:click="$dispatch('open-modal', 'create-attribute-group')"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Crear grupo
                </button>
            </div>

            @if($groups->count() > 0)
                <div class="space-y-6">
                    @foreach($groups as $group)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 flex justify-between items-center">
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $group->name }}</h3>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">{{ $group->attributes->count() }} atributo(s)</span>
                                    <button type="button"
                                            x-on:click="$dispatch('open-create-attribute', { groupId: {{ $group->id }} })"
                                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        Crear atributo
                                    </button>
                                    <button x-on:click="$dispatch('open-edit-attribute-group-modal', { id: {{ $group->id }} })"
                                            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm">
                                        Editar
                                    </button>
                                    <form action="{{ route('stores.attribute-groups.destroy', [$store, $group]) }}" method="POST" class="inline"
                                          onsubmit="return confirm('¿Eliminar este grupo? Debes mover o borrar sus atributos antes.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                            <div class="p-6">
                                @if($group->attributes->count() > 0)
                                    <div class="space-y-3">
                                        @foreach($group->attributes as $attr)
                                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                                <div class="flex items-center gap-3">
                                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $attr->name }}</span>
                                                    <span class="text-xs text-gray-500">({{ $attr->type }})</span>
                                                    @if($attr->pivot->is_required)
                                                        <span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Requerido</span>
                                                    @else
                                                        <span class="px-2 py-0.5 text-xs rounded bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Opcional</span>
                                                    @endif
                                                    @if($attr->isSelectType() && $attr->options->count() > 0)
                                                        <span class="text-xs text-gray-400">{{ $attr->options->pluck('value')->join(', ') }}</span>
                                                    @endif
                                                </div>
                                                <button x-on:click="$dispatch('open-edit-attribute-modal', { id: {{ $attr->id }} })"
                                                        class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 text-sm">
                                                    Editar
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin atributos. Usa el botón «Crear atributo» en la cabecera del grupo para añadir el primero.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">No hay grupos. Crea un grupo y luego añade atributos.</p>
                    <button type="button"
                            x-on:click="$dispatch('open-modal', 'create-attribute-group')"
                            class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-white text-sm font-medium hover:bg-indigo-700">
                        Crear primer grupo
                    </button>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
