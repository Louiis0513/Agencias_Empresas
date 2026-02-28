@php
    $category->load(['children', 'products']);
    $hasChildren = $category->children->count() > 0;
    $hasProducts = $category->products->count() > 0;
@endphp

<div x-data="{ expanded: {{ $level === 0 ? 'true' : 'false' }} }">
    {{-- Header tipo acordeón --}}
    <div class="flex items-center justify-between px-4 py-4 transition-colors"
         :class="expanded ? 'bg-white/10' : 'bg-white/5 hover:bg-white/[0.07]'">
        <div class="flex items-center gap-3 flex-1 min-w-0">
            @if($hasChildren)
                <button type="button"
                        @click="expanded = !expanded"
                        class="shrink-0 p-1 -m-1 text-gray-400 hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            @else
                <div class="w-5 shrink-0"></div>
            @endif

            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-medium text-gray-100 truncate">{{ $category->name }}</h3>
                <div class="mt-0.5 flex items-center gap-4 text-xs text-gray-400">
                    <span class="whitespace-nowrap">{{ $category->products->count() }} Pd</span>
                    @if($hasChildren)
                        <span class="whitespace-nowrap">{{ $category->children->count() }} SC</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="shrink-0 ml-2" @click.stop>
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-300 hover:text-white border border-white/10 rounded-lg hover:bg-white/5 transition">
                        Opciones
                        <svg class="ml-2 -mr-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <a href="{{ route('stores.category.show', [$store, $category]) }}" class="block px-4 py-2 text-sm text-gray-200 hover:bg-white/5">Ver</a>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-200 hover:bg-white/5" x-on:click="$dispatch('open-edit-modal', {{ $category->id }})">Editar</button>
                    <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-200 hover:bg-white/5" x-on:click="$dispatch('open-create-subcategory', { parentId: {{ $category->id }} })">Crear subcategoría</button>
                    <form method="POST" action="{{ route('stores.categories.destroy', [$store, $category]) }}"
                          onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?{{ $hasChildren ? '\\n\\nADVERTENCIA: Esta categoría tiene ' . $category->children->count() . ' subcategoría(s) que también serán eliminadas.' : '' }}{{ $hasProducts ? '\\n\\nADVERTENCIA: Esta categoría tiene ' . $category->products->count() . ' producto(s) asociado(s) que quedarán sin categoría.' : '' }}');"
                          class="block">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-400 hover:bg-white/5">Eliminar</button>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>

    {{-- Contenido expandible: subcategorías con mismo ancho --}}
    @if($hasChildren)
        <div x-show="expanded"
             x-collapse
             class="divide-y divide-white/10">
            @foreach($category->children->sortBy('name')->values() as $child)
                @include('stores.partials.category-item', ['category' => $child, 'level' => $level + 1, 'store' => $store])
            @endforeach
        </div>
    @endif
</div>
