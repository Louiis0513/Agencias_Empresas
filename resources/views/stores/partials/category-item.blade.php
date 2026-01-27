@php
    $category->load(['children', 'products']);
    $hasChildren = $category->children->count() > 0;
    $hasProducts = $category->products->count() > 0;
@endphp

<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 {{ $level > 0 ? 'ml-8 mt-2' : '' }}" x-data="{ expanded: {{ $level === 0 ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3 flex-1">
            @if($hasChildren)
                <button @click="expanded = !expanded" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-90': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            @else
                <div class="w-5"></div>
            @endif

            <div class="flex-1">
                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ $category->name }}
                </h3>
                <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ $category->products->count() }} producto(s)</span>
                    @if($hasChildren)
                        <span>{{ $category->children->count() }} subcategoría(s)</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <a href="{{ route('stores.category.attributes', [$store, $category]) }}" 
               class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                Atributos
            </a>
            <button class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium"
                    x-on:click="$dispatch('open-edit-modal', {{ $category->id }})">
                Editar
            </button>
            <form method="POST" action="{{ route('stores.categories.destroy', [$store, $category]) }}" 
                  onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?{{ $hasChildren ? '\\n\\nADVERTENCIA: Esta categoría tiene ' . $category->children->count() . ' subcategoría(s) que también serán eliminadas.' : '' }}{{ $hasProducts ? '\\n\\nADVERTENCIA: Esta categoría tiene ' . $category->products->count() . ' producto(s) asociado(s) que quedarán sin categoría.' : '' }}');"
                  class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium"
                        title="Eliminar categoría{{ $hasChildren ? ' (incluye subcategorías)' : '' }}{{ $hasProducts ? ' (los productos quedarán sin categoría)' : '' }}">
                    Eliminar
                </button>
            </form>
        </div>
    </div>

    @if($hasChildren)
        <div x-show="expanded" x-collapse class="mt-3 space-y-2">
            @foreach($category->children as $child)
                @include('stores.partials.category-item', ['category' => $child, 'level' => $level + 1, 'store' => $store])
            @endforeach
        </div>
    @endif
</div>
