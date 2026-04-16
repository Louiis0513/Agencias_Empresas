<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Productos - <span class="text-brand">{{ $store->name }}</span>
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition" wire:navigate>
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <livewire:create-product-modal :store-id="$store->id" />
    <livewire:edit-product-modal :store-id="$store->id" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-4 text-sm text-emerald-400">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/20 p-4 text-sm text-red-400">
                    {{ session('error') }}
                </div>
            @endif
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    {{-- Filtros y botón crear --}}
                    <div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <form method="GET" action="{{ route('stores.products', $store) }}" class="flex-1 w-full flex flex-wrap gap-2 items-end">
                            <div class="min-w-0 flex-1">
                                <label for="search" class="block text-xs font-medium text-gray-400 mb-1">Buscar</label>
                                <input type="text"
                                       id="search"
                                       name="search"
                                       value="{{ request('search') }}"
                                       placeholder="Nombre, SKU, código de barras..."
                                       class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            </div>
                            <div class="min-w-[140px]">
                                <label for="category_id" class="block text-xs font-medium text-gray-400 mb-1">Categoría</label>
                                <select id="category_id" name="category_id" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                                    <option value="">Todas</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button type="submit"
                                        class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                                    Buscar
                                </button>
                                @if(request('search') || request('category_id'))
                                    <a href="{{ route('stores.products', $store) }}"
                                       class="px-4 py-2 bg-white/10 text-gray-300 rounded-xl hover:bg-white/20 border border-white/10">
                                        Limpiar
                                    </a>
                                @endif
                            </div>
                        </form>
                        <button type="button"
                                x-on:click="$dispatch('open-modal', 'create-product')"
                                class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2.5 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition shrink-0">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Crear Producto
                        </button>
                    </div>

                    @if($products->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead>
                                    <tr class="border-b border-white/5">
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Nombre</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Categoría</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Stock</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Ubicación</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Estado</th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($products as $product)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-100">{{ $product->name }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-400">{{ $product->category?->name ?? '—' }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-100">{{ \App\Support\Quantity::displayStockForProduct($product, $product->stock) }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-400 max-w-[10rem] truncate" title="{{ $product->location }}">{{ $product->location ?? '—' }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->is_active ? 'bg-brand/10 text-brand border border-brand/20' : 'bg-white/10 text-gray-400 border border-white/10' }}">
                                                    {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('stores.products.show', [$store, $product]) }}" class="text-brand hover:text-white transition" wire:navigate>Detalles</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Paginación --}}
                        <div class="mt-4">
                            {{ $products->links() }}
                        </div>
                    @else
                        @php $hasFilters = request('search') || request('category_id'); @endphp
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            @if($hasFilters)
                                <h3 class="mt-2 text-sm font-medium text-gray-100">No se encontraron productos</h3>
                                <p class="mt-1 text-sm text-gray-400">No hay productos con los filtros indicados. Prueba con otros criterios.</p>
                                <div class="mt-4">
                                    <a href="{{ route('stores.products', $store) }}" class="inline-flex items-center px-4 py-2.5 bg-white/10 text-gray-300 rounded-xl hover:bg-white/20 border border-white/10">
                                        Limpiar filtros
                                    </a>
                                </div>
                            @else
                                <h3 class="mt-2 text-sm font-medium text-gray-100">No hay productos</h3>
                                <p class="mt-1 text-sm text-gray-400">Comienza creando tu primer producto para esta tienda.</p>
                                <div class="mt-4">
                                    <button type="button" x-on:click="$dispatch('open-modal', 'create-product')" class="inline-flex items-center px-4 py-2.5 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Crear Producto
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
