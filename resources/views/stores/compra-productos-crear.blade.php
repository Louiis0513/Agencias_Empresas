@php
    $purchase = $purchase ?? null;
    $detailsForEdit = $detailsForEdit ?? [];
    $editing = $purchase && $purchase instanceof \App\Models\Purchase;
    $formAction = $editing ? route('stores.purchases.update', [$store, $purchase]) : route('stores.product-purchases.store', $store);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $editing ? 'Editar compra' : 'Nueva Compra de Productos' }} - {{ $store->name }}
            </h2>
            <a href="{{ $editing ? route('stores.purchases.show', [$store, $purchase]) : route('stores.product-purchases', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                ← {{ $editing ? 'Volver a compra' : 'Volver a Compra de productos' }}
            </a>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO'])
    @livewire('select-batch-variant-modal', ['storeId' => $store->id])
    @livewire('create-product-modal', ['storeId' => $store->id, 'fromPurchase' => true])

    <div class="py-12" x-data="compraProductosSelection()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(!$editing)
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-3">
                La compra se guardará como borrador. Podrás editarla o aprobarla después desde el listado de compras de productos.
            </p>
            @endif

            <form method="POST" action="{{ $formAction }}" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6" id="form-compra-productos"
                  data-atributos-url="{{ route('stores.productos.atributos-categoria', [$store, 0]) }}"
                  x-on:item-selected.window="onItemSelected($event.detail)"
                  x-on:batch-variant-selected.window="onBatchVariantSelected($event.detail)">
                @csrf
                @if($editing)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                        <select name="proveedor_id" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Sin proveedor</option>
                            @foreach($proveedores as $prov)
                                <option value="{{ $prov->id }}" {{ old('proveedor_id', $editing ? $purchase->proveedor_id : null) == $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de Pago</label>
                        <select name="payment_status" x-model="paymentStatus" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="PAGADO" {{ old('payment_status', $editing ? $purchase->payment_status : 'PAGADO') == 'PAGADO' ? 'selected' : '' }}>Contado (Pagado)</option>
                            <option value="PENDIENTE" {{ old('payment_status', $editing ? $purchase->payment_status : null) == 'PENDIENTE' ? 'selected' : '' }}>A Crédito (Pendiente)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Factura Externa</label>
                        <input type="text" name="invoice_number" value="{{ old('invoice_number', $editing ? $purchase->invoice_number : '') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Ej: F-001-0001234">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Factura Externa</label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $editing && $purchase->invoice_date ? $purchase->invoice_date->format('Y-m-d') : '') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    </div>
                    <div x-show="paymentStatus === 'PENDIENTE'" x-transition>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de vencimiento de la factura</label>
                        <input type="date" name="due_date" value="{{ old('due_date', $editing && $purchase->due_date ? $purchase->due_date->format('Y-m-d') : '') }}" x-bind:required="paymentStatus === 'PENDIENTE'" x-bind:disabled="paymentStatus !== 'PENDIENTE'" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" placeholder="Cuando vence la cuenta por pagar">
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Solo para compras a crédito. Cuándo vence el pago.</p>
                    </div>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Detalle (productos de inventario)</h3>
                        <button type="button" id="add-row-productos" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar línea</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Producto</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Cantidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Costo Unit.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Caducidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Subtotal</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400"></th>
                                </tr>
                            </thead>
                            <tbody id="details-body-productos" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @php
                                    $defaultDetail = ['item_type' => 'INVENTARIO', 'product_id' => '', 'description' => '', 'quantity' => 1, 'unit_cost' => 0];
                                    $oldDetails = old('details');
                                    if ($oldDetails !== null) {
                                        $oldDetails = array_values($oldDetails);
                                    } elseif ($editing && !empty($detailsForEdit)) {
                                        $oldDetails = $detailsForEdit;
                                    } else {
                                        $oldDetails = [$defaultDetail];
                                    }
                                    if (empty($oldDetails)) {
                                        $oldDetails = [$defaultDetail];
                                    }
                                @endphp
                                @foreach($oldDetails as $i => $d)
                                    @php
                                        $d = is_array($d) ? $d : (array) $d;
                                        $hasItem = !empty(trim($d['description'] ?? '')) || !empty($d['product_id'] ?? '');
                                        $bi0 = $d['batch_items'][0] ?? [];
                                        $isBatchRow = !empty($d['batch_items']) && (!empty($bi0['batch_item_id']) || !empty($bi0['features']));
                                        $isSerialRow = !empty($d['serial_items']) && is_array($d['serial_items']);
                                        $qty = $isBatchRow ? (int) ($bi0['quantity'] ?? 1) : (int) ($d['quantity'] ?? 1);
                                        if ($isSerialRow) {
                                            $qty = count($d['serial_items']);
                                        }
                                        $cost = $isBatchRow ? (float) ($bi0['unit_cost'] ?? 0) : (float) ($d['unit_cost'] ?? 0);
                                        $subtotal = $qty * $cost;
                                        $productType = $d['product_type'] ?? 'simple';
                                        $batchItemId = $bi0['batch_item_id'] ?? null;
                                        $batchExpiration = $bi0['expiration_date'] ?? '';
                                    @endphp
                                    <tr class="detail-row" data-row-id="{{ $i }}" data-product-type="{{ $productType }}" @if(!empty($d['product_id'])) data-product-id="{{ $d['product_id'] }}" @endif @if($isBatchRow) data-is-batch="1" @if($batchItemId) data-batch-item-id="{{ $batchItemId }}" @endif @endif>
                                        <td class="px-3 py-2">
                                            <input type="hidden" name="details[{{ $i }}][item_type]" value="INVENTARIO">
                                            <div class="item-select-wrapper">
                                                <input type="hidden" name="details[{{ $i }}][product_id]" class="product-id-input" value="{{ $d['product_id'] ?? '' }}">
                                                <input type="hidden" name="details[{{ $i }}][description]" class="item-description-input" value="{{ $d['description'] ?? '' }}">
                                                @if($isBatchRow && $batchItemId)
                                                    <input type="hidden" name="details[{{ $i }}][batch_items][0][batch_item_id]" value="{{ $batchItemId }}">
                                                @elseif($isBatchRow && !empty($bi0['features']))
                                                    @foreach($bi0['features'] ?? [] as $attrId => $val)
                                                        <input type="hidden" name="details[{{ $i }}][batch_items][0][features][{{ $attrId }}]" value="{{ $val }}">
                                                    @endforeach
                                                @endif
                                                <span class="item-selected-name text-sm text-gray-700 dark:text-gray-300 block mb-1 min-h-[1.25rem]">{{ $d['description'] ?? '' }}</span>
                                                <button type="button" class="btn-select-item {{ $hasItem ? 'hidden' : '' }} px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                                    Seleccionar
                                                </button>
                                                <button type="button" class="btn-change-item {{ $hasItem ? '' : 'hidden' }} px-2 py-1 text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    Cambiar
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 detail-qty-cell">
                                            @if($isSerialRow)
                                                <input type="hidden" name="details[{{ $i }}][quantity]" value="{{ $qty }}" class="detail-qty">
                                                <span class="detail-serial-qty text-sm text-gray-700 dark:text-gray-300">{{ $qty }}</span>
                                            @elseif($isBatchRow)
                                                <input type="number" name="details[{{ $i }}][batch_items][0][quantity]" value="{{ $qty }}" min="1" class="detail-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                            @else
                                                <input type="number" name="details[{{ $i }}][quantity]" value="{{ $qty }}" min="1" class="detail-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                            @endif
                                            <span class="detail-serial-qty text-sm text-gray-600 dark:text-gray-400 hidden"></span>
                                            <span class="detail-batch-qty text-sm text-gray-700 dark:text-gray-300 hidden"></span>
                                        </td>
                                        <td class="px-3 py-2 detail-cost-cell">
                                            @if($isBatchRow)
                                                <input type="number" name="details[{{ $i }}][batch_items][0][unit_cost]" value="{{ $cost }}" min="0" step="0.01" class="detail-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                            @else
                                                <input type="number" name="details[{{ $i }}][unit_cost]" value="{{ $cost }}" min="0" step="0.01" class="detail-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                            @endif
                                            <span class="detail-serial-dash hidden">—</span>
                                            <span class="detail-batch-cost text-sm text-gray-700 dark:text-gray-300 hidden"></span>
                                        </td>
                                        <td class="px-3 py-2 detail-expiration-cell text-sm {{ $isBatchRow ? '' : 'text-gray-500 dark:text-gray-400' }}">
                                            @if($isBatchRow)
                                                <input type="date" class="detail-batch-expiration w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[{{ $i }}][batch_items][0][expiration_date]" value="{{ $batchExpiration }}" placeholder="Opcional">
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="detail-subtotal text-sm font-medium">{{ number_format($subtotal, 2) }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">Quitar</button>
                                        </td>
                                    </tr>
                                    @if($editing && !empty($d['serial_items']) && is_array($d['serial_items']))
                                        {{-- Fila de unidades serializadas: debe enviarse al guardar para no perder los seriales al editar --}}
                                        <tr class="serial-details-row bg-gray-50 dark:bg-gray-900/50" data-parent-row-id="{{ $i }}" data-product-id="{{ $d['product_id'] ?? '' }}">
                                            <td colspan="6" class="px-3 py-3 border-t border-gray-200 dark:border-gray-700">
                                                <div class="space-y-4 text-sm">
                                                    <div class="p-2 rounded bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800">
                                                        <p class="text-sm font-medium text-indigo-800 dark:text-indigo-200">Unidades serializadas (edición)</p>
                                                        <p class="mt-0.5 text-xs text-indigo-700 dark:text-indigo-300">Cada unidad con su número de serie y costo. Se envían al guardar para que la aprobación no falle.</p>
                                                    </div>
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-semibold text-gray-700 dark:text-gray-200">Unidades (serial + costo)</span>
                                                        <button type="button" class="btn-add-serial-unit text-indigo-600 hover:underline text-sm">+ Agregar unidad</button>
                                                    </div>
                                                    <div class="serial-items-container space-y-3">
                                                        @foreach($d['serial_items'] as $j => $unit)
                                                            @php
                                                                $unit = is_array($unit) ? $unit : (array) $unit;
                                                                $sn = $unit['serial_number'] ?? '';
                                                                $uc = (float) ($unit['cost'] ?? 0);
                                                                $feats = $unit['features'] ?? [];
                                                            @endphp
                                                            <div class="serial-item border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700" data-serial-index="{{ $j }}">
                                                                <div class="flex justify-between items-center serial-item-header">
                                                                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Unidad #{{ $j + 1 }}</span>
                                                                    <button type="button" class="btn-remove-serial text-red-600 hover:underline text-sm">Eliminar</button>
                                                                </div>
                                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                    <div>
                                                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Número de serie (IMEI, etc.)</label>
                                                                        <input type="text" class="serial-number w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ej: IMEI-123456789" name="details[{{ $i }}][serial_items][{{ $j }}][serial_number]" value="{{ old('details.'.$i.'.serial_items.'.$j.'.serial_number', $sn) }}">
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Costo de esta unidad (€)</label>
                                                                        <input type="number" step="0.01" min="0" class="serial-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="0.00" name="details[{{ $i }}][serial_items][{{ $j }}][cost]" value="{{ old('details.'.$i.'.serial_items.'.$j.'.cost', $uc) }}">
                                                                    </div>
                                                                </div>
                                                                @if(!empty($feats))
                                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                        @foreach($feats as $attrId => $attrVal)
                                                                            <div>
                                                                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Atributo {{ $attrId }}</label>
                                                                                <input type="text" class="serial-attr-feature w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" data-attr-id="{{ $attrId }}" name="details[{{ $i }}][serial_items][{{ $j }}][features][{{ $attrId }}]" value="{{ is_scalar($attrVal) ? $attrVal : '' }}">
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Productos de inventario (para reventa). Busca por nombre o SKU en «Seleccionar» o crea un producto nuevo.
                    </p>
                </div>

                <div class="compra-productos-validation-error hidden mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">Debes seleccionar al menos un producto en el detalle. Haz clic en «Seleccionar» en cada línea.</span>
                    <button type="button" class="absolute top-2 right-2 text-red-600 hover:text-red-800" onclick="this.parentElement.classList.add('hidden')" aria-label="Cerrar">×</button>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ $editing ? route('stores.purchases.show', [$store, $purchase]) : route('stores.product-purchases', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        Cancelar
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        {{ $editing ? 'Guardar cambios' : 'Guardar como borrador' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function compraProductosUpdateSubtotal(row) {
            var qty = parseFloat(row.querySelector('.detail-qty').value) || 0;
            var cost = parseFloat(row.querySelector('.detail-cost').value) || 0;
            row.querySelector('.detail-subtotal').textContent = (qty * cost).toFixed(2);
        }

        function compraProductosUpdateSerialQtyAndSubtotal(detailRow) {
            var serialRow = detailRow.nextElementSibling;
            if (!serialRow || !serialRow.classList.contains('serial-details-row')) return;
            var container = serialRow.querySelector('.serial-items-container');
            if (!container) return;
            var items = container.querySelectorAll('.serial-item');
            var total = 0;
            items.forEach(function(item) {
                var cost = parseFloat(item.querySelector('.serial-cost').value) || 0;
                total += cost;
            });
            var qtySpan = detailRow.querySelector('.detail-serial-qty');
            var qtyInput = detailRow.querySelector('.detail-qty');
            if (qtySpan) qtySpan.textContent = items.length + ' unidad(es)';
            if (qtyInput) qtyInput.value = items.length;
            detailRow.querySelector('.detail-subtotal').textContent = total.toFixed(2);
        }

        function compraProductosUpdateBatchTotals(detailRow) {
            var batchRow = detailRow.nextElementSibling;
            if (!batchRow || !batchRow.classList.contains('batch-details-row')) return;
            var totalQty = 0;
            var totalCost = 0;
            var container = batchRow.querySelector('.batch-items-container');
            if (container) {
                var items = container.querySelectorAll('.batch-item');
                items.forEach(function(item) {
                    var qty = parseInt(item.querySelector('.batch-item-qty').value, 10) || 0;
                    var cost = parseFloat(item.querySelector('.batch-item-cost').value) || 0;
                    totalQty += qty;
                    totalCost += qty * cost;
                });
            } else {
                var qtyInp = batchRow.querySelector('.batch-item-qty');
                var costInp = batchRow.querySelector('.batch-item-cost');
                if (qtyInp && costInp) {
                    totalQty = parseInt(qtyInp.value, 10) || 0;
                    var unitCost = parseFloat(costInp.value) || 0;
                    totalCost = totalQty * unitCost;
                }
            }
            var qtyInput = detailRow.querySelector('.detail-qty');
            var costInput = detailRow.querySelector('.detail-cost');
            if (qtyInput) qtyInput.value = totalQty;
            if (costInput) costInput.value = totalQty > 0 ? (totalCost / totalQty).toFixed(2) : '0';
            var subtotalEl = detailRow.querySelector('.detail-subtotal');
            if (subtotalEl) subtotalEl.textContent = totalCost.toFixed(2);
            var batchQtySpan = detailRow.querySelector('.detail-batch-qty');
            var batchCostSpan = detailRow.querySelector('.detail-batch-cost');
            if (batchQtySpan) batchQtySpan.textContent = totalQty;
            if (batchCostSpan) batchCostSpan.textContent = totalQty > 0 ? (totalCost / totalQty).toFixed(2) : '0.00';
        }

        function compraProductosSelection() {
            // Función auxiliar para escapar HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            return {
                paymentStatus: @json(old('payment_status', $editing ? $purchase->payment_status : 'PENDIENTE')),
                onItemSelected(detail) {
                    if (detail.type !== 'INVENTARIO') return;
                    const row = document.querySelector(`#details-body-productos .detail-row[data-row-id="${detail.rowId}"]`);
                    if (!row) return;
                    
                    const productType = detail.productType || 'simple';
                    const rowId = detail.rowId;
                    const productId = detail.id;
                    const productName = detail.name;

                    // Guardar información del producto en la fila
                    row.setAttribute('data-product-id', productId);
                    row.setAttribute('data-product-type', productType);
                    
                    const productInput = row.querySelector('.product-id-input');
                    const descInput = row.querySelector('.item-description-input');
                    const nameSpan = row.querySelector('.item-selected-name');
                    if (productInput) productInput.value = productId;
                    if (descInput) descInput.value = productName;
                    if (nameSpan) nameSpan.textContent = productName;
                    
                    const btnSelect = row.querySelector('.btn-select-item');
                    const btnChange = row.querySelector('.btn-change-item');
                    if (btnSelect) btnSelect.classList.add('hidden');
                    if (btnChange) btnChange.classList.remove('hidden');

                    // Limpiar filas expandidas anteriores
                    let next = row.nextElementSibling;
                    if (next && (next.classList.contains('serial-details-row') || next.classList.contains('batch-details-row'))) {
                        next.remove();
                    }

                    // Manejar según el tipo de producto
                    if (productType === 'simple') {
                        // Productos simples: solo cantidad y costo (como está actualmente)
                        row.querySelector('.detail-qty').classList.remove('hidden');
                        row.querySelector('.detail-serial-qty').classList.add('hidden');
                        row.querySelector('.detail-cost').classList.remove('hidden');
                        row.querySelector('.detail-serial-dash').classList.add('hidden');
                        compraProductosUpdateSubtotal(row);
                    } else if (productType === 'batch') {
                        // Productos batch: abrir modal para seleccionar variante
                        Livewire.dispatch('open-select-batch-variant', {
                            productId: productId,
                            rowId: rowId,
                            productName: productName
                        });
                    } else if (productType === 'serialized') {
                        // Productos serializados: mostrar campos para atributos, serie y costo
                        row.setAttribute('data-product-type', 'serialized');
                        if (!row.nextElementSibling || !row.nextElementSibling.classList.contains('serial-details-row')) {
                            window.compraProductosCreateSerialDetailsRowUnidades(rowId, productId, row);
                        }
                        row.querySelector('.detail-qty').classList.add('hidden');
                        row.querySelector('.detail-serial-qty').classList.remove('hidden');
                        row.querySelector('.detail-cost').classList.add('hidden');
                        row.querySelector('.detail-serial-dash').classList.remove('hidden');
                        compraProductosUpdateSerialQtyAndSubtotal(row);
                    }
                    
                    const errDiv = document.querySelector('.compra-productos-validation-error');
                    if (errDiv) errDiv.classList.add('hidden');
                },
                onBatchVariantSelected(detail) {
                    const row = document.querySelector(`#details-body-productos .detail-row[data-row-id="${detail.rowId}"]`);
                    if (!row) return;
                    
                    const rowId = detail.rowId;
                    const productName = detail.productName || row.querySelector('.item-selected-name')?.textContent || '';
                    const batchItemId = detail.batchItemId;
                    const variantFeatures = detail.variantFeatures || {};
                    const displayName = detail.displayName || '';
                    
                    row.setAttribute('data-batch-item-id', String(batchItemId || ''));
                    row.setAttribute('data-is-batch', '1');
                    
                    // Producto + variante en la primera columna (ej. "Blusa — US: 8, Color: Rojo")
                    const descText = displayName ? productName + ' — ' + displayName : productName;
                    const nameSpan = row.querySelector('.item-selected-name');
                    const descInput = row.querySelector('.item-description-input');
                    if (nameSpan) nameSpan.textContent = descText;
                    if (descInput) descInput.value = descText;
                    
                    // Quitar fila expandida si existía (no usamos sección de abajo; todo en la primera fila)
                    let next = row.nextElementSibling;
                    if (next && (next.classList.contains('serial-details-row') || next.classList.contains('batch-details-row'))) {
                        next.remove();
                    }
                    
                    // Inputs de cantidad y costo en la primera fila (visibles)
                    const qtyInput = row.querySelector('.detail-qty');
                    const costInput = row.querySelector('.detail-cost');
                    if (row.querySelector('.detail-serial-qty')) row.querySelector('.detail-serial-qty').classList.add('hidden');
                    if (row.querySelector('.detail-serial-dash')) row.querySelector('.detail-serial-dash').classList.add('hidden');
                    if (row.querySelector('.detail-batch-qty')) row.querySelector('.detail-batch-qty').classList.add('hidden');
                    if (row.querySelector('.detail-batch-cost')) row.querySelector('.detail-batch-cost').classList.add('hidden');
                    if (qtyInput) { qtyInput.classList.remove('hidden'); qtyInput.name = `details[${rowId}][batch_items][0][quantity]`; qtyInput.value = '1'; }
                    if (costInput) { costInput.classList.remove('hidden'); costInput.name = `details[${rowId}][batch_items][0][unit_cost]`; costInput.value = '0'; }
                    
                    // batch_item_id (si existe) o features: el backend necesita uno de los dos para identificar la variante al aprobar
                    const wrapper = row.querySelector('.item-select-wrapper');
                    if (wrapper) {
                        row.querySelectorAll('input[name*="[batch_items][0][batch_item_id]"]').forEach(function(inp) { inp.remove(); });
                        row.querySelectorAll('input[name*="[batch_items][0][features]"]').forEach(function(inp) { inp.remove(); });
                        if (batchItemId) {
                            const hid = document.createElement('input');
                            hid.type = 'hidden';
                            hid.name = `details[${rowId}][batch_items][0][batch_item_id]`;
                            hid.value = batchItemId;
                            wrapper.appendChild(hid);
                        } else if (variantFeatures && typeof variantFeatures === 'object') {
                            Object.keys(variantFeatures).forEach(function(attrId) {
                                const val = variantFeatures[attrId];
                                if (val !== '' && val != null) {
                                    const inp = document.createElement('input');
                                    inp.type = 'hidden';
                                    inp.name = `details[${rowId}][batch_items][0][features][${attrId}]`;
                                    inp.value = val;
                                    wrapper.appendChild(inp);
                                }
                            });
                        }
                    }
                    
                    // Fecha de caducidad opcional (solo para lote)
                    const expCell = row.querySelector('.detail-expiration-cell');
                    if (expCell) {
                        expCell.textContent = '';
                        expCell.classList.remove('text-gray-500', 'dark:text-gray-400');
                        const expInput = document.createElement('input');
                        expInput.type = 'date';
                        expInput.className = 'detail-batch-expiration w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm';
                        expInput.name = `details[${rowId}][batch_items][0][expiration_date]`;
                        expInput.placeholder = 'Opcional';
                        expCell.appendChild(expInput);
                    }
                    
                    compraProductosUpdateSubtotal(row);
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.getElementById('details-body-productos');

            function createRowHtml(idx) {
                return `
                    <td class="px-3 py-2">
                        <input type="hidden" name="details[${idx}][item_type]" value="INVENTARIO">
                        <div class="item-select-wrapper">
                            <input type="hidden" name="details[${idx}][product_id]" class="product-id-input" value="">
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
                    <td class="px-3 py-2 detail-qty-cell">
                        <input type="number" name="details[${idx}][quantity]" value="1" min="1" class="detail-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        <span class="detail-serial-qty text-sm text-gray-600 dark:text-gray-400 hidden"></span>
                        <span class="detail-batch-qty text-sm text-gray-700 dark:text-gray-300 hidden"></span>
                    </td>
                    <td class="px-3 py-2 detail-cost-cell">
                        <input type="number" name="details[${idx}][unit_cost]" value="0" min="0" step="0.01" class="detail-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        <span class="detail-serial-dash hidden">—</span>
                        <span class="detail-batch-cost text-sm text-gray-700 dark:text-gray-300 hidden"></span>
                    </td>
                    <td class="px-3 py-2 detail-expiration-cell text-sm text-gray-500 dark:text-gray-400">—</td>
                    <td class="px-3 py-2">
                        <span class="detail-subtotal text-sm font-medium">0.00</span>
                    </td>
                    <td class="px-3 py-2">
                        <button type="button" class="remove-row text-red-600 hover:text-red-800 text-sm">Quitar</button>
                    </td>
                `;
            }

            function createSerialDetailsRowUnidades(rowId, productId, detailRow) {
                const tr = document.createElement('tr');
                tr.className = 'serial-details-row bg-gray-50 dark:bg-gray-900/50';
                tr.setAttribute('data-parent-row-id', rowId);
                tr.setAttribute('data-product-id', productId);
                tr.innerHTML = `
                    <td colspan="6" class="px-3 py-3 border-t border-gray-200 dark:border-gray-700">
                        <div class="space-y-4 text-sm">
                            <div class="p-2 rounded bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800">
                                <p class="text-sm font-medium text-indigo-800 dark:text-indigo-200">Referencia de compra/origen</p>
                                <p class="mt-0.5 text-xs text-indigo-700 dark:text-indigo-300">La referencia será el mismo número de esta compra al guardarla (ej. Compra #47). No hace falta escribirla aquí.</p>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Unidades (cada una: serial + atributos + costo)</span>
                                <button type="button" class="btn-add-serial-unit text-indigo-600 hover:underline text-sm">+ Agregar unidad</button>
                            </div>
                            <div class="serial-items-container space-y-3"></div>
                        </div>
                    </td>
                `;
                detailRow.insertAdjacentElement('afterend', tr);
                const container = tr.querySelector('.serial-items-container');
                const form = document.getElementById('form-compra-productos');
                const urlTemplate = form.getAttribute('data-atributos-url');
                const url = urlTemplate.replace(/\/0\/atributos-categoria/, '/' + productId + '/atributos-categoria');
                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        const attrs = data.attributes || [];
                        function addUnit(index) {
                            const unitDiv = document.createElement('div');
                            unitDiv.className = 'serial-item border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700';
                            unitDiv.setAttribute('data-serial-index', index);
                            let attrsHtml = '';
                            attrs.forEach(function(attr) {
                                attrsHtml += `
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">${escapeHtml(attr.name)}</label>
                                        <input type="text" class="serial-attr-feature w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ej: valor para ${escapeHtml(attr.name)}" data-attr-id="${attr.id}" name="details[${rowId}][serial_items][${index}][features][${attr.id}]">
                                    </div>
                                `;
                            });
                            unitDiv.innerHTML = `
                                <div class="flex justify-between items-center serial-item-header">
                                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Unidad #${index + 1}</span>
                                    <button type="button" class="btn-remove-serial text-red-600 hover:underline text-sm">Eliminar</button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Número de serie (IMEI, etc.)</label>
                                        <input type="text" class="serial-number w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="Ej: IMEI-123456789" name="details[${rowId}][serial_items][${index}][serial_number]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Costo de esta unidad (€)</label>
                                        <input type="number" step="0.01" min="0" class="serial-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="0.00" name="details[${rowId}][serial_items][${index}][cost]">
                                    </div>
                                </div>
                                ${attrs.length ? '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' + attrsHtml + '</div>' : ''}
                            `;
                            container.appendChild(unitDiv);
                            unitDiv.querySelector('.serial-cost').addEventListener('input', function() { compraProductosUpdateSerialQtyAndSubtotal(detailRow); });
                            const removeBtn = unitDiv.querySelector('.btn-remove-serial');
                            if (removeBtn) {
                                removeBtn.addEventListener('click', function() {
                                    const items = container.querySelectorAll('.serial-item');
                                    if (items.length > 1) {
                                        unitDiv.remove();
                                        renumberSerialItems(container, rowId, attrs);
                                        compraProductosUpdateSerialQtyAndSubtotal(detailRow);
                                    }
                                });
                            }
                        }
                        addUnit(0);
                        tr.querySelector('.btn-add-serial-unit').addEventListener('click', function() {
                            const n = container.querySelectorAll('.serial-item').length;
                            addUnit(n);
                            renumberSerialItems(container, rowId, attrs);
                            compraProductosUpdateSerialQtyAndSubtotal(detailRow);
                        });
                        compraProductosUpdateSerialQtyAndSubtotal(detailRow);
                        toggleSerialRemoveButtons(container);
                    })
                    .catch(function() {
                        container.innerHTML = '<p class="text-xs text-amber-600 dark:text-amber-400">No se pudieron cargar los atributos. Añade al menos una unidad con serial y costo.</p>';
                        const unitDiv = document.createElement('div');
                        unitDiv.className = 'serial-item border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30';
                        unitDiv.innerHTML = `
                            <div class="text-sm font-semibold text-gray-600 dark:text-gray-300">Unidad #1</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Número de serie (IMEI, etc.)</label>
                                    <input type="text" class="serial-number w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[${rowId}][serial_items][0][serial_number]">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Costo de esta unidad (€)</label>
                                    <input type="number" step="0.01" min="0" class="serial-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[${rowId}][serial_items][0][cost]">
                                </div>
                            </div>
                        `;
                        container.appendChild(unitDiv);
                        unitDiv.querySelector('.serial-cost').addEventListener('input', function() { compraProductosUpdateSerialQtyAndSubtotal(detailRow); });
                        tr.querySelector('.btn-add-serial-unit').addEventListener('click', function() {
                            const n = container.querySelectorAll('.serial-item').length;
                            const u = document.createElement('div');
                            u.className = 'serial-item border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30';
                            u.innerHTML = `
                                <div class="flex justify-between"><span class="text-sm font-semibold">Unidad #${n + 1}</span><button type="button" class="btn-remove-serial text-red-600 hover:underline text-sm">Eliminar</button></div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Número de serie</label><input type="text" class="serial-number w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[${rowId}][serial_items][${n}][serial_number]"></div>
                                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Costo (€)</label><input type="number" step="0.01" min="0" class="serial-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[${rowId}][serial_items][${n}][cost]"></div>
                                </div>
                            `;
                            container.appendChild(u);
                            u.querySelector('.serial-cost').addEventListener('input', function() { compraProductosUpdateSerialQtyAndSubtotal(detailRow); });
                            u.querySelector('.btn-remove-serial').addEventListener('click', function() { if (container.querySelectorAll('.serial-item').length > 1) { u.remove(); renumberSerialItems(container, rowId, []); compraProductosUpdateSerialQtyAndSubtotal(detailRow); } });
                            compraProductosUpdateSerialQtyAndSubtotal(detailRow);
                        });
                        compraProductosUpdateSerialQtyAndSubtotal(detailRow);
                    });
            }

            window.compraProductosCreateSerialDetailsRowUnidades = createSerialDetailsRowUnidades;

            // DESHABILITADO: Esta función permitía crear variantes nuevas libremente.
            // Ahora solo se pueden seleccionar variantes existentes del producto mediante el modal SelectBatchVariantModal.
            // Se mantiene comentada por referencia, pero no se debe usar.
            /*
            function createBatchDetailsRow(rowId, productId, detailRow) {
                const tr = document.createElement('tr');
                tr.className = 'batch-details-row bg-gray-50 dark:bg-gray-900/50';
                tr.setAttribute('data-parent-row-id', rowId);
                tr.setAttribute('data-product-id', productId);
                tr.innerHTML = `
                    <td colspan="6" class="px-3 py-3 border-t border-gray-200 dark:border-gray-700">
                        <div class="space-y-4 text-sm">
                            <div class="p-2 rounded bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Variantes del lote (opcional)</p>
                                <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">Elige en los desplegables solo las opciones definidas para este producto (configura «Variantes permitidas» en el detalle del producto). Cantidad, costo y precio de venta por variante.</p>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Variantes (atributos, cantidad, costo, precio venta)</span>
                                <button type="button" class="btn-add-batch-variant text-indigo-600 hover:underline text-sm">+ Agregar variante</button>
                            </div>
                            <div class="batch-items-container space-y-3"></div>
                        </div>
                    </td>
                `;
                detailRow.insertAdjacentElement('afterend', tr);
                const container = tr.querySelector('.batch-items-container');
                const form = document.getElementById('form-compra-productos');
                const urlTemplate = form.getAttribute('data-atributos-url');
                const url = urlTemplate.replace(/\/0\/atributos-categoria/, '/' + productId + '/atributos-categoria');
                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        const attrs = data.attributes || [];
                        function addBatchVariant(index) {
                            const div = document.createElement('div');
                            div.className = 'batch-item border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700';
                            div.setAttribute('data-batch-index', index);
                            let attrsHtml = '';
                            attrs.forEach(function(attr) {
                                const options = attr.options || [];
                                let opts = '<option value="">—</option>';
                                options.forEach(function(opt) {
                                    opts += '<option value="' + escapeHtml(opt.value) + '">' + escapeHtml(opt.value) + '</option>';
                                });
                                attrsHtml += `
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">${escapeHtml(attr.name)}</label>
                                        <select class="batch-attr-feature w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" data-attr-id="${attr.id}" name="details[${rowId}][batch_items][${index}][features][${attr.id}]">${opts}</select>
                                    </div>
                                `;
                            });
                            div.innerHTML = `
                                <div class="flex justify-between items-center batch-item-header">
                                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Variante #${index + 1}</span>
                                    <button type="button" class="btn-remove-batch-variant text-red-600 hover:underline text-sm">Eliminar</button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Cantidad</label>
                                        <input type="number" min="1" class="batch-item-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" value="1" name="details[${rowId}][batch_items][${index}][quantity]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Costo unit. (€)</label>
                                        <input type="number" step="0.01" min="0" class="batch-item-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" value="0" name="details[${rowId}][batch_items][${index}][unit_cost]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Precio venta (€, opcional)</label>
                                        <input type="number" step="0.01" min="0" class="batch-item-price w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" placeholder="—" name="details[${rowId}][batch_items][${index}][price]">
                                    </div>
                                </div>
                                ${attrs.length ? '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' + attrsHtml + '</div>' : ''}
                            `;
                            container.appendChild(div);
                            div.querySelectorAll('.batch-item-qty, .batch-item-cost, .batch-item-price').forEach(function(inp) {
                                inp.addEventListener('input', function() { compraProductosUpdateBatchTotals(detailRow); });
                            });
                            const removeBtn = div.querySelector('.btn-remove-batch-variant');
                            if (removeBtn) {
                                removeBtn.addEventListener('click', function() {
                                    div.remove();
                                    renumberBatchItems(container, rowId, attrs);
                                    compraProductosUpdateBatchTotals(detailRow);
                                });
                            }
                        }
                        tr.querySelector('.btn-add-batch-variant').addEventListener('click', function() {
                            const n = container.querySelectorAll('.batch-item').length;
                            addBatchVariant(n);
                            renumberBatchItems(container, rowId, attrs);
                            compraProductosUpdateBatchTotals(detailRow);
                        });
                    })
                    .catch(function() {
                        tr.querySelector('.batch-items-container').innerHTML = '<p class="text-xs text-amber-600 dark:text-amber-400">No se pudieron cargar los atributos. Puedes agregar variantes con cantidad, costo y precio.</p>';
                        tr.querySelector('.btn-add-batch-variant').addEventListener('click', function() {
                            const container = tr.querySelector('.batch-items-container');
                            const p = container.querySelector('p');
                            if (p) p.remove();
                            const n = container.querySelectorAll('.batch-item').length;
                            const div = document.createElement('div');
                            div.className = 'batch-item border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30';
                            div.innerHTML = `
                                <div class="flex justify-between"><span class="text-sm font-semibold">Variante #${n + 1}</span><button type="button" class="btn-remove-batch-variant text-red-600 hover:underline text-sm">Eliminar</button></div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Cantidad</label><input type="number" min="1" class="batch-item-qty w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" value="1" name="details[${rowId}][batch_items][${n}][quantity]"></div>
                                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Costo unit. (€)</label><input type="number" step="0.01" min="0" class="batch-item-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" value="0" name="details[${rowId}][batch_items][${n}][unit_cost]"></div>
                                    <div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Precio venta (€)</label><input type="number" step="0.01" min="0" class="batch-item-price w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[${rowId}][batch_items][${n}][price]"></div>
                                </div>
                            `;
                            container.appendChild(div);
                            div.querySelectorAll('.batch-item-qty, .batch-item-cost, .batch-item-price').forEach(function(inp) {
                                inp.addEventListener('input', function() { compraProductosUpdateBatchTotals(detailRow); });
                            });
                            div.querySelector('.btn-remove-batch-variant').addEventListener('click', function() {
                                div.remove();
                                compraProductosUpdateBatchTotals(detailRow);
                            });
                            compraProductosUpdateBatchTotals(detailRow);
                        });
                    });
            }
            */

            // window.compraProductosCreateBatchDetailsRow = createBatchDetailsRow; // DESHABILITADO

            function renumberBatchItems(container, rowId, attrs) {
                const items = container.querySelectorAll('.batch-item');
                items.forEach(function(item, i) {
                    item.setAttribute('data-batch-index', String(i));
                    const qty = item.querySelector('.batch-item-qty');
                    const cost = item.querySelector('.batch-item-cost');
                    if (qty) qty.name = 'details[' + rowId + '][batch_items][' + i + '][quantity]';
                    if (cost) cost.name = 'details[' + rowId + '][batch_items][' + i + '][unit_cost]';
                    item.querySelectorAll('.batch-attr-feature').forEach(function(inp) {
                        const attrId = inp.getAttribute('data-attr-id');
                        if (attrId) inp.name = 'details[' + rowId + '][batch_items][' + i + '][features][' + attrId + ']';
                    });
                    const header = item.querySelector('.batch-item-header span');
                    if (header) header.textContent = 'Variante #' + (i + 1);
                });
            }

            function toggleSerialRemoveButtons(container) {
                const items = container.querySelectorAll('.serial-item');
                const showRemove = items.length > 1;
                items.forEach(function(item) {
                    const header = item.querySelector('.serial-item-header');
                    if (header) {
                        const btn = header.querySelector('.btn-remove-serial');
                        if (btn) btn.style.display = showRemove ? '' : 'none';
                    }
                });
            }

            function renumberSerialItems(container, rowId, attrs) {
                const items = container.querySelectorAll('.serial-item');
                items.forEach(function(item, i) {
                    item.setAttribute('data-serial-index', String(i));
                    var sn = item.querySelector('.serial-number');
                    var sc = item.querySelector('.serial-cost');
                    if (sn) sn.name = 'details[' + rowId + '][serial_items][' + i + '][serial_number]';
                    if (sc) sc.name = 'details[' + rowId + '][serial_items][' + i + '][cost]';
                    item.querySelectorAll('.serial-attr-feature').forEach(function(inp) {
                        const attrId = inp.getAttribute('data-attr-id');
                        if (attrId) inp.name = 'details[' + rowId + '][serial_items][' + i + '][features][' + attrId + ']';
                    });
                    const header = item.querySelector('.serial-item-header span');
                    if (header) header.textContent = 'Unidad #' + (i + 1);
                });
                toggleSerialRemoveButtons(container);
            }

            function updateSerialQtyAndSubtotal(detailRow) {
                const serialRow = detailRow.nextElementSibling;
                if (!serialRow || !serialRow.classList.contains('serial-details-row')) return;
                const container = serialRow.querySelector('.serial-items-container');
                const items = container.querySelectorAll('.serial-item');
                let total = 0;
                items.forEach(function(item) {
                    const cost = parseFloat(item.querySelector('.serial-cost').value) || 0;
                    total += cost;
                });
                const qtySpan = detailRow.querySelector('.detail-serial-qty');
                const qtyInput = detailRow.querySelector('.detail-qty');
                if (qtySpan) qtySpan.textContent = items.length + ' unidad(es)';
                if (qtyInput) qtyInput.value = items.length;
                detailRow.querySelector('.detail-subtotal').textContent = total.toFixed(2);
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function updateSubtotal(row) {
                const qty = parseFloat(row.querySelector('.detail-qty').value) || 0;
                const cost = parseFloat(row.querySelector('.detail-cost').value) || 0;
                row.querySelector('.detail-subtotal').textContent = (qty * cost).toFixed(2);
            }

            function renumberRows() {
                const rows = tbody.querySelectorAll('.detail-row');
                rows.forEach(function(row, i) {
                    row.setAttribute('data-row-id', String(i));
                    const itemTypeInput = row.querySelector('input[name*="[item_type]"]');
                    if (itemTypeInput) itemTypeInput.name = 'details[' + i + '][item_type]';
                    const productInput = row.querySelector('.product-id-input');
                    if (productInput) productInput.name = 'details[' + i + '][product_id]';
                    const descInput = row.querySelector('.item-description-input');
                    if (descInput) descInput.name = 'details[' + i + '][description]';
                    const qtyInput = row.querySelector('.detail-qty');
                    const costInput = row.querySelector('.detail-cost');
                    const isBatch = row.hasAttribute('data-is-batch');
                    if (qtyInput) qtyInput.name = isBatch ? 'details[' + i + '][batch_items][0][quantity]' : 'details[' + i + '][quantity]';
                    if (costInput) costInput.name = isBatch ? 'details[' + i + '][batch_items][0][unit_cost]' : 'details[' + i + '][unit_cost]';
                    if (isBatch) {
                        const batchItemIdInp = row.querySelector('input[name*="[batch_items][0][batch_item_id]"]');
                        if (batchItemIdInp) batchItemIdInp.name = 'details[' + i + '][batch_items][0][batch_item_id]';
                        row.querySelectorAll('input[name*="[batch_items][0][features]"]').forEach(function(inp) {
                            var m = inp.name.match(/\[features\]\[([^\]]+)\]$/);
                            if (m) inp.name = 'details[' + i + '][batch_items][0][features][' + m[1] + ']';
                        });
                        var expInp = row.querySelector('.detail-batch-expiration');
                        if (expInp) expInp.name = 'details[' + i + '][batch_items][0][expiration_date]';
                    }

                    const serialRow = row.nextElementSibling && row.nextElementSibling.classList.contains('serial-details-row') ? row.nextElementSibling : null;
                    if (serialRow) {
                        serialRow.setAttribute('data-parent-row-id', String(i));
                        const container = serialRow.querySelector('.serial-items-container');
                        if (container) {
                            container.querySelectorAll('.serial-item').forEach(function(item, j) {
                                var sn = item.querySelector('.serial-number');
                                var sc = item.querySelector('.serial-cost');
                                if (sn) sn.name = 'details[' + i + '][serial_items][' + j + '][serial_number]';
                                if (sc) sc.name = 'details[' + i + '][serial_items][' + j + '][cost]';
                                item.querySelectorAll('.serial-attr-feature').forEach(function(inp) {
                                    const attrId = inp.getAttribute('data-attr-id');
                                    if (attrId) inp.name = 'details[' + i + '][serial_items][' + j + '][features][' + attrId + ']';
                                });
                            });
                        }
                    }
                    const batchRow = row.nextElementSibling && row.nextElementSibling.classList.contains('batch-details-row') ? row.nextElementSibling : null;
                    if (batchRow) {
                        batchRow.setAttribute('data-parent-row-id', String(i));
                        const batchContainer = batchRow.querySelector('.batch-items-container');
                        if (batchContainer) {
                            const batchItems = batchContainer.querySelectorAll('.batch-item');
                            batchItems.forEach(function(item, j) {
                                const qty = item.querySelector('.batch-item-qty');
                                const cost = item.querySelector('.batch-item-cost');
                                if (qty) qty.name = 'details[' + i + '][batch_items][' + j + '][quantity]';
                                if (cost) cost.name = 'details[' + i + '][batch_items][' + j + '][unit_cost]';
                                item.querySelectorAll('.batch-attr-feature').forEach(function(inp) {
                                    const attrId = inp.getAttribute('data-attr-id');
                                    if (attrId) inp.name = 'details[' + i + '][batch_items][' + j + '][features][' + attrId + ']';
                                });
                            });
                        }
                    }
                });
            }

            function bindRowEvents(row) {
                row.querySelectorAll('.detail-qty, .detail-cost').forEach(function(input) {
                    input.addEventListener('input', function() { updateSubtotal(row); });
                });
                const btnSelect = row.querySelector('.btn-select-item');
                const btnChange = row.querySelector('.btn-change-item');
                if (btnSelect) {
                    btnSelect.addEventListener('click', function() {
                        const rowId = row.getAttribute('data-row-id');
                        Livewire.dispatch('open-select-item-for-row', { rowId: rowId, itemType: 'INVENTARIO' });
                    });
                }
                if (btnChange) {
                    btnChange.addEventListener('click', function() {
                        const rowId = row.getAttribute('data-row-id');
                        row.setAttribute('data-product-type', 'simple');
                        row.removeAttribute('data-product-id');
                        row.removeAttribute('data-batch-item-id');
                        row.removeAttribute('data-is-batch');
                        let next = row.nextElementSibling;
                        if (next && (next.classList.contains('serial-details-row') || next.classList.contains('batch-details-row'))) next.remove();
                        row.querySelector('.product-id-input').value = '';
                        row.querySelector('.item-description-input').value = '';
                        row.querySelector('.item-selected-name').textContent = '';
                        const featuresInput = row.querySelector('input[name*="[variant_features]"]');
                        if (featuresInput) featuresInput.remove();
                        row.querySelectorAll('input[name*="[batch_items]"]').forEach(function(inp) { inp.remove(); });
                        var expCell = row.querySelector('.detail-expiration-cell');
                        if (expCell) { expCell.textContent = '—'; expCell.classList.add('text-gray-500', 'dark:text-gray-400'); }
                        var qtyInput = row.querySelector('.detail-qty');
                        var costInput = row.querySelector('.detail-cost');
                        if (qtyInput) { qtyInput.value = '1'; qtyInput.name = 'details[' + rowId + '][quantity]'; qtyInput.classList.remove('hidden'); }
                        if (costInput) { costInput.value = '0'; costInput.name = 'details[' + rowId + '][unit_cost]'; costInput.classList.remove('hidden'); }
                        row.querySelector('.detail-serial-qty').classList.add('hidden').textContent = '';
                        row.querySelector('.detail-serial-dash').classList.add('hidden');
                        var bq = row.querySelector('.detail-batch-qty');
                        var bc = row.querySelector('.detail-batch-cost');
                        if (bq) { bq.classList.add('hidden'); bq.textContent = ''; }
                        if (bc) { bc.classList.add('hidden'); bc.textContent = ''; }
                        btnSelect.classList.remove('hidden');
                        btnChange.classList.add('hidden');
                        updateSubtotal(row);
                    });
                }
                const removeBtn = row.querySelector('.remove-row');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        if (tbody.querySelectorAll('.detail-row').length > 1) {
                            let next = row.nextElementSibling;
                            if (next && (next.classList.contains('serial-details-row') || next.classList.contains('batch-details-row'))) next.remove();
                            row.remove();
                            renumberRows();
                        }
                    });
                }
                updateSubtotal(row);
            }

            function addRow() {
                const rows = tbody.querySelectorAll('.detail-row');
                const tr = document.createElement('tr');
                tr.className = 'detail-row';
                tr.setAttribute('data-row-id', '0');
                tr.setAttribute('data-product-type', 'simple');
                tr.innerHTML = createRowHtml(0);
                tbody.insertBefore(tr, tbody.firstChild);
                renumberRows();
                bindRowEvents(tr);
            }

            document.getElementById('add-row-productos').addEventListener('click', addRow);
            tbody.querySelectorAll('.detail-row').forEach(bindRowEvents);

            // Filas seriales pre-renderizadas (edición): enlazar eventos para actualizar subtotal y eliminar unidad
            document.querySelectorAll('#details-body-productos .serial-details-row').forEach(function(serialRow) {
                const parentRow = serialRow.previousElementSibling;
                if (!parentRow || !parentRow.classList.contains('detail-row')) return;
                const rowId = parentRow.getAttribute('data-row-id');
                const container = serialRow.querySelector('.serial-items-container');
                if (!container) return;
                container.querySelectorAll('.serial-item').forEach(function(item) {
                    const costInp = item.querySelector('.serial-cost');
                    if (costInp) costInp.addEventListener('input', function() { updateSerialQtyAndSubtotal(parentRow); });
                    const removeBtn = item.querySelector('.btn-remove-serial');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            const items = container.querySelectorAll('.serial-item');
                            if (items.length > 1) {
                                item.remove();
                                container.querySelectorAll('.serial-item').forEach(function(it, j) {
                                    const sn = it.querySelector('.serial-number');
                                    const sc = it.querySelector('.serial-cost');
                                    if (sn) sn.name = 'details[' + rowId + '][serial_items][' + j + '][serial_number]';
                                    if (sc) sc.name = 'details[' + rowId + '][serial_items][' + j + '][cost]';
                                    it.querySelectorAll('.serial-attr-feature').forEach(function(inp) {
                                        const attrId = inp.getAttribute('data-attr-id');
                                        if (attrId) inp.name = 'details[' + rowId + '][serial_items][' + j + '][features][' + attrId + ']';
                                    });
                                    var h = it.querySelector('.serial-item-header span');
                                    if (h) h.textContent = 'Unidad #' + (j + 1);
                                });
                                updateSerialQtyAndSubtotal(parentRow);
                            }
                        });
                    }
                });
                const addBtn = serialRow.querySelector('.btn-add-serial-unit');
                if (addBtn) {
                    addBtn.addEventListener('click', function() {
                        const j = container.querySelectorAll('.serial-item').length;
                        const div = document.createElement('div');
                        div.className = 'serial-item border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-900/30 border-gray-200 dark:border-gray-700';
                        div.setAttribute('data-serial-index', j);
                        div.innerHTML = '<div class="flex justify-between items-center serial-item-header"><span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Unidad #' + (j + 1) + '</span><button type="button" class="btn-remove-serial text-red-600 hover:underline text-sm">Eliminar</button></div><div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Número de serie</label><input type="text" class="serial-number w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[' + rowId + '][serial_items][' + j + '][serial_number]"></div><div><label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-0.5">Costo (€)</label><input type="number" step="0.01" min="0" class="serial-cost w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm" name="details[' + rowId + '][serial_items][' + j + '][cost]" value="0"></div></div>';
                        container.appendChild(div);
                        div.querySelector('.serial-cost').addEventListener('input', function() { updateSerialQtyAndSubtotal(parentRow); });
                        div.querySelector('.btn-remove-serial').addEventListener('click', function() {
                            if (container.querySelectorAll('.serial-item').length > 1) {
                                div.remove();
                                container.querySelectorAll('.serial-item').forEach(function(it, jj) {
                                    var sn = it.querySelector('.serial-number');
                                    var sc = it.querySelector('.serial-cost');
                                    if (sn) sn.name = 'details[' + rowId + '][serial_items][' + jj + '][serial_number]';
                                    if (sc) sc.name = 'details[' + rowId + '][serial_items][' + jj + '][cost]';
                                    it.querySelectorAll('.serial-attr-feature').forEach(function(inp) {
                                        var attrId = inp.getAttribute('data-attr-id');
                                        if (attrId) inp.name = 'details[' + rowId + '][serial_items][' + jj + '][features][' + attrId + ']';
                                    });
                                    var h = it.querySelector('.serial-item-header span');
                                    if (h) h.textContent = 'Unidad #' + (jj + 1);
                                });
                                updateSerialQtyAndSubtotal(parentRow);
                            }
                        });
                        updateSerialQtyAndSubtotal(parentRow);
                    });
                }
                updateSerialQtyAndSubtotal(parentRow);
            });
        });
    </script>
</x-app-layout>
