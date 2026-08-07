<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ __('Productos') }} — {{ $store->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @include('stores.partials.flujo-productos-incompleto')

            <div class="bg-dark-card border border-white/5 rounded-2xl p-8 text-center">
                <p class="text-gray-400 text-sm">
                    No hay catálogo activo. Cuando se rediseñe el maestro de productos/servicios, aparecerá aquí.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
