<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Carrito - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO', 'rowId' => 'venta'])
    @livewire('select-batch-variant-modal', ['storeId' => $store->id])

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <livewire:ventas-carrito :store-id="$store->id" />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
