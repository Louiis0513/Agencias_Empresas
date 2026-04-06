<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Inventario — {{ $store->name }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('stores.inventario.export-excel', $store) }}" class="text-sm px-3 py-2 rounded-lg bg-brand/20 text-brand border border-brand/30 hover:bg-brand/30 transition">
                    Descargar Excel
                </a>
                <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    ← Volver al Resumen
                </a>
            </div>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO', 'rowId' => 'inventario-filtro'])
    @livewire('select-batch-variant-modal', ['storeId' => $store->id])
    @livewire('select-serial-for-filter-modal', ['storeId' => $store->id])
    <livewire:create-movimiento-inventario-modal :store-id="$store->id" />

    <div class="py-12" x-data="{
        productId: '{{ request('product_id') ?? '' }}',
        productName: @js($productoSeleccionado?->name ?? ''),
        productDisplay: @js($productoSeleccionadoDisplay ?? ''),
        productVariantId: '{{ request('product_variant_id') ?? '' }}',
        productItemId: '{{ request('product_item_id') ?? '' }}',
        pendingProductId: '',
        pendingProductName: '',
        pendingProductType: ''
    }"
    @item-selected.window="
        if ($event.detail.rowId === 'inventario-filtro') {
            const pt = $event.detail.productType || 'simple';
            if (pt === 'simple') {
                productId = String($event.detail.id ?? '');
                productName = $event.detail.name ?? '';
                productDisplay = productName;
                productVariantId = '';
                productItemId = '';
            } else if (pt === 'batch') {
                const variantId = $event.detail.productVariantId || null;
                if (variantId) {
                    // Variante ya resuelta: asignar directamente sin abrir modal de variantes
                    productId = String($event.detail.id ?? '');
                    productVariantId = String(variantId);
                    productName = $event.detail.name ?? '';
                    productDisplay = productName;
                    productItemId = '';
                    pendingProductId = '';
                    pendingProductName = '';
                    pendingProductType = '';
                } else {
                    pendingProductId = $event.detail.id;
                    pendingProductName = $event.detail.name ?? '';
                    pendingProductType = 'batch';
                    Livewire.dispatch('open-select-batch-variant', { productId: parseInt($event.detail.id), rowId: 'inventario-filtro', productName: pendingProductName, variantKeysInCart: [] });
                }
            } else if (pt === 'serialized') {
                const itemId = $event.detail.productItemId || null;
                if (itemId) {
                    // Ítem serializado ya resuelto: asignar directamente sin abrir modal de seriales
                    productId = String($event.detail.id ?? '');
                    productItemId = String(itemId);
                    productName = $event.detail.name ?? '';
                    productDisplay = productName;
                    productVariantId = '';
                    pendingProductId = '';
                    pendingProductName = '';
                    pendingProductType = '';
                } else {
                    pendingProductId = $event.detail.id;
                    pendingProductName = $event.detail.name ?? '';
                    pendingProductType = 'serialized';
                    Livewire.dispatch('open-select-serial-for-filter', { productId: parseInt($event.detail.id), productName: pendingProductName });
                }
            }
        }
    "
    @batch-variant-selected.window="
        if ($event.detail.rowId === 'inventario-filtro') {
            productId = String($event.detail.productId ?? '');
            productVariantId = String($event.detail.productVariantId ?? '');
            productDisplay = (pendingProductName || productName || '') + ($event.detail.displayName ? ' (' + $event.detail.displayName + ')' : '');
            productItemId = '';
            pendingProductId = '';
            pendingProductName = '';
            pendingProductType = '';
        }
    "
    @filter-serial-selected.window="
        const d = $event.detail;
        if (d && d.productId) {
            productId = String(d.productId);
            productItemId = String(d.productItemId ?? '');
            productDisplay = (d.productName || '') + (d.serialNumber ? ' (Serial: ' + d.serialNumber + ')' : '');
            productVariantId = '';
            pendingProductId = '';
            pendingProductName = '';
            pendingProductType = '';
        }
    ">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

                <div class="flex flex-wrap justify-between items-center gap-4">
                    <button type="button" x-on:click="$dispatch('open-modal', 'create-movimiento-inventario')" class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] font-medium">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Registrar movimiento
                    </button>
                </div>
            

            <form method="GET" action="{{ route('stores.inventario', $store) }}" class="mb-6 flex flex-wrap gap-2 items-end">
                <input type="hidden" name="product_id" :value="productId">
                <input type="hidden" name="product_variant_id" :value="productVariantId">
                <input type="hidden" name="product_item_id" :value="productItemId">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Producto</label>
                    <div class="flex gap-1 items-center">
                        <span class="min-w-[160px] px-3 py-2 rounded-md border border-white/10 bg-white/5 text-gray-100 text-sm" x-text="productDisplay || productName || 'Todos'"></span>
                        <button type="button" @click="Livewire.dispatch('open-select-item-for-row', { rowId: 'inventario-filtro', itemType: 'INVENTARIO', productIdsInCartSimple: [], productVariantIdsInDocument: [] })" class="px-3 py-2 rounded-md border border-white/10 bg-white/5 text-gray-300 hover:bg-white/10 text-sm">
                            Seleccionar
                        </button>
                        <button type="button" x-show="productId" @click="productId = ''; productName = ''; productDisplay = ''; productVariantId = ''; productItemId = ''" class="px-3 py-2 rounded-md border border-white/10 bg-white/5 text-gray-400 hover:bg-white/10 text-sm">Limpiar</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                    <select name="type" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                        <option value="">Todos</option>
                        <option value="ENTRADA" {{ request('type') === 'ENTRADA' ? 'selected' : '' }}>Entrada</option>
                        <option value="SALIDA" {{ request('type') === 'SALIDA' ? 'selected' : '' }}>Salida</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Desde</label>
                    <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar por descripción</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Descripción del movimiento..." class="rounded-md border-white/10 bg-white/5 text-gray-100 min-w-[180px]">
                </div>
                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Filtrar</button>
                @if(request()->anyFilled(['product_id', 'product_variant_id', 'product_item_id', 'type', 'fecha_desde', 'fecha_hasta', 'search']))
                    <a href="{{ route('stores.inventario', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Limpiar</a>
                @endif
            </form>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    @if($movimientos->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Producto</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cantidad</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Costo unit.</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Usuario</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($movimientos as $m)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-3 text-sm text-gray-100">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-100">{{ $m->product_display ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm font-semibold {{ $m->type === 'ENTRADA' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }}">
                                                {{ $m->type === 'ENTRADA' ? '+' : '-' }}{{ $m->quantity }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-400">
                                                {{ $m->unit_cost !== null ? money($m->unit_cost, $store->currency ?? 'COP', false) : '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-400">{{ $m->description ?? '—' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-400">{{ $m->user->name ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $movimientos->withQueryString()->links() }}</div>
                    @else
                        <p class="text-center text-gray-400 py-8">
                            @if(request()->anyFilled(['product_id', 'product_variant_id', 'product_item_id', 'type', 'fecha_desde', 'fecha_hasta', 'search']))
                                No hay movimientos con los filtros aplicados.
                            @else
                                No hay movimientos de inventario. Registra una entrada o salida (solo productos con type «producto»).
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
