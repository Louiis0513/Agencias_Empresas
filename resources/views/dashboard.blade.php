<x-app-layout>
    <x-slot name="header">
        {{-- Encabezado con el título y el botón alineados --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            
            {{-- AQUÍ INYECTAMOS EL BOTÓN --}}
            <livewire:create-store-modal />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <h3 class="text-lg font-bold mb-4 text-gray-900 dark:text-gray-100 px-4 sm:px-0">
                Mis Tiendas
            </h3>

            {{-- Lógica: Si no hay tiendas, mostramos aviso. Si hay, mostramos grid --}}
            @if(Auth::user()->stores->isEmpty())
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">
                    <p>No tienes tiendas asociadas aún.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 px-4 sm:px-0">
                    @foreach(Auth::user()->stores as $store)
                        <div
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition duration-300">
                            <div class="p-6 text-gray-900 dark:text-gray-100">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="text-xl font-bold text-indigo-500">{{ $store->name }}</h4>
                                        <p class="text-sm text-gray-500 mt-1">Slug: {{ $store->slug }}</p>
                                        <p class="text-xs text-gray-400 mt-2">
                                            Rol:
                                            <span class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">
                                                {{ $store->pivot->role_id ? 'Empleado' : 'Dueño' }}
                                            </span>
                                        </p>
                                    </div>
                                    <div
                                        class="h-10 w-10 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center text-indigo-600 dark:text-indigo-300">
                                        {{-- Icono simple de tienda --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.615V21m-9-15V3m0 12v-2.5" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <a href="{{ route('stores.dashboard', $store) }}"
                                        class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                        Entrar a gestionar &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>