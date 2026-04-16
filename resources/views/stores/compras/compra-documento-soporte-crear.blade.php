<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Documento soporte - Nueva compra
            </h2>
            <a href="{{ route('stores.product-purchases', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver a Compra de productos
            </a>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO', 'rowId' => 'support-doc-line'])

    <div
        class="py-12"
        x-data="supportDocSelection({
            initialPaymentStatus: @js(old('payment_status', 'PAGADO')),
            initialDueDate: @js(old('due_date')),
            initialProveedor: @js(old('proveedor_id')),
            initialDetails: @js(old('inventory_items', []))
        })"
        x-init="init()"
        x-on:item-selected.window="onItemSelected($event.detail)"
    >
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    <p class="font-semibold mb-2">No se pudo guardar el documento soporte:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($proveedores->isEmpty())
                <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-amber-700 dark:text-amber-300">
                    <p class="font-medium">Debes crear al menos un proveedor para vincular al vendedor.</p>
                    <a href="{{ route('stores.proveedores', $store) }}" class="mt-2 inline-block text-sm font-medium text-amber-600 dark:text-amber-400 hover:underline">
                        Ir a proveedores →
                    </a>
                </div>
            @endif

            <form action="{{ route('stores.product-purchases.documento-soporte.store', $store) }}" method="post" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6">
                @csrf

                <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div class="xl:col-span-2 rounded-lg border border-white/10 bg-white/5 p-4">
                        <h3 class="text-sm font-semibold text-gray-100 mb-3">Emisor (tu tienda)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-gray-400">Razón social</p>
                                <p class="text-gray-100 font-medium">{{ $store->name }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">NIT / RUT</p>
                                <p class="text-gray-100 font-medium">{{ $store->rut_nit ?: 'Por configurar' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">Dirección</p>
                                <p class="text-gray-100">{{ $store->address ?: 'Por configurar' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400">Ciudad / país</p>
                                <p class="text-gray-100">{{ trim(($store->city ?: 'Ciudad').($store->country ? ', '.$store->country : '')) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-white/10 bg-white/5 p-4 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-100">Documento soporte</h3>
                        <div>
                            <p class="text-xs text-gray-400">Número (asignado por sistema)</p>
                            <p class="text-base font-semibold text-brand">DSE - (se asignará al guardar)</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Fecha de emisión</label>
                            <input type="date" name="issue_date" value="{{ old('issue_date', now()->format('Y-m-d')) }}" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3">
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-white/10 p-4">
                    <h3 class="text-sm font-semibold text-gray-100 border-b border-white/10 pb-2 mb-4">Datos de la compra</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Proveedor / vendedor <span class="text-red-500">*</span></label>
                            <select name="proveedor_id" x-model="proveedorId" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" {{ $proveedores->isEmpty() ? 'disabled' : '' }}>
                                @if($proveedores->isEmpty())
                                    <option value="">Cree un proveedor primero</option>
                                @else
                                    <option value="">Seleccione</option>
                                    @foreach($proveedores as $prov)
                                        <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Forma de pago</label>
                            <select name="payment_status" x-model="paymentStatus" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" @change="syncHiddenInputs()">
                                <option value="PAGADO">Contado</option>
                                <option value="PENDIENTE">A crédito (pendiente)</option>
                            </select>
                        </div>
                        <div x-show="paymentStatus === 'PENDIENTE'" x-transition>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Fecha de vencimiento</label>
                            <input type="date" name="due_date" x-model="dueDate" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Observaciones</label>
                            <textarea name="notes" rows="2" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" placeholder="Notas internas del documento soporte">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-white/10 p-4">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-100">Detalle (productos de inventario)</h3>
                        <button type="button" class="text-sm text-brand hover:underline" @click="openProductSelector()">+ Agregar producto</button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-white/10">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Item</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Descripción</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Cant.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">V. Unit ({{ currency_symbol($store->currency ?? 'COP') }})</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">IVA</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">V. Total</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="details.length === 0">
                                    <tr>
                                        <td colspan="7" class="px-3 py-6 text-sm text-gray-500 text-center">No hay productos agregados.</td>
                                    </tr>
                                </template>
                                <template x-for="(line, index) in details" :key="index">
                                    <tr>
                                        <td class="px-3 py-3 text-sm text-gray-400" x-text="index + 1"></td>
                                        <td class="px-3 py-3 text-sm text-gray-200" x-text="line.description"></td>
                                        <td class="px-3 py-3 text-sm">
                                            <input type="number" min="0.01" step="0.01" class="w-24 rounded-md border-white/10 bg-white/5 text-gray-100 py-1 px-2" x-model.number="line.quantity" @input="recomputeLine(index)">
                                        </td>
                                        <td class="px-3 py-3 text-sm">
                                            <input type="number" min="0" step="0.01" class="w-32 rounded-md border-white/10 bg-white/5 text-gray-100 py-1 px-2" x-model.number="line.unit_cost" @input="recomputeLine(index)">
                                        </td>
                                        <td class="px-3 py-3 text-sm">
                                            <input type="number" min="0" step="0.01" class="w-24 rounded-md border-white/10 bg-white/5 text-gray-100 py-1 px-2" x-model.number="line.tax_rate" @input="recomputeLine(index)">
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-200" x-text="formatMoney(line.line_total)"></td>
                                        <td class="px-3 py-3 text-sm">
                                            <button type="button" class="text-red-400 hover:text-red-300" @click="removeLine(index)">Quitar</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-white/10 p-4" x-show="paymentStatus === 'PAGADO'" x-transition>
                    <h3 class="text-sm font-semibold text-gray-100 mb-2">Pago contado</h3>
                    <p class="text-sm text-gray-400">
                        El reparto por bolsillos se define al <strong class="text-gray-300">aprobar</strong> el documento desde la pantalla de edición del borrador.
                    </p>
                </section>

                <section class="grid grid-cols-1 gap-4">
                    <div class="rounded-lg border border-white/10 p-4">
                        <h3 class="text-sm font-semibold text-gray-100 mb-3">Totales</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between text-gray-400">
                                <span>Total bruto</span>
                                <span x-text="formatMoney(summary.subtotal)"></span>
                            </div>
                            <div class="flex items-center justify-between text-gray-400">
                                <span>IVA</span>
                                <span x-text="formatMoney(summary.tax_total)"></span>
                            </div>
                            <div class="border-t border-white/10 pt-2 flex items-center justify-between text-gray-100 font-semibold">
                                <span>Total a pagar</span>
                                <span x-text="'{{ currency_symbol($store->currency ?? 'COP') }} ' + formatMoney(summary.total)"></span>
                            </div>
                        </div>
                    </div>
                </section>

                <div id="support-doc-hidden-inputs"></div>

                <div class="flex flex-wrap items-center gap-3 justify-end border-t border-white/10 pt-6">
                    <a href="{{ route('stores.product-purchases', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
                    <button type="submit" class="px-4 py-2 rounded-md text-white" :class="canSubmit() ? 'bg-brand hover:opacity-90' : 'bg-gray-600 cursor-not-allowed'" :disabled="!canSubmit()">
                        Guardar documento soporte (borrador)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function supportDocSelection(config = {}) {
            return {
                proveedorId: config.initialProveedor ? String(config.initialProveedor) : '',
                paymentStatus: config.initialPaymentStatus || 'PAGADO',
                dueDate: config.initialDueDate || '',
                details: Array.isArray(config.initialDetails) ? config.initialDetails.map((line) => ({
                    product_id: Number(line.product_id || 0),
                    description: String(line.description || ''),
                    quantity: Math.max(0.01, Number(line.quantity || 0.01)),
                    unit_cost: Math.max(0, Number(line.unit_cost || 0)),
                    tax_rate: Math.max(0, Number(line.tax_rate || 0)),
                    tax_amount: 0,
                    line_total: 0,
                })) : [],
                summary: { subtotal: 0, tax_total: 0, total: 0 },
                init() {
                    this.recomputeAll();
                    this.syncHiddenInputs();
                },
                openProductSelector() {
                    Livewire.dispatch('open-select-item-for-row', { rowId: 'support-doc-line', itemType: 'INVENTARIO' });
                },
                onItemSelected(detail) {
                    if (!detail || detail.type !== 'INVENTARIO' || detail.rowId !== 'support-doc-line') return;
                    this.details.push({
                        product_id: Number(detail.id),
                        description: String(detail.name || ''),
                        quantity: 1,
                        unit_cost: 0,
                        tax_rate: 0,
                        tax_amount: 0,
                        line_total: 0,
                    });
                    this.recomputeAll();
                },
                recomputeLine(index) {
                    const line = this.details[index];
                    if (!line) return;
                    line.quantity = Math.round(Math.max(0.01, parseFloat(line.quantity || 0.01)) * 100) / 100;
                    line.unit_cost = Math.max(0, parseFloat(line.unit_cost || 0));
                    line.tax_rate = Math.max(0, parseFloat(line.tax_rate || 0));
                    const base = line.quantity * line.unit_cost;
                    line.tax_amount = base * (line.tax_rate / 100);
                    line.line_total = base + line.tax_amount;
                    this.recomputeSummary();
                    this.syncHiddenInputs();
                },
                recomputeAll() {
                    this.details.forEach((_, idx) => this.recomputeLine(idx));
                    this.recomputeSummary();
                    this.syncHiddenInputs();
                },
                recomputeSummary() {
                    let subtotal = 0;
                    let taxTotal = 0;
                    this.details.forEach((line) => {
                        const base = parseFloat(line.quantity || 0) * parseFloat(line.unit_cost || 0);
                        const tax = base * ((parseFloat(line.tax_rate || 0)) / 100);
                        subtotal += base;
                        taxTotal += tax;
                    });
                    this.summary.subtotal = subtotal;
                    this.summary.tax_total = taxTotal;
                    this.summary.total = subtotal + taxTotal;
                },
                removeLine(index) {
                    this.details.splice(index, 1);
                    this.recomputeSummary();
                    this.syncHiddenInputs();
                },
                canSubmit() {
                    if (!this.proveedorId) return false;
                    if (this.details.length === 0) return false;
                    if (this.paymentStatus === 'PENDIENTE' && !this.dueDate) return false;
                    return true;
                },
                syncHiddenInputs() {
                    const container = document.getElementById('support-doc-hidden-inputs');
                    if (!container) return;
                    const html = [];

                    this.details.forEach((line, i) => {
                        html.push(`<input type="hidden" name="inventory_items[${i}][product_id]" value="${line.product_id}">`);
                        html.push(`<input type="hidden" name="inventory_items[${i}][description]" value="${String(line.description || '').replace(/"/g, '&quot;')}">`);
                        html.push(`<input type="hidden" name="inventory_items[${i}][quantity]" value="${line.quantity}">`);
                        html.push(`<input type="hidden" name="inventory_items[${i}][unit_cost]" value="${line.unit_cost}">`);
                        html.push(`<input type="hidden" name="inventory_items[${i}][tax_rate]" value="${line.tax_rate}">`);
                    });

                    container.innerHTML = html.join('');
                },
                formatMoney(value) {
                    return Number(value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
</x-app-layout>
