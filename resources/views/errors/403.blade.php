<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Sin permiso
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-gray-700 dark:text-gray-300">
                    {{ $exception->getMessage() ?: 'No tienes permiso para realizar esta acción en esta tienda.' }}
                </p>
                <div class="mt-4">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Ir al dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
