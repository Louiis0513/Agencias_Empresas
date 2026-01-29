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

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('stores.purchases.update', [$store, $purchase]) }}" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                        <select name="proveedor_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Sin proveedor</option>
                            @foreach($proveedores as $prov)
                                <option value="{{ $prov->id }}" {{ $purchase->proveedor_id == $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de Pago</label>
                        <select name="payment_status" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="PAGADO" {{ $purchase->payment_status == 'PAGADO' ? 'selected' : '' }}>Contado (Pagado)</option>
                            <option value="PENDIENTE" {{ $purchase->payment_status == 'PENDIENTE' ? 'selected' : '' }}>A Crédito (Pendiente)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Factura Externa</label>
                        <input type="text" name="invoice_number" value="{{ $purchase->invoice_number }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Factura Externa</label>
                        <input type="date" name="invoice_date" value="{{ $purchase->invoice_date?->format('Y-m-d') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
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
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto / Descripción</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cantidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Costo Unit.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Subtotal</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400"></th>
                                </tr>
                            </thead>
                            <tbody id="details-body" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($purchase->details as $i => $d)
                                    <tr class="detail-row">
                                        <td class="px-3 py-2">
                                            <select name="details[{{ $i }}][product_id]" class="product-select w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                                <option value="">-- Bien/Activo (escribir abajo) --</option>
                                                @foreach($productos as $p)
                                                    <option value="{{ $p->id }}" data-name="{{ $p->name }}" {{ $d->product_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="details[{{ $i }}][description]" value="{{ $d->description }}" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                                        </td>
                                        <td class="px-3 py-2">
                                            <select name="details[{{ $i }}][item_type]" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                                <option value="INVENTARIO" {{ $d->item_type == 'INVENTARIO' ? 'selected' : '' }}>Inventario</option>
                                                <option value="ACTIVO_FIJO" {{ $d->item_type == 'ACTIVO_FIJO' ? 'selected' : '' }}>Activo Fijo</option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" name="details[{{ $i }}][quantity]" value="{{ $d->quantity }}" min="1" class="detail-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" name="details[{{ $i }}][unit_cost]" value="{{ $d->unit_cost }}" min="0" step="0.01" class="detail-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="detail-subtotal text-sm font-medium">{{ number_format($d->subtotal, 2) }}</span>
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

                <div class="flex justify-end gap-3">
                    <a href="{{ route('stores.purchases.show', [$store, $purchase]) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Actualizar Compra</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let rowIndex = {{ $purchase->details->count() }};
            const tbody = document.getElementById('details-body');
            const productOptions = @json($productos->map(fn($p) => ['id' => $p->id, 'name' => $p->name]));

            function addRow() {
                const tr = document.createElement('tr');
                tr.className = 'detail-row';
                tr.innerHTML = `
                    <td class="px-3 py-2">
                        <select name="details[${rowIndex}][product_id]" class="product-select w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                            <option value="">-- Bien/Activo (escribir abajo) --</option>
                            ${productOptions.map(p => `<option value="${p.id}" data-name="${p.name}">${p.name}</option>`).join('')}
                        </select>
                        <input type="text" name="details[${rowIndex}][description]" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Descripción" required>
                    </td>
                    <td class="px-3 py-2">
                        <select name="details[${rowIndex}][item_type]" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                            <option value="INVENTARIO">Inventario</option>
                            <option value="ACTIVO_FIJO">Activo Fijo</option>
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="details[${rowIndex}][quantity]" value="1" min="1" class="detail-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="details[${rowIndex}][unit_cost]" value="0" min="0" step="0.01" class="detail-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" required>
                    </td>
                    <td class="px-3 py-2">
                        <span class="detail-subtotal text-sm font-medium">0.00</span>
                    </td>
                    <td class="px-3 py-2">
                        <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">Quitar</button>
                    </td>
                `;
                tbody.appendChild(tr);
                rowIndex++;
                bindRowEvents(tr);
            }

            function updateSubtotal(row) {
                const qty = parseFloat(row.querySelector('.detail-qty').value) || 0;
                const cost = parseFloat(row.querySelector('.detail-cost').value) || 0;
                row.querySelector('.detail-subtotal').textContent = (qty * cost).toFixed(2);
            }

            function bindRowEvents(row) {
                row.querySelector('.detail-qty, .detail-cost').addEventListener('input', () => updateSubtotal(row));
                row.querySelector('.product-select').addEventListener('change', function() {
                    const opt = this.options[this.selectedIndex];
                    const descInput = row.querySelector('input[name*="[description]"]');
                    if (opt.value && opt.dataset.name) descInput.value = opt.dataset.name;
                });
                row.querySelector('.remove-row').addEventListener('click', function() {
                    if (tbody.querySelectorAll('.detail-row').length > 1) row.remove();
                });
                updateSubtotal(row);
            }

            document.getElementById('add-row').addEventListener('click', addRow);
            tbody.querySelectorAll('.detail-row').forEach(bindRowEvents);
        });
    </script>
</x-app-layout>
