<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Abrir caja — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.cajas', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">← Volver a Caja</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if($bolsillos->isEmpty())
                <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
                    <p class="text-sm text-amber-800 dark:text-amber-200">No hay bolsillos activos. Cree al menos un bolsillo desde la página de Caja antes de abrir.</p>
                    <a href="{{ route('stores.cajas', $store) }}" class="inline-block mt-2 text-indigo-600 dark:text-indigo-400 hover:underline">Ir a Caja</a>
                </div>
            @else
            <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">Ingrese el saldo físico contado en cada bolsillo. Si hay diferencia con el saldo esperado, se generará un ajuste automático.</p>

            <form method="POST" action="{{ route('stores.cajas.apertura.store', $store) }}">
                @csrf
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-6">
                    @foreach($bolsillos as $b)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $b->name }}</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Saldo esperado: ${{ number_format($saldosEsperados[$b->id] ?? 0, 2) }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Saldo físico contado</label>
                                <input type="number" name="saldo_fisico[{{ $b->id }}]" value="{{ old('saldo_fisico.'.$b->id, '0') }}" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                            </div>
                        </div>
                    @endforeach

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nota (opcional)</label>
                        <textarea name="nota_apertura" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: Caja revisada por la mañana">{{ old('nota_apertura') }}</textarea>
                    </div>

                    <div class="flex gap-2 pt-4">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">Abrir sesión de caja</button>
                        <a href="{{ route('stores.cajas', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
                    </div>
                </div>
            </form>
            @endif
        </div>
    </div>
</x-app-layout>
