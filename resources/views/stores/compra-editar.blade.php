<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editar Compra #{{ $purchase->id }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.purchases.show', [$store, $purchase]) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← Volver a Compra
            </a>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id])
    @livewire('create-product-modal', ['storeId' => $store->id, 'fromPurchase' => true])
    @livewire('create-activo-modal', ['storeId' => $store->id, 'fromPurchase' => true])

    <div class="py-12" x-data="compraItemSelection({ paymentStatus: '{{ old('payment_status', $purchase->payment_status) }}' })">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @php
                $detailError = $errors->first('details');
                if (!$detailError) {
                    foreach ($errors->getMessages() as $key => $messages) {
                        if (str_starts_with((string) $key, 'details')) {
                            $detailError = $messages[0] ?? null;
                            break;
                        }
                    }
                }
            @endphp
            @if($detailError)
                <div id="compra-error-message" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline font-medium">{{ $detailError }}</span>
                </div>
            @endif

            @if($errors->any() && !$detailError)
                <div id="compra-error-message" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <p class="font-medium mb-1">Por favor corrige los siguientes errores:</p>
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="compra-validation-error hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">Debes seleccionar al menos un producto o bien en el detalle de la compra. Haz clic en "Seleccionar" en cada línea para elegir un producto de inventario o un activo fijo.</span>
                <button type="button" class="absolute top-2 right-2 text-red-600 hover:text-red-800" onclick="this.parentElement.classList.add('hidden')" aria-label="Cerrar">×</button>
            </div>

            <form method="POST" action="{{ route('stores.purchases.update', [$store, $purchase]) }}" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6"
                  x-on:item-selected.window="onItemSelected($event.detail)">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                        <select name="proveedor_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Sin proveedor</option>
                            @foreach($proveedores as $prov)
                                <option value="{{ $prov->id }}" {{ old('proveedor_id', $purchase->proveedor_id) == $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de Pago</label>
                        <select name="payment_status" x-model="paymentStatus" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="PAGADO" {{ old('payment_status', $purchase->payment_status) == 'PAGADO' ? 'selected' : '' }}>Contado (Pagado)</option>
                            <option value="PENDIENTE" {{ old('payment_status', $purchase->payment_status) == 'PENDIENTE' ? 'selected' : '' }}>A Crédito (Pendiente)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Factura Externa</label>
                        <input type="text" name="invoice_number" value="{{ old('invoice_number', $purchase->invoice_number) }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Factura Externa</label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $purchase->invoice_date?->format('Y-m-d')) }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                    </div>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Detalle de la Compra</h3>
                        <button type="button" id="add-row" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar línea</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Bien / Producto</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cantidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Costo Unit.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Subtotal</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400"></th>
                                </tr>
                            </thead>
                            <tbody id="details-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @php
                                    $detailsToShow = old('details');
                                    if ($detailsToShow === null) {
                                        $detailsToShow = $purchase->details->map(fn($d) => [
                                            'item_type' => $d->item_type,
                                            'product_id' => $d->product_id,
                                            'activo_id' => $d->activo_id,
                                            'description' => $d->description,
                                            'quantity' => $d->quantity,
                                            'unit_cost' => $d->unit_cost,
                                            'subtotal' => $d->subtotal,
                                            'is_serializado' => $d->activo && $d->activo->control_type === 'SERIALIZADO',
                                        ])->values()->all();
                                    } else {
                                        $detailsToShow = array_values($detailsToShow);
                                    }
                                @endphp
                                @foreach($detailsToShow as $i => $d)
                                    @php
                                        $d = is_array($d) ? $d : (array) $d;
                                        $hasItem = !empty(trim($d['description'] ?? '')) || !empty($d['product_id'] ?? '') || !empty($d['activo_id'] ?? '');
                                        $qty = (int) ($d['quantity'] ?? 1);
                                        $cost = (float) ($d['unit_cost'] ?? 0);
                                        $subtotal = $d['subtotal'] ?? ($qty * $cost);
                                        $isSerializado = $d['is_serializado'] ?? false;
                                    @endphp
                                    <tr class="detail-row" data-row-id="{{ $i }}" @if($isSerializado) data-activo-serializado="1" @endif>
                                        <td class="px-3 py-2">
                                            <select name="details[{{ $i }}][item_type]" class="item-type-select w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                                <option value="INVENTARIO" {{ ($d['item_type'] ?? 'INVENTARIO') == 'INVENTARIO' ? 'selected' : '' }}>Inventario</option>
                                                <option value="ACTIVO_FIJO" {{ ($d['item_type'] ?? '') == 'ACTIVO_FIJO' ? 'selected' : '' }}>Activo Fijo</option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="item-select-wrapper">
                                                <input type="hidden" name="details[{{ $i }}][product_id]" class="product-id-input" value="{{ $d['product_id'] ?? '' }}">
                                                <input type="hidden" name="details[{{ $i }}][activo_id]" class="activo-id-input" value="{{ $d['activo_id'] ?? '' }}">
                                                <input type="hidden" name="details[{{ $i }}][description]" class="item-description-input" value="{{ $d['description'] ?? '' }}">
                                                <span class="item-selected-name text-sm text-gray-700 dark:text-gray-300 block mb-1 min-h-[1.25rem]">{{ $d['description'] ?? '' }}</span>
                                                <button type="button" class="btn-select-item {{ $hasItem ? 'hidden' : '' }} px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                                    Seleccionar
                                                </button>
                                                <button type="button" class="btn-change-item {{ $hasItem ? '' : 'hidden' }} px-2 py-1 text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    Cambiar
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" name="details[{{ $i }}][quantity]" value="{{ $qty }}" {{ $isSerializado ? 'min="0" max="1"' : 'min="1"' }} class="detail-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" name="details[{{ $i }}][unit_cost]" value="{{ $cost }}" min="0" step="0.01" class="detail-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="detail-subtotal text-sm font-medium">{{ number_format($subtotal, 2) }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">Quitar</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-700" x-show="paymentStatus === 'PENDIENTE'" x-transition>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fecha de vencimiento de la factura</label>
                    <input type="date" name="due_date" value="{{ old('due_date', $purchase->due_date?->format('Y-m-d')) }}" :required="paymentStatus === 'PENDIENTE'" :disabled="paymentStatus !== 'PENDIENTE'" class="w-full max-w-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Cuando vence la cuenta por pagar">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Indica cuándo vence el pago según la factura real o acuerdos con el proveedor.</p>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('stores.purchases.show', [$store, $purchase]) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Actualizar Compra</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function compraItemSelection(init = {}) {
            return {
                paymentStatus: init.paymentStatus || 'PAGADO',
                onItemSelected(detail) {
                    const row = document.querySelector(`.detail-row[data-row-id="${detail.rowId}"]`);
                    if (!row) return;
                    const productInput = row.querySelector('.product-id-input');
                    const activoInput = row.querySelector('.activo-id-input');
                    const descInput = row.querySelector('.item-description-input');
                    const nameSpan = row.querySelector('.item-selected-name');
                    const qtyInput = row.querySelector('.detail-qty');
                    if (detail.type === 'INVENTARIO') {
                        productInput.value = detail.id;
                        activoInput.value = '';
                        row.removeAttribute('data-activo-serializado');
                        if (qtyInput) { qtyInput.min = 1; qtyInput.max = ''; qtyInput.removeAttribute('data-activo-serializado'); qtyInput.value = 1; }
                    } else {
                        activoInput.value = detail.id;
                        productInput.value = '';
                        if (detail.controlType === 'SERIALIZADO') {
                            row.setAttribute('data-activo-serializado', '1');
                            if (qtyInput) { qtyInput.min = 0; qtyInput.max = 1; qtyInput.setAttribute('data-activo-serializado', '1'); qtyInput.value = 1; }
                        } else {
                            row.removeAttribute('data-activo-serializado');
                            if (qtyInput) { qtyInput.min = 1; qtyInput.max = ''; qtyInput.removeAttribute('data-activo-serializado'); qtyInput.value = qtyInput.value || 1; }
                        }
                    }
                    descInput.value = detail.name;
                    if (nameSpan) nameSpan.textContent = detail.name;
                    const btnSelect = row.querySelector('.btn-select-item');
                    const btnChange = row.querySelector('.btn-change-item');
                    if (btnSelect) btnSelect.classList.add('hidden');
                    if (btnChange) btnChange.classList.remove('hidden');
                    if (qtyInput) qtyInput.dispatchEvent(new Event('input'));
                    const errDiv = document.querySelector('.compra-validation-error');
                    if (errDiv) errDiv.classList.add('hidden');
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.getElementById('details-body');
            let rowIndex = tbody ? tbody.querySelectorAll('.detail-row').length : 0;

            function createRowHtml(idx) {
                return `
                    <td class="px-3 py-2">
                        <select name="details[${idx}][item_type]" class="item-type-select w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                            <option value="INVENTARIO">Inventario</option>
                            <option value="ACTIVO_FIJO">Activo Fijo</option>
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <div class="item-select-wrapper">
                            <input type="hidden" name="details[${idx}][product_id]" class="product-id-input" value="">
                            <input type="hidden" name="details[${idx}][activo_id]" class="activo-id-input" value="">
                            <input type="hidden" name="details[${idx}][description]" class="item-description-input" value="">
                            <span class="item-selected-name text-sm text-gray-700 dark:text-gray-300 block mb-1 min-h-[1.25rem]"></span>
                            <button type="button" class="btn-select-item px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Seleccionar
                            </button>
                            <button type="button" class="btn-change-item hidden px-2 py-1 text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                Cambiar
                            </button>
                        </div>
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="details[${idx}][quantity]" value="1" min="1" class="detail-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="details[${idx}][unit_cost]" value="0" min="0" step="0.01" class="detail-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                    </td>
                    <td class="px-3 py-2">
                        <span class="detail-subtotal text-sm font-medium">0.00</span>
                    </td>
                    <td class="px-3 py-2">
                        <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">Quitar</button>
                    </td>
                `;
            }

            function updateSubtotal(row) {
                const qty = parseFloat(row.querySelector('.detail-qty').value) || 0;
                const cost = parseFloat(row.querySelector('.detail-cost').value) || 0;
                row.querySelector('.detail-subtotal').textContent = (qty * cost).toFixed(2);
            }

            function bindRowEvents(row) {
                row.querySelectorAll('.detail-qty, .detail-cost').forEach(input => {
                    input.addEventListener('input', () => updateSubtotal(row));
                });
                const btnSelect = row.querySelector('.btn-select-item');
                const btnChange = row.querySelector('.btn-change-item');
                if (btnSelect) {
                    btnSelect.addEventListener('click', function() {
                        const rowId = row.getAttribute('data-row-id');
                        const itemType = row.querySelector('.item-type-select').value;
                        Livewire.dispatch('open-select-item-for-row', { rowId: rowId, itemType: itemType });
                    });
                }
                if (btnChange) {
                    btnChange.addEventListener('click', function() {
                        row.querySelector('.product-id-input').value = '';
                        row.querySelector('.activo-id-input').value = '';
                        row.querySelector('.item-description-input').value = '';
                        row.querySelector('.item-selected-name').textContent = '';
                        row.removeAttribute('data-activo-serializado');
                        const qtyInput = row.querySelector('.detail-qty');
                        if (qtyInput) { qtyInput.min = 1; qtyInput.max = ''; qtyInput.removeAttribute('data-activo-serializado'); qtyInput.value = 1; qtyInput.dispatchEvent(new Event('input')); }
                        btnSelect.classList.remove('hidden');
                        btnChange.classList.add('hidden');
                    });
                }
                row.querySelector('.remove-row').addEventListener('click', function() {
                    if (tbody.querySelectorAll('.detail-row').length > 1) {
                        row.remove();
                        renumberRows();
                    }
                });
                updateSubtotal(row);
            }

            function renumberRows() {
                const rows = tbody.querySelectorAll('.detail-row');
                rows.forEach((row, i) => {
                    row.setAttribute('data-row-id', String(i));
                    row.querySelector('.item-type-select').name = `details[${i}][item_type]`;
                    row.querySelector('.product-id-input').name = `details[${i}][product_id]`;
                    row.querySelector('.activo-id-input').name = `details[${i}][activo_id]`;
                    row.querySelector('.item-description-input').name = `details[${i}][description]`;
                    row.querySelector('.detail-qty').name = `details[${i}][quantity]`;
                    row.querySelector('.detail-cost').name = `details[${i}][unit_cost]`;
                });
            }

            function addRow() {
                const tr = document.createElement('tr');
                tr.className = 'detail-row';
                tr.setAttribute('data-row-id', '0');
                tr.innerHTML = createRowHtml(0);
                tbody.insertBefore(tr, tbody.firstChild);
                const rows = tbody.querySelectorAll('.detail-row');
                rows.forEach((row, i) => {
                    row.setAttribute('data-row-id', String(i));
                    row.querySelector('.item-type-select').name = `details[${i}][item_type]`;
                    row.querySelector('.product-id-input').name = `details[${i}][product_id]`;
                    row.querySelector('.activo-id-input').name = `details[${i}][activo_id]`;
                    row.querySelector('.item-description-input').name = `details[${i}][description]`;
                    row.querySelector('.detail-qty').name = `details[${i}][quantity]`;
                    row.querySelector('.detail-cost').name = `details[${i}][unit_cost]`;
                });
                rowIndex++;
                bindRowEvents(tr);
            }

            document.getElementById('add-row').addEventListener('click', addRow);
            tbody.querySelectorAll('.detail-row').forEach(bindRowEvents);

            const errorEl = document.getElementById('compra-error-message');
            if (errorEl) {
                errorEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            const form = document.querySelector('form[action*="purchases"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const rows = tbody.querySelectorAll('.detail-row');
                    let hasSelectedItem = false;
                    rows.forEach(row => {
                        const descInput = row.querySelector('.item-description-input');
                        if (descInput && descInput.value && descInput.value.trim() !== '') {
                            hasSelectedItem = true;
                        }
                    });
                    if (!hasSelectedItem) {
                        e.preventDefault();
                        const errorDiv = document.querySelector('.compra-validation-error');
                        if (errorDiv) {
                            errorDiv.classList.remove('hidden');
                            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        } else {
                            alert('Debes seleccionar al menos un producto o bien en el detalle de la compra.');
                        }
                        return false;
                    }
                });
            }
        });
    </script>
</x-app-layout>
