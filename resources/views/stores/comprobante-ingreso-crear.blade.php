<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Nuevo Comprobante de Ingreso - {{ $store->name }}
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('stores.comprobantes-ingreso.store', $store) }}">
                    @csrf
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                                <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                                <select name="type" id="tipo-ingreso" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="INGRESO_MANUAL" {{ old('type', 'INGRESO_MANUAL') == 'INGRESO_MANUAL' ? 'selected' : '' }}>Ingreso manual</option>
                                    <option value="COBRO_CUENTA" {{ old('type') == 'COBRO_CUENTA' ? 'selected' : '' }}>Cobro a cuenta por cobrar</option>
                                </select>
                            </div>
                        </div>

                        <div id="bloque-cobro" class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700" style="display: {{ old('type') == 'COBRO_CUENTA' ? 'block' : 'none' }}">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cuenta por cobrar</label>
                            <select name="account_receivable_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 mb-2">
                                <option value="">Seleccionar...</option>
                                @foreach($cuentasPendientes as $ar)
                                    <option value="{{ $ar->id }}" {{ old('account_receivable_id') == $ar->id ? 'selected' : '' }}>
                                        Factura #{{ $ar->invoice->id }} - {{ $ar->customer?->name ?? 'Sin cliente' }} (Saldo: {{ number_format($ar->balance, 2) }})
                                    </option>
                                @endforeach
                            </select>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto a cobrar</label>
                            <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Opcional">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destino del dinero (bolsillo(s))</label>
                            <div id="parts-container">
                                <div class="flex gap-2 mb-2">
                                    <select name="parts[0][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                        <option value="">Seleccionar bolsillo</option>
                                        @foreach($bolsillos as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="parts[0][amount]" step="0.01" min="0" placeholder="Monto" class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tipoSelect = document.getElementById('tipo-ingreso');
            const bloqueCobro = document.getElementById('bloque-cobro');
            tipoSelect.addEventListener('change', function() {
                bloqueCobro.style.display = this.value === 'COBRO_CUENTA' ? 'block' : 'none';
            });

            let partIndex = 1;
            const bolsillos = @json($bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name]));
            document.getElementById('add-part').addEventListener('click', function() {
                const div = document.createElement('div');
                div.className = 'flex gap-2 mb-2';
                div.innerHTML = `
                    <select name="parts[${partIndex}][bolsillo_id]" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                        <option value="">Seleccionar bolsillo</option>
                        ${bolsillos.map(b => `<option value="${b.id}">${b.name}</option>`).join('')}
                    </select>
                    <input type="number" name="parts[${partIndex}][amount]" step="0.01" min="0" placeholder="Monto" class="w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                `;
                document.getElementById('parts-container').appendChild(div);
                partIndex++;
            });
        });
    </script>
</x-app-layout>
