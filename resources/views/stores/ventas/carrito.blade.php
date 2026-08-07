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

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('stores.partials.flujo-ventas-incompleto')
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl p-8 text-center">
                <p class="text-gray-400 text-sm">
                    El carrito de ventas no está disponible hasta el rediseño contable del flujo de venta.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
