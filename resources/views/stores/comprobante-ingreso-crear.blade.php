<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Nuevo Comprobante de Ingreso (Ingreso manual) - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.comprobantes-ingreso.index', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">← Volver</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Enlace a Cuentas por cobrar: los cobros se registran desde ahí --}}
            <div class="mb-6 p-4 bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-700 rounded-lg">
                <p class="text-sm text-sky-800 dark:text-sky-200 mb-2">
                    ¿Necesita registrar un cobro a una cuenta por cobrar?
                </p>
                <a href="{{ route('stores.accounts-receivables', $store) }}" class="inline-flex items-center px-4 py-2 bg-sky-600 text-white rounded-md hover:bg-sky-700 text-sm font-medium">
                    Ir a Cuentas por cobrar →
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('stores.comprobantes-ingreso.store', $store) }}">
                    @csrf
                    <input type="hidden" name="type" value="INGRESO_MANUAL">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                                <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                                <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Opcional">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destino del dinero (bolsillo(s))</label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Puede indicar una referencia por línea (ej. caja menor, depósito, transferencia).</p>
                            <div id="parts-container">
                                <div class="flex flex-wrap items-end gap-2 mb-2">
                                    <select name="parts[0][bolsillo_id]" class="flex-1 min-w-[140px] rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                        <option value="">Seleccionar bolsillo</option>
                                        @foreach($bolsillos as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="parts[0][amount]" step="0.01" min="0.01" placeholder="Monto" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                    <input type="text" name="parts[0][reference]" value="{{ old('parts.0.reference') }}" class="w-40 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ref. (opcional)" maxlength="100">
                                </div>
                            </div>
                            <button type="button" id="add-part" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar bolsillo</button>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Crear comprobante</button>
                        <a href="{{ route('stores.comprobantes-ingreso.index', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @php
        $bolsillosForJs = $bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values()->all();
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let partIndex = 1;
            const bolsillos = @json($bolsillosForJs);
            document.getElementById('add-part').addEventListener('click', function() {
                const div = document.createElement('div');
                div.className = 'flex flex-wrap items-end gap-2 mb-2';
                div.innerHTML = `
                    <select name="parts[${partIndex}][bolsillo_id]" class="flex-1 min-w-[140px] rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                        <option value="">Seleccionar bolsillo</option>
                        ${bolsillos.map(b => `<option value="${b.id}">${b.name}</option>`).join('')}
                    </select>
                    <input type="number" name="parts[${partIndex}][amount]" step="0.01" min="0.01" placeholder="Monto" class="w-28 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                    <input type="text" name="parts[${partIndex}][reference]" class="w-40 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ref. (opcional)" maxlength="100">
                `;
                document.getElementById('parts-container').appendChild(div);
                partIndex++;
            });
        });
    </script>
</x-app-layout>
