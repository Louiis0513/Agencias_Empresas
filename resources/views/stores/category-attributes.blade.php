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

    <livewire:create-attribute-modal :store-id="$store->id" />

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

            {{-- Botón crear atributo --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Atributos Disponibles en la Tienda
                        </h3>
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'create-attribute')"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Atributo
                        </button>
                    </div>

                    @php
                        $hasAnyAttribute = $storeAttributeGroups->sum(fn ($g) => $g->attributes->count()) > 0;
                    @endphp
                    @if($hasAnyAttribute)
                        <form method="POST" action="{{ route('stores.category.attributes.assign', [$store, $category]) }}">
                            @csrf
                            <div class="space-y-6">
                                @foreach($storeAttributeGroups as $group)
                                    @if($group->attributes->isEmpty())
                                        @continue
                                    @endif
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $group->name }}</h4>
                                        <div class="space-y-3">
                                            @foreach($group->attributes as $attribute)
                                                @php
                                                    $isAssigned = $categoryAttributes->contains('id', $attribute->id);
                                                    $pivot = $isAssigned ? $categoryAttributes->firstWhere('id', $attribute->id)->pivot : null;
                                                    $defaultRequired = $attribute->pivot->is_required ?? false;
                                                @endphp
                                                <label class="flex items-start space-x-3 p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                                    <input type="checkbox"
                                                           name="attribute_ids[]"
                                                           value="{{ $attribute->id }}"
                                                           {{ $isAssigned ? 'checked' : '' }}
                                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    <div class="flex-1">
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $attribute->name }}</span>
                                                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">({{ $attribute->type }})</span>
                                                                @if($defaultRequired)
                                                                    <span class="ml-2 text-xs text-amber-600 dark:text-amber-400">requerido en grupo</span>
                                                                @endif
                                                            </div>
                                                            <label class="flex items-center text-sm">
                                                                <input type="checkbox"
                                                                       name="required[{{ $attribute->id }}]"
                                                                       value="1"
                                                                       {{ ($pivot && $pivot->is_required) || $defaultRequired ? 'checked' : '' }}
                                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                                <span class="ml-2 text-gray-600 dark:text-gray-400">Requerido</span>
                                                            </label>
                                                        </div>
                                                        @if($attribute->isSelectType() && $attribute->options->count() > 0)
                                                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                                Opciones: {{ $attribute->options->pluck('value')->join(', ') }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-primary-button type="submit">
                                    Guardar Atributos
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
