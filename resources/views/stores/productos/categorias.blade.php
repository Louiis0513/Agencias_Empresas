<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Categorías - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <livewire:create-category-modal :store-id="$store->id" />
    <livewire:edit-category-modal :store-id="$store->id" />

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-dark-card border border-white/5 overflow-visible sm:rounded-xl">
                <div class="p-6">
                    {{-- Cabecera: título, descripción y botón Nueva Categoría --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div>
                            <h1 class="text-xl font-semibold text-gray-100">Gestionar Categorías</h1>
                            <p class="text-sm text-gray-400 mt-1">Organiza las categorías y subcategorías de tu catálogo</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="GET" action="{{ route('stores.categories', $store) }}" class="flex flex-wrap gap-2">
                                <input type="text"
                                       name="search"
                                       value="{{ request('search') }}"
                                       placeholder="Filtrar por nombre"
                                       class="min-w-0 flex-1 sm:w-48 rounded-md border-white/10 bg-white/5 text-gray-100 text-sm px-3 py-2">
                                <button type="submit" class="px-3 py-2 bg-white/10 text-gray-300 rounded-md hover:bg-white/20 text-sm shrink-0">Buscar</button>
                                @if(request('search'))
                                    <a href="{{ route('stores.categories', $store) }}" class="px-3 py-2 text-gray-400 hover:text-gray-200 text-sm shrink-0">Limpiar</a>
                                @endif
                            </form>
                            <button type="button"
                                    x-on:click="$dispatch('open-create-category')"
                                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md bg-brand text-white hover:opacity-90 transition shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Nueva Categoría
                            </button>
                        </div>
                    </div>

                    {{-- Árbol de categorías --}}
                    @if($categoryTree->count() > 0)
                        <div class="border border-white/10 rounded-lg bg-white/[0.02] overflow-visible">
                            <div class="py-1 min-w-fit">
                                @foreach($categoryTree as $index => $category)
                                    @include('stores.partials.category-item', [
                                        'category' => $category,
                                        'level' => 0,
                                        'store' => $store,
                                        'isLast' => $index === $categoryTree->count() - 1,
                                        'parentLines' => [],
                                    ])
                                @endforeach
                            </div>
                        </div>
                        <div class="mt-4">
                            {{ $categoryTree->withQueryString()->links() }}
                        </div>
                    @else
                        <div class="border border-white/10 rounded-lg bg-white/[0.02] py-12 text-center">
                            <p class="text-sm text-gray-400">No hay categorías. Crea la primera.</p>
                            <button type="button"
                                    x-on:click="$dispatch('open-create-category')"
                                    class="mt-4 inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-md bg-brand text-white hover:opacity-90 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Nueva Categoría
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
