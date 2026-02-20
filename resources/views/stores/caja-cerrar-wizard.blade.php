<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Cerrar caja — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.cajas', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">← Volver a Caja</a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ step: 1 }">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Paso 1: Operaciones de última hora --}}
            <div x-show="step === 1" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Paso 1: Operaciones de última hora</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Registre retiros del dueño o traslados entre bolsillos antes del conteo. Saldo esperado actual por bolsillo:</p>
                <ul class="list-disc list-inside mb-4 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    @foreach($bolsillos as $b)
                        <li><strong>{{ $b->name }}</strong>: ${{ number_format($b->saldo, 2) }}</li>
                    @endforeach
                </ul>
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="{{ route('stores.comprobantes-egreso.create', $store) }}?retiro=1" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 text-sm">Registrar retiro (comprobante de egreso)</a>
                    <a href="{{ route('stores.comprobantes-ingreso.create', $store) }}" class="inline-flex items-center px-4 py-2 bg-sky-600 text-white rounded-md hover:bg-sky-700 text-sm">Comprobante de ingreso</a>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Para traslados: registre un egreso desde el bolsillo origen y un ingreso al bolsillo destino.</p>
                <button type="button" x-on:click="step = 2" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium">Continuar al conteo físico</button>
            </div>

            {{-- Paso 2: Conteo físico y cierre --}}
            <div x-show="step === 2" x-cloak class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Paso 2: Conteo físico y cierre</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Ingrese cuánto dinero físico hay en cada bolsillo. Si hay descuadre, se generará un ajuste automático.</p>

                <form method="POST" action="{{ route('stores.cajas.cerrar.store', $store) }}">
                    @csrf
                    <div class="space-y-4 mb-6">
                        @foreach($bolsillos as $b)
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $b->name }}</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Saldo esperado: ${{ number_format($b->saldo, 2) }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Cuánto hay en {{ $b->name }}?</label>
                                    <input type="number" name="saldo_fisico[{{ $b->id }}]" value="{{ old('saldo_fisico.'.$b->id, $b->saldo) }}" step="0.01" min="0" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                </div>
                            </div>
                        @endforeach

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nota del cajero (opcional)</label>
                            <textarea name="nota_cierre" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: Descuadre por billete falso">{{ old('nota_cierre') }}</textarea>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" x-on:click="step = 1" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Atrás</button>
                        <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 font-medium">Cerrar sesión de caja</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
