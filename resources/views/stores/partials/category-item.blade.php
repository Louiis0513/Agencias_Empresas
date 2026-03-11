@php
    $category->load(['children', 'products']);
    $hasChildren = $category->children->count() > 0;
    $hasProducts = $category->products->count() > 0;
    $parentLines = $parentLines ?? [];
    $INDENT = 24;
@endphp

<div x-data="{ expanded: {{ $level < 2 ? 'true' : 'false' }} }">
    {{-- Fila con líneas del árbol --}}
    <div class="group relative flex items-center justify-between w-full rounded-md hover:bg-white/5 transition-colors duration-150"
         style="padding-left: {{ $level * $INDENT + 12 }}px; padding-right: 12px; padding-top: 8px; padding-bottom: 8px;">
        {{-- Líneas verticales de niveles padre --}}
        @if($level > 0)
            @foreach($parentLines as $i => $showLine)
                @if($showLine)
                    <div class="absolute top-0 h-full w-px bg-white/20" style="left: {{ $i * $INDENT + 20 }}px;" aria-hidden="true"></div>
                @endif
            @endforeach
            {{-- Conector vertical desde el padre hasta esta fila --}}
            <div class="absolute bg-white/20"
                 style="left: {{ ($level - 1) * $INDENT + 20 }}px; top: 0; width: 1px; height: {{ $isLast ? '50%' : '100%' }};"></div>
            {{-- Conector horizontal hasta el nodo --}}
            <div class="absolute bg-white/20"
                 style="left: {{ ($level - 1) * $INDENT + 20 }}px; top: 50%; width: 16px; height: 1px;"></div>
        @endif

        {{-- Contenido izquierdo: chevron/círculo + nombre + (N) --}}
        <div class="flex items-center gap-1.5 relative z-10 min-w-0 flex-1">
            @if($hasChildren)
                <button type="button"
                        @click="expanded = !expanded"
                        class="flex items-center justify-center w-5 h-5 shrink-0 rounded hover:bg-white/10 transition-colors text-gray-400 hover:text-gray-200">
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-90': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            @else
                <div class="flex items-center justify-center w-5 h-5 shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span>
                </div>
            @endif
            <span class="text-sm font-medium text-gray-100 truncate select-none">{{ $category->name }}</span>
            @if($hasChildren)
                <span class="text-xs text-gray-400 tabular-nums ml-1 shrink-0">({{ $category->children->count() }})</span>
            @endif
        </div>

        {{-- Acciones: iconos rápidos + menú Opciones --}}
        <div class="flex items-center gap-1 shrink-0 md:opacity-0 md:group-hover:opacity-100 transition-opacity duration-150" @click.stop>
            <button type="button"
                    x-on:click="$dispatch('open-create-subcategory', { parentId: {{ $category->id }} })"
                    class="flex items-center justify-center w-7 h-7 rounded hover:bg-white/10 transition-colors text-gray-400 hover:text-gray-200"
                    title="Añadir subcategoría">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
            <a href="{{ route('stores.category.show', [$store, $category]) }}"
               class="flex items-center justify-center w-7 h-7 rounded hover:bg-white/10 transition-colors text-gray-400 hover:text-gray-200"
               title="Ver categoría">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
            </a>
            <button type="button"
                    x-on:click="$dispatch('open-edit-modal', {{ $category->id }})"
                    class="flex items-center justify-center w-7 h-7 rounded hover:bg-white/10 transition-colors text-gray-400 hover:text-gray-200"
                    title="Editar">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                </svg>
            </button>
            <form method="POST" action="{{ route('stores.categories.destroy', [$store, $category]) }}"
                  class="inline"
                  onsubmit="return confirm('¿Eliminar esta categoría?{{ $hasChildren ? ' Se eliminarán también ' . $category->children->count() . ' subcategoría(s).' : '' }}{{ $hasProducts ? ' Tiene ' . $category->products->count() . ' producto(s) asociado(s).' : '' }}');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="flex items-center justify-center w-7 h-7 rounded hover:bg-red-500/10 transition-colors text-red-400 hover:text-red-300"
                        title="Eliminar">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </form>
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button" class="inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-300 hover:text-white border border-white/10 rounded-md hover:bg-white/5 transition">
                        Opciones
                        <svg class="ml-1 -mr-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    {{-- Hijos (expandible) --}}
    @if($hasChildren)
        <div x-show="expanded"
             x-collapse
             class="overflow-visible">
            @foreach($category->children->sortBy('name')->values() as $childIndex => $child)
                @include('stores.partials.category-item', [
                    'category' => $child,
                    'level' => $level + 1,
                    'store' => $store,
                    'isLast' => $childIndex === $category->children->count() - 1,
                    'parentLines' => array_merge($parentLines, [ !($childIndex === $category->children->count() - 1) ]),
                ])
            @endforeach
        </div>
    @endif
</div>
