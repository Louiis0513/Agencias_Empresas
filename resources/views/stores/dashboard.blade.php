<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{-- Mostramos el nombre de la tienda actual --}}
                Gestionando: {{ $store->name }}
            </h2>
            <span class="px-3 py-1 text-sm bg-indigo-100 text-indigo-800 rounded-full">
                {{-- Aquí podríamos mostrar el rol más adelante --}}
                Panel Principal
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    ¡Bienvenido a la administración de <strong>{{ $store->name }}</strong>!
                    <br><br>
                    Aquí irán los menús de Inventario, Ventas, Usuarios, etc.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>