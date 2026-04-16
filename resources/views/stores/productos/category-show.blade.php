<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Detalle de la categoría - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.categories', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver a Categorías
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Cabecera: información de la categoría --}}
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-100">
                                {{ $category->name }}
                            </h3>
                            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                <p>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Atributos:</span>
                                    @if($category->attributes->isNotEmpty())
                                        {{ $category->attributes->pluck('name')->join(', ') }}
                                    @else
                                        <span class="text-gray-500 dark:text-gray-500">Ninguno asignado</span>
                                    @endif
                                </p>
                                <p>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Productos en esta categoría:</span>
                                    {{ $products->count() }}
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('stores.category.attributes', [$store, $category]) }}"
                           class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                            Modificar atributos
                        </a>
                    </div>
                </div>
            </div>

            {{-- Tabla de productos --}}
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-100 mb-4">
                        Productos de esta categoría
                    </h3>
                    @if($products->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Nombre</th>
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
                                                <span class="text-sm font-medium text-gray-100">{{ \App\Support\Quantity::displayStockForProduct($product, $product->stock) }}</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-400 max-w-[10rem] truncate" title="{{ $product->location }}">{{ $product->location ?? '—' }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                    {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('stores.products.show', [$store, $product]) }}" class="text-brand hover:text-white transition">Detalles</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-100">No hay productos en esta categoría</h3>
                            <p class="mt-1 text-sm text-gray-400">Los productos que asignes a esta categoría aparecerán aquí.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
