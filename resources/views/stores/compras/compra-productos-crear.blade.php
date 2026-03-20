@php
    $purchase = $purchase ?? null;
    $detailsForEdit = $detailsForEdit ?? [];
    $editing = $purchase && $purchase instanceof \App\Models\Purchase;
    $formAction = $editing ? route('stores.product-purchases.update', [$store, $purchase]) : route('stores.product-purchases.store', $store);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ $editing ? 'Editar compra' : 'Nueva Compra de Productos' }} - {{ $store->name }}
            </h2>
            <a href="{{ $editing ? route('stores.purchases.show', [$store, $purchase]) : route('stores.product-purchases', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← {{ $editing ? 'Volver a compra' : 'Volver a Compra de productos' }}
            </a>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO'])
    @livewire('select-batch-variant-modal', ['storeId' => $store->id])
    @livewire('create-product-modal', ['storeId' => $store->id, 'fromPurchase' => true])

    <div class="py-12" x-data="compraProductosSelection()" x-init="init()">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(!$editing)
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400 border-b border-white/5/30 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-3">
                La compra se guardará como borrador. Podrás editarla o aprobarla después desde el listado de compras de productos.
            </p>
            @endif

            @if($proveedores->isEmpty())
            <div class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-amber-700 dark:text-amber-300">
                <p class="font-medium">Debes crear al menos un proveedor para registrar compras de productos.</p>
                <a href="{{ route('stores.proveedores', $store) }}" class="mt-2 inline-block text-sm font-medium text-amber-600 dark:text-amber-400 hover:underline">
                    Ir a crear proveedor →
                </a>
            </div>
            @endif

            <form method="POST" action="{{ $formAction }}" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6" id="form-compra-productos"
                  data-atributos-url="{{ route('stores.productos.atributos-categoria', [$store, 0]) }}"
                  data-currency="{{ $store->currency ?? 'COP' }}"
                  data-currency-symbol="{{ currency_symbol($store->currency ?? 'COP') }}"
                  x-on:item-selected.window="onItemSelected($event.detail)"
                  x-on:batch-variant-selected.window="onBatchVariantSelected($event.detail)">
                @csrf
                @if($editing)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor <span class="text-red-500">*</span></label>
                        <select name="proveedor_id" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" required {{ $proveedores->isEmpty() ? 'disabled' : '' }}>
                            @if($proveedores->isEmpty())
                                <option value="">Debe crear un proveedor primero</option>
                            @else
                                <option value="">Seleccione un proveedor</option>
                                @foreach($proveedores as $prov)
                                    <option value="{{ $prov->id }}" {{ old('proveedor_id', $editing ? $purchase->proveedor_id : null) == $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de Pago</label>
                        <select name="payment_status" x-model="paymentStatus" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3">
                            <option value="PAGADO" {{ old('payment_status', $editing ? $purchase->payment_status : 'PAGADO') == 'PAGADO' ? 'selected' : '' }}>Contado (Pagado)</option>
                            <option value="PENDIENTE" {{ old('payment_status', $editing ? $purchase->payment_status : null) == 'PENDIENTE' ? 'selected' : '' }}>A Crédito (Pendiente)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nº Factura Externa</label>
                        <input type="text" name="invoice_number" value="{{ old('invoice_number', $editing ? $purchase->invoice_number : '') }}" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" placeholder="Ej: F-001-0001234">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha Factura Externa</label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $editing && $purchase->invoice_date ? $purchase->invoice_date->format('Y-m-d') : '') }}" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3">
                    </div>
                    <div x-show="paymentStatus === 'PENDIENTE'" x-transition>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de vencimiento de la factura</label>
                        <input type="date" name="due_date" value="{{ old('due_date', $editing && $purchase->due_date ? $purchase->due_date->format('Y-m-d') : '') }}" x-bind:required="paymentStatus === 'PENDIENTE'" x-bind:disabled="paymentStatus !== 'PENDIENTE'" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" placeholder="Cuando vence la cuenta por pagar">
                        <p class="mt-0.5 text-xs text-gray-400">Solo para compras a crédito. Cuándo vence el pago.</p>
                    </div>
                </div>

                @php
                    $oldDetails = old('details');
                    if ($oldDetails !== null) {
                        $initialDetails = array_values($oldDetails);
                    } elseif ($editing && !empty($detailsForEdit)) {
                        $initialDetails = array_values($detailsForEdit);
                    } else {
                        $initialDetails = [];
                    }
                @endphp
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-100">Detalle (productos de inventario)</h3>
                        <button type="button" class="text-sm text-indigo-600 hover:text-indigo-800" @click="openCreateLine()">+ Agregar línea</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead class="border-b border-white/5">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Producto</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Cantidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Costo Unit. ({{ currency_symbol($store->currency ?? 'COP') }})</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Caducidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Subtotal</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-white/5">
                                <template x-if="details.length === 0">
                                    <tr>
                                        <td colspan="6" class="px-3 py-5 text-sm text-gray-400">No hay líneas aún. Haz clic en «Agregar línea» para iniciar.</td>
                                    </tr>
                                </template>
                                <template x-for="(line, idx) in details" :key="idx">
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-gray-100" x-text="line.description || '—'"></td>
                                        <td class="px-3 py-2 text-sm text-gray-100" x-text="line.quantity"></td>
                                        <td class="px-3 py-2 text-sm text-gray-100" x-text="formatMoney(line.unit_cost)"></td>
                                        <td class="px-3 py-2 text-sm text-gray-100" x-text="line.expiration_date || '—'"></td>
                                        <td class="px-3 py-2 text-sm font-semibold text-gray-100" x-text="formatMoney((line.quantity || 0) * (line.unit_cost || 0))"></td>
                                        <td class="px-3 py-2 text-sm space-x-3">
                                            <button type="button" class="text-indigo-500 hover:text-indigo-400" @click="openEditLine(idx)">Editar</button>
                                            <button type="button" class="text-red-600 hover:text-red-500" @click="removeLine(idx)">Quitar</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div id="details-hidden-inputs"></div>
                    <p class="mt-2 text-xs text-gray-400">
                        Productos de inventario (para reventa). Busca por nombre o SKU en «Seleccionar».
                    </p>
                </div>

                <div x-show="lineModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/60" @click="closeLineModal()"></div>
                    <div class="relative w-full max-w-xl rounded-lg border border-white/10 bg-gray-900 p-5">
                        <h4 class="text-base font-semibold text-white mb-4" x-text="lineModalMode === 'edit' ? 'Editar línea' : 'Agregar línea'"></h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Producto</label>
                                <div class="flex gap-2 items-center">
                                    <div class="flex-1 rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-100 min-h-[40px]" x-text="lineForm.description || 'Sin seleccionar'"></div>
                                    <button type="button" class="px-3 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700" @click="selectProductForModal()">Seleccionar</button>
                                </div>
                            </div>
                            <div x-show="lineModalError" class="rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-300" x-text="lineModalError"></div>
                            <div x-show="lineForm.product_type !== 'serialized'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Cantidad</label>
                                    <input type="number" min="1" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 text-sm" x-model.number="lineForm.quantity" @input="recomputeModalSubtotal()">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Costo unitario ({{ currency_symbol($store->currency ?? 'COP') }})</label>
                                    <input type="text" inputmode="decimal" autocomplete="off" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 text-sm" x-model="lineForm.unit_cost_display" @input="recomputeModalSubtotal()" @blur="normalizeModalCost()">
                                </div>
                            </div>
                            <div x-show="lineForm.product_type === 'batch'">
                                <label class="block text-xs text-gray-400 mb-1">Fecha de caducidad (opcional)</label>
                                <input type="date" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 text-sm" x-model="lineForm.expiration_date">
                            </div>
                            <div x-show="lineForm.product_type === 'serialized'" class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs text-gray-400">Unidades serializadas</label>
                                    <button type="button" class="text-xs text-indigo-400 hover:text-indigo-300" @click="addSerializedUnit()">+ Agregar unidad</button>
                                </div>
                                <template x-for="(unit, unitIdx) in lineForm.serial_items" :key="unitIdx">
                                    <div class="rounded-md border border-white/10 p-3 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-gray-400" x-text="'Unidad #' + (unitIdx + 1)"></span>
                                            <button type="button" class="text-xs text-red-400 hover:text-red-300" x-show="lineForm.serial_items.length > 1" @click="removeSerializedUnit(unitIdx)">Quitar</button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[11px] text-gray-400 mb-1">Serial *</label>
                                                <input type="text" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 text-sm" x-model="unit.serial_number" @input="recomputeModalSubtotal()">
                                            </div>
                                            <div>
                                                <label class="block text-[11px] text-gray-400 mb-1">Costo ({{ currency_symbol($store->currency ?? 'COP') }}) *</label>
                                                <input type="text" inputmode="decimal" autocomplete="off" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 text-sm" x-model="unit.cost_display" @input="recomputeModalSubtotal()" @blur="normalizeSerializedUnitCost(unitIdx)">
                                            </div>
                                        </div>
                                        <div x-show="serializedAttributes.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <template x-for="attr in serializedAttributes" :key="attr.id">
                                                <div>
                                                    <label class="block text-[11px] text-gray-400 mb-1" x-text="attr.name"></label>
                                                    <input type="text" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 text-sm"
                                                           x-model="unit.features[attr.id]">
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-200">
                                Subtotal: <span class="font-semibold" x-text="formatMoney(lineForm.subtotal || 0)"></span>
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" class="px-3 py-2 text-sm rounded-md bg-gray-700 text-gray-200 hover:bg-gray-600" @click="closeLineModal()">Cancelar</button>
                            <button type="button" class="px-3 py-2 text-sm rounded-md bg-brand text-white hover:opacity-90" @click="saveLine()">Guardar línea</button>
                        </div>
                    </div>
                </div>

                <div class="compra-productos-validation-error hidden mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">Debes seleccionar al menos un producto en el detalle. Haz clic en «Seleccionar» en cada línea.</span>
                    <button type="button" class="absolute top-2 right-2 text-red-600 hover:text-red-800" onclick="this.parentElement.classList.add('hidden')" aria-label="Cerrar">×</button>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ $editing ? route('stores.purchases.show', [$store, $purchase]) : route('stores.product-purchases', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        Cancelar
                    </a>
                    <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed" {{ $proveedores->isEmpty() ? 'disabled' : '' }}>
                        {{ $editing ? 'Guardar cambios' : 'Guardar como borrador' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function compraProductosSelection() {
            const currency = document.getElementById('form-compra-productos')?.dataset?.currency || 'COP';
            const initialDetails = @json($initialDetails);

            const hasDecimals = (cur) => !['COP', 'CLP', 'JPY'].includes(String(cur || 'COP').toUpperCase());
            const parseMoney = (value) => {
                const str = String(value ?? '').trim();
                if (!str) return 0;
                if (!hasDecimals(currency)) return parseFloat(str.replace(/\./g, '').replace(/,/g, '')) || 0;
                return parseFloat(str.replace(/,/g, '')) || 0;
            };
            const formatMoney = (value) => {
                const amount = Number(value || 0);
                if (!hasDecimals(currency)) return Math.round(amount).toLocaleString('es-CO');
                return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            const normalizeLine = (raw) => {
                const d = (raw && typeof raw === 'object') ? raw : {};
                const bi = Array.isArray(d.batch_items) ? (d.batch_items[0] || {}) : {};
                const isBatch = !!(bi && (bi.product_variant_id || bi.batch_item_id));
                const serialItemsRaw = Array.isArray(d.serial_items) ? d.serial_items : [];
                const isSerialized = serialItemsRaw.length > 0 || String(d.product_type || '') === 'serialized';
                const serialItems = serialItemsRaw.map((row) => {
                    const unit = (row && typeof row === 'object') ? row : {};
                    const cost = parseFloat(unit.cost) || 0;
                    return {
                        serial_number: String(unit.serial_number || ''),
                        cost: cost,
                        cost_display: formatMoney(cost),
                        features: unit.features && typeof unit.features === 'object' ? unit.features : {}
                    };
                });
                const serializedQty = serialItems.length > 0 ? serialItems.length : Math.max(1, parseInt(d.quantity, 10) || 1);
                const serializedTotal = serialItems.reduce((acc, u) => acc + (parseFloat(u.cost) || 0), 0);
                return {
                    item_type: 'INVENTARIO',
                    product_id: Number(d.product_id || 0) || null,
                    description: String(d.description || ''),
                    product_type: isSerialized ? 'serialized' : (isBatch ? 'batch' : String(d.product_type || 'simple')),
                    product_variant_id: bi.product_variant_id ? Number(bi.product_variant_id) : null,
                    quantity: isSerialized
                        ? serializedQty
                        : Math.max(1, parseInt(isBatch ? (bi.quantity ?? d.quantity) : d.quantity, 10) || 1),
                    unit_cost: isSerialized
                        ? (serializedQty > 0 ? (serializedTotal / serializedQty) : 0)
                        : (parseFloat(isBatch ? (bi.unit_cost ?? d.unit_cost) : d.unit_cost) || 0),
                    expiration_date: isBatch ? String(bi.expiration_date || '') : '',
                    serial_items: serialItems
                };
            };

            return {
                paymentStatus: @json(old('payment_status', $editing ? $purchase->payment_status : 'PENDIENTE')),
                details: [],
                lineModalOpen: false,
                lineModalMode: 'create',
                editIndex: null,
                lineModalError: '',
                serializedAttributes: [],
                lineForm: {
                    product_id: null,
                    description: '',
                    product_type: 'simple',
                    product_variant_id: null,
                    quantity: 1,
                    unit_cost: 0,
                    unit_cost_display: '',
                    expiration_date: '',
                    subtotal: 0,
                    serial_items: []
                },
                formatMoney(value) { return formatMoney(value); },
                init() {
                    this.details = (initialDetails || []).map(normalizeLine);
                    this.syncHiddenInputs();
                },
                recomputeModalSubtotal() {
                    if (this.lineForm.product_type === 'serialized') {
                        let total = 0;
                        this.lineForm.serial_items = (this.lineForm.serial_items || []).map((unit) => {
                            const cost = parseMoney(unit.cost_display);
                            total += cost;
                            return {
                                ...unit,
                                serial_number: String(unit.serial_number || ''),
                                cost,
                                cost_display: unit.cost_display ?? formatMoney(cost),
                                features: unit.features && typeof unit.features === 'object' ? unit.features : {}
                            };
                        });
                        const qty = this.lineForm.serial_items.length || 0;
                        this.lineForm.quantity = qty;
                        this.lineForm.unit_cost = qty > 0 ? (total / qty) : 0;
                        this.lineForm.unit_cost_display = formatMoney(this.lineForm.unit_cost);
                        this.lineForm.subtotal = total;
                        return;
                    }
                    this.lineForm.quantity = Math.max(1, parseInt(this.lineForm.quantity, 10) || 1);
                    this.lineForm.unit_cost = parseMoney(this.lineForm.unit_cost_display);
                    this.lineForm.subtotal = this.lineForm.quantity * this.lineForm.unit_cost;
                },
                normalizeModalCost() {
                    if (this.lineForm.product_type === 'serialized') return;
                    this.lineForm.unit_cost = parseMoney(this.lineForm.unit_cost_display);
                    this.lineForm.unit_cost_display = formatMoney(this.lineForm.unit_cost);
                    this.recomputeModalSubtotal();
                },
                normalizeSerializedUnitCost(index) {
                    const unit = this.lineForm.serial_items[index];
                    if (!unit) return;
                    unit.cost = parseMoney(unit.cost_display);
                    unit.cost_display = formatMoney(unit.cost);
                    this.recomputeModalSubtotal();
                },
                addSerializedUnit() {
                    const features = {};
                    (this.serializedAttributes || []).forEach((attr) => {
                        features[attr.id] = '';
                    });
                    this.lineForm.serial_items.push({
                        serial_number: '',
                        cost: 0,
                        cost_display: formatMoney(0),
                        features
                    });
                    this.recomputeModalSubtotal();
                },
                removeSerializedUnit(index) {
                    this.lineForm.serial_items.splice(index, 1);
                    if (this.lineForm.serial_items.length === 0) {
                        this.addSerializedUnit();
                    } else {
                        this.recomputeModalSubtotal();
                    }
                },
                openCreateLine() {
                    this.lineModalMode = 'create';
                    this.editIndex = null;
                    this.lineModalError = '';
                    this.lineForm = {
                        product_id: null,
                        description: '',
                        product_type: 'simple',
                        product_variant_id: null,
                        quantity: 1,
                        unit_cost: 0,
                        unit_cost_display: formatMoney(0),
                        expiration_date: '',
                        subtotal: 0,
                        serial_items: []
                    };
                    this.serializedAttributes = [];
                    this.lineModalOpen = true;
                },
                openEditLine(index) {
                    const line = this.details[index];
                    if (!line) return;
                    this.lineModalMode = 'edit';
                    this.editIndex = index;
                    this.lineModalError = '';
                    this.lineForm = {
                        ...line,
                        unit_cost_display: formatMoney(line.unit_cost),
                        subtotal: line.quantity * line.unit_cost,
                        serial_items: Array.isArray(line.serial_items) ? line.serial_items.map((u) => ({
                            serial_number: String(u.serial_number || ''),
                            cost: parseFloat(u.cost) || 0,
                            cost_display: formatMoney(parseFloat(u.cost) || 0),
                            features: u.features && typeof u.features === 'object' ? u.features : {}
                        })) : []
                    };
                    if (this.lineForm.product_type === 'serialized' && this.lineForm.serial_items.length === 0) {
                        this.addSerializedUnit();
                    }
                    this.recomputeModalSubtotal();
                    this.lineModalOpen = true;
                    if (this.lineForm.product_type === 'serialized' && this.lineForm.product_id) {
                        this.loadSerializedAttributes(this.lineForm.product_id);
                    }
                },
                closeLineModal() {
                    this.lineModalOpen = false;
                },
                selectProductForModal() {
                    Livewire.dispatch('open-select-item-for-row', { rowId: 'line-modal', itemType: 'INVENTARIO' });
                },
                onItemSelected(detail) {
                    if (!this.lineModalOpen || detail.type !== 'INVENTARIO' || detail.rowId !== 'line-modal') return;
                    this.lineModalError = '';
                    const productType = detail.productType || 'simple';
                    this.lineForm.product_id = Number(detail.id);
                    this.lineForm.product_type = productType;
                    this.lineForm.product_variant_id = detail.productVariantId || detail.variant_id || null;
                    this.lineForm.description = detail.name || '';
                    if (productType === 'batch' && !this.lineForm.product_variant_id) {
                        Livewire.dispatch('open-select-batch-variant', {
                            productId: this.lineForm.product_id,
                            rowId: 'line-modal',
                            productName: this.lineForm.description
                        });
                    }
                    if (productType === 'batch' && this.lineForm.product_variant_id) {
                        this.lineForm.description = detail.name || this.lineForm.description;
                    }
                    if (productType !== 'batch') {
                        this.lineForm.expiration_date = '';
                        this.lineForm.product_variant_id = null;
                    }
                    if (productType === 'serialized' && (!Array.isArray(this.lineForm.serial_items) || this.lineForm.serial_items.length === 0)) {
                        this.lineForm.serial_items = [];
                        this.addSerializedUnit();
                    }
                    if (productType === 'serialized') {
                        this.loadSerializedAttributes(this.lineForm.product_id);
                    } else {
                        this.serializedAttributes = [];
                    }
                    this.recomputeModalSubtotal();
                },
                async loadSerializedAttributes(productId) {
                    const form = document.getElementById('form-compra-productos');
                    const template = form?.getAttribute('data-atributos-url') || '';
                    if (!template || !productId) {
                        this.serializedAttributes = [];
                        return;
                    }
                    try {
                        const url = template.replace(/\/0\/atributos-categoria$/, `/${productId}/atributos-categoria`);
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await res.json();
                        this.serializedAttributes = Array.isArray(data.attributes) ? data.attributes : [];
                        if (Array.isArray(this.lineForm.serial_items)) {
                            this.lineForm.serial_items = this.lineForm.serial_items.map((unit) => {
                                const features = unit.features && typeof unit.features === 'object' ? { ...unit.features } : {};
                                this.serializedAttributes.forEach((attr) => {
                                    if (features[attr.id] === undefined) features[attr.id] = '';
                                });
                                return { ...unit, features };
                            });
                        }
                    } catch (e) {
                        this.serializedAttributes = [];
                    }
                },
                onBatchVariantSelected(detail) {
                    if (!this.lineModalOpen || detail.rowId !== 'line-modal') return;
                    this.lineForm.product_type = 'batch';
                    this.lineForm.product_variant_id = detail.productVariantId || null;
                    const base = detail.productName || this.lineForm.description || '';
                    const display = detail.displayName ? `${base} — ${detail.displayName}` : base;
                    this.lineForm.description = display;
                },
                saveLine() {
                    this.recomputeModalSubtotal();
                    if (!this.lineForm.product_id) {
                        this.lineModalError = 'Debes seleccionar un producto.';
                        return;
                    }
                    if (this.lineForm.product_type === 'batch' && !this.lineForm.product_variant_id) {
                        this.lineModalError = 'Debes seleccionar una variante para producto por lote.';
                        return;
                    }
                    if (this.lineForm.product_type === 'serialized') {
                        const units = Array.isArray(this.lineForm.serial_items) ? this.lineForm.serial_items : [];
                        if (units.length === 0) {
                            this.lineModalError = 'Debes agregar al menos una unidad serializada.';
                            return;
                        }
                        const seen = new Set();
                        for (const u of units) {
                            const serial = String(u.serial_number || '').trim();
                            if (!serial) {
                                this.lineModalError = 'Cada unidad serializada debe tener número de serie.';
                                return;
                            }
                            const key = serial.toLowerCase();
                            if (seen.has(key)) {
                                this.lineModalError = 'No puedes repetir seriales en la misma línea.';
                                return;
                            }
                            seen.add(key);
                            if ((parseFloat(u.cost) || 0) < 0) {
                                this.lineModalError = 'El costo de una unidad serializada no puede ser negativo.';
                                return;
                            }
                        }
                    }
                    const payload = {
                        item_type: 'INVENTARIO',
                        product_id: this.lineForm.product_id,
                        description: this.lineForm.description,
                        product_type: this.lineForm.product_type,
                        product_variant_id: this.lineForm.product_variant_id || null,
                        quantity: this.lineForm.quantity,
                        unit_cost: this.lineForm.unit_cost,
                        expiration_date: this.lineForm.product_type === 'batch' ? (this.lineForm.expiration_date || '') : '',
                        serial_items: this.lineForm.product_type === 'serialized'
                            ? (this.lineForm.serial_items || []).map((u) => ({
                                serial_number: String(u.serial_number || '').trim(),
                                cost: parseFloat(u.cost) || 0,
                                features: u.features && typeof u.features === 'object' ? u.features : {}
                            }))
                            : []
                    };
                    if (this.lineModalMode === 'edit' && this.editIndex !== null) {
                        this.details.splice(this.editIndex, 1, payload);
                    } else {
                        this.details.push(payload);
                    }
                    this.syncHiddenInputs();
                    this.closeLineModal();
                },
                removeLine(index) {
                    this.details.splice(index, 1);
                    this.syncHiddenInputs();
                },
                syncHiddenInputs() {
                    const container = document.getElementById('details-hidden-inputs');
                    if (!container) return;
                    const html = [];
                    this.details.forEach((line, i) => {
                        html.push(`<input type="hidden" name="details[${i}][item_type]" value="INVENTARIO">`);
                        html.push(`<input type="hidden" name="details[${i}][product_id]" value="${line.product_id || ''}">`);
                        html.push(`<input type="hidden" name="details[${i}][description]" value="${String(line.description || '').replace(/"/g, '&quot;')}">`);
                        html.push(`<input type="hidden" name="details[${i}][quantity]" value="${line.quantity}">`);
                        html.push(`<input type="hidden" name="details[${i}][unit_cost]" value="${line.unit_cost}">`);
                        if (line.product_type === 'batch') {
                            html.push(`<input type="hidden" name="details[${i}][batch_items][0][quantity]" value="${line.quantity}">`);
                            html.push(`<input type="hidden" name="details[${i}][batch_items][0][unit_cost]" value="${line.unit_cost}">`);
                            html.push(`<input type="hidden" name="details[${i}][batch_items][0][product_variant_id]" value="${line.product_variant_id || ''}">`);
                            html.push(`<input type="hidden" name="details[${i}][batch_items][0][expiration_date]" value="${line.expiration_date || ''}">`);
                        }
                        if (line.product_type === 'serialized' && Array.isArray(line.serial_items)) {
                            line.serial_items.forEach((unit, j) => {
                                const serial = String(unit.serial_number || '').replace(/"/g, '&quot;');
                                const cost = parseFloat(unit.cost) || 0;
                                html.push(`<input type="hidden" name="details[${i}][serial_items][${j}][serial_number]" value="${serial}">`);
                                html.push(`<input type="hidden" name="details[${i}][serial_items][${j}][cost]" value="${cost}">`);
                                const features = unit.features && typeof unit.features === 'object' ? unit.features : {};
                                Object.keys(features).forEach((attrId) => {
                                    const raw = features[attrId];
                                    const val = String(raw == null ? '' : raw).replace(/"/g, '&quot;');
                                    html.push(`<input type="hidden" name="details[${i}][serial_items][${j}][features][${attrId}]" value="${val}">`);
                                });
                            });
                        }
                    });
                    container.innerHTML = html.join('');
                }
            };
        }
    </script>
</x-app-layout>
