<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Crear Activo - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.activos', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Activos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('stores.activos.store', $store) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de control *</label>
                            <select name="control_type" id="control_type" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                                <option value="LOTE" {{ old('control_type', 'LOTE') == 'LOTE' ? 'selected' : '' }}>Lote / Granel (sillas, pesas, vajilla)</option>
                                <option value="SERIALIZADO" {{ old('control_type') == 'SERIALIZADO' ? 'selected' : '' }}>Serializado (computador, caminadora, vehículo)</option>
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Lote: 1 registro, cantidad N. Serializado: 1 registro por unidad con serial único.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: Sillas plásticas, Computador Dell">
                            @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Código</label>
                            <input type="text" name="code" value="{{ old('code') }}"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: ACT-001">
                            @error('code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Marca</label>
                                <input type="text" name="brand" value="{{ old('brand') }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: Dell, Nike">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modelo</label>
                                <input type="text" name="model" value="{{ old('model') }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: XPS 15, ProForm 500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                            <textarea name="description" rows="2" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ old('description') }}</textarea>
                            @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div id="quantity-field">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cantidad *</label>
                            <input type="number" name="quantity" id="quantity-input" value="{{ old('quantity', 0) }}" min="0" step="1"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="quantity-help">Si es > 0, se registra como «Alta inicial». También se suma al aprobar compras de tipo Activo Fijo.</p>
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1 hidden" id="quantity-serial-help">0 = catálogo (añadirás unidades desde compras). 1 = una unidad única ahora (indica el serial abajo).</p>
                            @error('quantity')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div id="serial-number-field" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de serie <span id="serial-required-label" class="text-red-500">*</span></label>
                            <input type="text" name="serial_number" id="serial-number-input" value="{{ old('serial_number') }}"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: SN123456">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Obligatorio cuando creas 1 unidad serializada ahora.</p>
                            @error('serial_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Costo unitario</label>
                            <input type="number" name="unit_cost" value="{{ old('unit_cost', 0) }}" min="0" step="0.01"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="0.00">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Se actualiza automáticamente al aprobar compras.</p>
                            @error('unit_cost')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ubicación</label>
                            <input type="text" name="location" value="{{ old('location') }}"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: Escritorio, Almacén, Recepción">
                            @error('location')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Antigüedad (fecha de compra)</label>
                                <input type="date" name="purchase_date" value="{{ old('purchase_date') }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Cuándo se compró realmente.</p>
                                @error('purchase_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Custodia (quién lo tiene)</label>
                                <select name="assigned_to_user_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">— Sin asignar</option>
                                    @foreach($workers ?? [] as $w)
                                        <option value="{{ $w->id }}" {{ old('assigned_to_user_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Usuario responsable del activo.</p>
                                @error('assigned_to_user_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Condición</label>
                                <select name="condition" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Activo::condicionesDisponibles() as $k => $v)
                                        <option value="{{ $k }}" {{ old('condition') == $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado</label>
                                <select name="status" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    @foreach(\App\Models\Activo::estadosDisponibles() as $k => $v)
                                        <option value="{{ $k }}" {{ old('status', 'ACTIVO') == $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Activo</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Crear Activo</button>
                        <a href="{{ route('stores.activos', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSerialFields() {
            const isSerial = document.getElementById('control_type').value === 'SERIALIZADO';
            const qtyField = document.getElementById('quantity-field');
            const qtyInput = document.getElementById('quantity-input');
            const help = document.getElementById('quantity-help');
            const serialHelp = document.getElementById('quantity-serial-help');
            const serialField = document.getElementById('serial-number-field');
            const serialInput = document.getElementById('serial-number-input');
            const serialRequiredLabel = document.getElementById('serial-required-label');
            if (isSerial) {
                qtyField.classList.remove('hidden');
                qtyInput.min = 0;
                qtyInput.max = 1;
                qtyInput.placeholder = '0 o 1';
                help.classList.add('hidden');
                serialHelp.classList.remove('hidden');
                serialField.classList.remove('hidden');
                if (parseInt(qtyInput.value) === 1) {
                    serialInput.required = true;
                    if (serialRequiredLabel) serialRequiredLabel.classList.remove('hidden');
                } else {
                    serialInput.required = false;
                    serialInput.value = '';
                    if (serialRequiredLabel) serialRequiredLabel.classList.add('hidden');
                }
            } else {
                qtyField.classList.remove('hidden');
                qtyInput.removeAttribute('max');
                qtyInput.placeholder = '';
                help.classList.remove('hidden');
                serialHelp.classList.add('hidden');
                serialField.classList.add('hidden');
                serialInput.required = false;
                if (serialRequiredLabel) serialRequiredLabel.classList.add('hidden');
            }
        }
        document.getElementById('control_type')?.addEventListener('change', toggleSerialFields);
        document.getElementById('quantity-input')?.addEventListener('input', toggleSerialFields);
        document.getElementById('quantity-input')?.addEventListener('change', toggleSerialFields);
        toggleSerialFields();
    </script>
</x-app-layout>
