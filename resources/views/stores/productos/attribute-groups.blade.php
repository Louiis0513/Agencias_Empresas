<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Grupos de atributos - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
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

           

            <div class="bg-dark-card border border-white/5 sm:rounded-xl overflow-visible">
                <div class="p-6">
                    {{-- Filtro y botón crear grupo --}}
                    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <form method="GET" action="{{ route('stores.attribute-groups', $store) }}" class="flex flex-col sm:flex-row gap-2 sm:items-center w-full lg:max-w-2xl">
                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Buscar por nombre del grupo o del atributo"
                                   class="min-w-0 flex-1 rounded-md border border-white/10 bg-white/5 text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-brand/50 focus:border-brand/50">
                            <button type="submit"
                                    class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition shrink-0">
                                Buscar
                            </button>
                            @if(request('search'))
                                <a href="{{ route('stores.attribute-groups', $store) }}"
                                   class="px-4 py-2 bg-white/10 text-gray-300 rounded-xl hover:bg-white/20 border border-white/10 shrink-0">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'create-attribute-group')"
                                class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition shrink-0">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            Crear grupo
                        </button>
                    </div>

                    @if($groups->count() > 0)
                        {{-- Vista móvil: cards --}}
                        <div class="md:hidden space-y-4">
                            @foreach($groups as $group)
                                <div x-data="{ expanded: false }" class="border border-white/10 rounded-xl overflow-visible bg-white/[0.02]">
                                    <div @click="expanded = !expanded" class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer hover:bg-white/5">
                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                            <svg class="w-5 h-5 text-gray-400 shrink-0 transition-transform" :class="{ 'rotate-90': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            <span class="font-semibold text-gray-100 truncate">{{ $group->name }}</span>
                                            <span class="text-xs text-gray-500 shrink-0">{{ $group->attributes->count() }} attr.</span>
                                        </div>
                                        <div @click.stop class="shrink-0">
                                            <x-dropdown align="right" width="48" content-classes="py-1 bg-dark-card border border-white/5 shadow-xl ring-1 ring-black/20">
                                                <x-slot name="trigger">
                                                    <button type="button" class="inline-flex items-center justify-center p-2 text-gray-300 hover:text-white border border-white/10 rounded-lg hover:bg-white/5">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                                        </svg>
                                                    </button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-200 hover:bg-white/5" x-on:click="$dispatch('open-create-attribute', { groupId: {{ $group->id }} })">Crear atributo</button>
                                                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-200 hover:bg-white/5" x-on:click="$dispatch('open-edit-attribute-group-modal', { id: {{ $group->id }} })">Editar</button>
                                                    <form action="{{ route('stores.attribute-groups.destroy', [$store, $group]) }}" method="POST" class="block" onsubmit="return confirm('¿Eliminar este grupo? Debes mover o borrar sus atributos antes.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-400 hover:bg-white/5">Eliminar</button>
                                                    </form>
                                                </x-slot>
                                            </x-dropdown>
                                        </div>
                                    </div>
                                    <div x-show="expanded" x-collapse class="border-t border-white/10 rounded-b-xl overflow-hidden px-4 py-3 space-y-3">
                                        @if($group->attributes->count() > 0)
                                            @foreach($group->attributes as $attr)
                                                <div class="flex flex-row items-center justify-between gap-3 py-2 border-b border-white/5 last:border-0">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <span class="font-medium text-gray-200">{{ $attr->name }}</span>
                                                        @if($attr->pivot->is_required)
                                                            <span class="px-2 py-0.5 text-xs rounded shrink-0 bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Requerido</span>
                                                        @else
                                                            <span class="px-2 py-0.5 text-xs rounded shrink-0 bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Opcional</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <button type="button" x-on:click="$dispatch('open-edit-attribute-modal', { id: {{ $attr->id }} })" class="text-indigo-400 hover:text-indigo-300 text-sm font-medium">
                                                            Editar
                                                        </button>
                                                        <form action="{{ route('stores.attribute-groups.attributes.destroy', [$store, $attr]) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este atributo?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm font-medium">Eliminar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <p class="text-sm text-gray-400">Sin atributos. Usa «Crear atributo» en Opciones.</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Vista desktop: tabla (sin overflow en el contenedor para que el menú Opciones no se corte) --}}
                        <div class="hidden md:block border border-white/10 rounded-xl overflow-visible">
                            <table class="min-w-full divide-y divide-white/10 w-full">
                                    <thead class="bg-white/5">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Nombre del grupo</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider w-40">Cantidad</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider w-36 pl-4">Opciones</th>
                                        </tr>
                                    </thead>
                                    @foreach($groups as $group)
                                        <tbody x-data="{ expanded: false }" class="divide-y divide-white/10 border-b border-white/10">
                                        <tr @click="expanded = !expanded"
                                            class="cursor-pointer transition-colors hover:bg-white/5"
                                            :class="{ 'bg-white/[0.07]': expanded }">
                                            <td class="px-6 py-4 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <svg class="w-5 h-5 text-gray-400 transition-transform shrink-0"
                                                         :class="{ 'rotate-90': expanded }"
                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                    </svg>
                                                    <span class="font-semibold text-gray-100">{{ $group->name }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-400 align-middle">
                                                {{ $group->attributes->count() }} atributo(s)
                                            </td>
                                            <td class="px-6 py-4 text-right whitespace-nowrap align-middle" @click.stop>
                                                <div class="flex justify-end">
                                                    <x-dropdown align="right" width="48" content-classes="py-1 bg-dark-card border border-white/10 shadow-xl ring-1 ring-black/20">
                                                    <x-slot name="trigger">
                                                        <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 hover:text-white border border-white/10 rounded-lg hover:bg-white/5 transition">
                                                            Opciones
                                                            <svg class="ml-2 -mr-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                            </svg>
                                                        </button>
                                                    </x-slot>
                                                    <x-slot name="content">
                                                        <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-200 hover:bg-white/5" x-on:click="$dispatch('open-create-attribute', { groupId: {{ $group->id }} })">
                                                            Crear atributo
                                                        </button>
                                                        <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-200 hover:bg-white/5" x-on:click="$dispatch('open-edit-attribute-group-modal', { id: {{ $group->id }} })">
                                                            Editar
                                                        </button>
                                                        <form action="{{ route('stores.attribute-groups.destroy', [$store, $group]) }}" method="POST" class="block"
                                                              onsubmit="return confirm('¿Eliminar este grupo? Debes mover o borrar sus atributos antes.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-400 hover:bg-white/5">Eliminar</button>
                                                        </form>
                                                    </x-slot>
                                                </x-dropdown>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr x-show="expanded"
                                            x-collapse
                                            class="bg-white/[0.03]">
                                            <td colspan="3" class="px-6 py-4">
                                                <div class="pl-8 space-y-3">
                                                    @if($group->attributes->count() > 0)
                                                        @foreach($group->attributes as $attr)
                                                            <div class="flex flex-row items-center justify-between gap-3 py-2 border-b border-white/5 last:border-0">
                                                                <div class="flex items-center gap-3 min-w-0">
                                                                    <span class="font-medium text-gray-200">{{ $attr->name }}</span>
                                                                    @if($attr->pivot->is_required)
                                                                        <span class="px-2 py-0.5 text-xs rounded shrink-0 bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Requerido</span>
                                                                    @else
                                                                        <span class="px-2 py-0.5 text-xs rounded shrink-0 bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Opcional</span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex items-center gap-2 shrink-0">
                                                                    <button type="button"
                                                                            x-on:click="$dispatch('open-edit-attribute-modal', { id: {{ $attr->id }} })"
                                                                            class="text-indigo-400 hover:text-indigo-300 text-sm font-medium">
                                                                        Editar
                                                                    </button>
                                                                    <form action="{{ route('stores.attribute-groups.attributes.destroy', [$store, $attr]) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este atributo?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="text-red-400 hover:text-red-300 text-sm font-medium">Eliminar</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <p class="text-sm text-gray-400">Sin atributos. Usa «Crear atributo» en Opciones para añadir el primero.</p>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    @endforeach
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $groups->withQueryString()->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <p class="text-gray-400">No hay grupos. Crea un grupo y luego añade atributos.</p>
                            <button type="button"
                                    x-on:click="$dispatch('open-modal', 'create-attribute-group')"
                                    class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-white text-sm font-medium hover:bg-indigo-700">
                                Crear primer grupo
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
