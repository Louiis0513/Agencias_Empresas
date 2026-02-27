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
            <div class="bg-dark-card border border-white/5 sm:rounded-xl overflow-visible">
                <div class="p-6">
                    {{-- Filtro y botón crear categoría --}}
                    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <form method="GET" action="{{ route('stores.categories', $store) }}" class="flex-1 w-full flex flex-wrap gap-2">
                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Filtrar por nombre de categoría"
                                   class="min-w-0 flex-1 rounded-md border-white/10 bg-white/5 text-gray-100">
                            <button type="submit"
                                    class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition shrink-0">
                                Buscar
                            </button>
                            @if(request('search'))
                                <a href="{{ route('stores.categories', $store) }}"
                                   class="px-4 py-2 bg-white/10 text-gray-300 rounded-xl hover:bg-white/20 border border-white/10 shrink-0">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                        <button type="button"
                                x-on:click="$dispatch('open-create-category')"
                                class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition shrink-0">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Categoría
                        </button>
                    </div>

                    {{-- Árbol de categorías --}}
                    @if($categoryTree->count() > 0)
                        <div class="border border-white/10 rounded-xl overflow-visible divide-y divide-white/10">
                            @foreach($categoryTree as $category)
                                @include('stores.partials.category-item', ['category' => $category, 'level' => 0, 'store' => $store])
                            @endforeach
                        </div>
                        <div class="mt-4">
                            {{ $categoryTree->withQueryString()->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-100">No hay categorías</h3>
                            <p class="mt-1 text-sm text-gray-400">Comienza creando tu primera categoría para organizar tus productos.</p>
                            <div class="mt-4">
                                <button type="button"
                                        x-on:click="$dispatch('open-create-category')"
                                        class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Crear Categoría
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
