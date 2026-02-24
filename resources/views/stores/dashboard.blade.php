<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Gestionando: <span class="text-brand">{{ $store->name }}</span>
            </h2>
            <span class="px-3 py-1 text-sm bg-brand/20 text-brand border border-brand/30 rounded-full font-medium">
                Panel Principal
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6 text-gray-100">
                    ¡Bienvenido a la administración de <strong class="text-white">{{ $store->name }}</strong>!
                    <br><br>
                    Aquí irán los menús de Inventario, Ventas, Usuarios, etc.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
