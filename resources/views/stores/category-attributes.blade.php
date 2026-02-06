<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Atributos de "{{ $category->name }}" - {{ $store->name }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('stores.categories', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    ← Volver a Categorías
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Mensajes de éxito/error --}}
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Grupos de atributos
                    </h3>

                    @php
                        $hasAnyAttribute = $storeAttributeGroups->sum(fn ($g) => $g->attributes->count()) > 0;
                    @endphp
                    @if($hasAnyAttribute)
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Asigna <strong>grupos de atributos</strong> a esta categoría. La categoría tendrá todos los atributos del grupo; si un atributo es requerido, ya quedó definido al crear el atributo en el grupo.
                        </p>
                        <form method="POST" action="{{ route('stores.category.attributes.assign', [$store, $category]) }}">
                            @csrf
                            <div class="space-y-3">
                                @foreach($storeAttributeGroups as $group)
                                    @if($group->attributes->isEmpty())
                                        @continue
                                    @endif
                                    @php
                                        $categoryAttrIds = $categoryAttributes->pluck('id')->all();
                                        $groupAttrIds = $group->attributes->pluck('id')->all();
                                        $groupFullyAssigned = count($groupAttrIds) > 0 && count(array_intersect($groupAttrIds, $categoryAttrIds)) === count($groupAttrIds);
                                        $totalInGroup = $group->attributes->count();
                                    @endphp
                                    <div x-data="{ open: false }"
                                         class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden bg-gray-50/50 dark:bg-gray-800/50">
                                        <div class="flex items-center gap-4 px-4 py-3">
                                            <label class="flex items-center gap-3 cursor-pointer shrink-0">
                                                <input type="checkbox"
                                                       name="attribute_group_ids[]"
                                                       value="{{ $group->id }}"
                                                       {{ $groupFullyAssigned ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                                                <span class="font-semibold text-gray-800 dark:text-gray-200">Incluir grupo «{{ $group->name }}» en la categoría</span>
                                            </label>
                                            <button type="button"
                                                    @click="open = !open"
                                                    class="ml-auto flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                                <span x-text="open ? 'Ocultar' : 'Ver atributos'">Ver atributos</span>
                                                <span class="text-gray-400 dark:text-gray-500">({{ $totalInGroup }})</span>
                                                <svg class="w-5 h-5 shrink-0 transition-transform"
                                                     :class="{ 'rotate-180': open }"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <div x-show="open"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0"
                                             x-transition:enter-end="opacity-100"
                                             x-transition:leave="transition ease-in duration-150"
                                             x-transition:leave-start="opacity-100"
                                             x-transition:leave-end="opacity-0"
                                             class="border-t border-gray-200 dark:border-gray-600">
                                            <div class="p-4 pt-3 space-y-2 bg-white dark:bg-gray-800">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Atributos de este grupo (definidos al crear el atributo en el grupo):</p>
                                                @foreach($group->attributes as $attribute)
                                                    @php
                                                        $requiredInGroup = $attribute->pivot->is_required ?? false;
                                                    @endphp
                                                    <div class="flex items-center gap-3 p-3 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-700/30">
                                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $attribute->name }}</span>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">({{ $attribute->type }})</span>
                                                        @if($requiredInGroup)
                                                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Requerido</span>
                                                        @endif
                                                        @if($attribute->isSelectType() && $attribute->options->count() > 0)
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">Opciones: {{ $attribute->options->pluck('value')->join(', ') }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-primary-button type="submit">
                                    Guardar grupos asignados
                                </x-primary-button>
                            </div>
                        </form>
                    @else
                        <div class="text-center py-8">
                            <p class="text-sm text-gray-500 dark:text-gray-400">No hay atributos. Crea grupos y atributos en <strong>Grupos de atributos</strong>.</p>
                            <a href="{{ route('stores.attribute-groups', $store) }}" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 rounded-md text-white text-sm font-medium hover:bg-indigo-700">
                                Ir a Grupos de atributos
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Atributos actualmente asignados (por grupo) --}}
            @if($categoryAttributes->count() > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Atributos Asignados a esta Categoría
                        </h3>
                        @php
                            $assignedByGroup = [];
                            foreach ($categoryAttributes as $attr) {
                                $g = $attr->groups->first();
                                $kn = $g ? $g->name : 'Sin grupo';
                                if (!isset($assignedByGroup[$kn])) {
                                    $assignedByGroup[$kn] = [];
                                }
                                $assignedByGroup[$kn][] = $attr;
                            }
                        @endphp
                        <div class="space-y-4">
                            @foreach($assignedByGroup as $groupName => $attrs)
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $groupName }}</h4>
                                    <div class="space-y-2">
                                        @foreach($attrs as $attribute)
                                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                                <div>
                                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $attribute->name }}</span>
                                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">({{ $attribute->type }})</span>
                                                    @if($attribute->pivot->is_required)
                                                        <span class="ml-2 px-2 py-0.5 text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded">Requerido</span>
                                                    @endif
                                                    @if($attribute->isSelectType() && $attribute->options->count() > 0)
                                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                            Opciones: {{ $attribute->options->pluck('value')->join(', ') }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
