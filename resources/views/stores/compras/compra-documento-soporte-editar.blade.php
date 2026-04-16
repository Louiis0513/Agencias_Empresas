@php
    $initialDetails = old('inventory_items', $supportDocument->inventoryItems->map(function ($line) {
        return [
            'product_id' => $line->product_id,
            'description' => $line->description,
            'quantity' => (float) $line->quantity,
            'unit_cost' => (float) $line->unit_cost,
            'tax_rate' => (float) ($line->tax_rate ?? 0),
        ];
    })->values()->all());
    $isBorrador = $supportDocument->status === \App\Models\SupportDocument::STATUS_BORRADOR;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Documento soporte #{{ $supportDocument->doc_prefix }}-{{ $supportDocument->doc_number }}
            </h2>
            <div class="flex flex-col items-end gap-2 sm:flex-row sm:items-center sm:gap-4">
                @storeCan($store, 'support-documents.print')
                <a href="{{ route('stores.product-purchases.documento-soporte.print', [$store, $supportDocument]) }}" target="_blank" rel="noopener" class="text-sm text-brand hover:text-white transition">
                    Imprimir tira (PDF)
                </a>
                @endstoreCan
                @storeCan($store, 'support-documents.view')
                <a href="{{ route('stores.product-purchases.documento-soporte.create', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    ← Volver a nueva compra (documento soporte)
                </a>
                @endstoreCan
            </div>
        </div>
    </x-slot>

    @livewire('select-item-modal', ['storeId' => $store->id, 'itemType' => 'INVENTARIO', 'rowId' => 'support-doc-line-edit'])

    <div
        class="py-12"
        x-data="supportDocEdit({
            initialPaymentStatus: @js(old('payment_status', $supportDocument->payment_status)),
            initialDueDate: @js(old('due_date', optional($supportDocument->due_date)->format('Y-m-d'))),
            initialProveedor: @js(old('proveedor_id', (string) $supportDocument->proveedor_id)),
            initialDetails: @js($initialDetails),
            initialPaymentParts: @js(old('payment_parts', [])),
            readonly: @js(!$isBorrador)
        })"
        x-init="init()"
        x-on:item-selected.window="onItemSelected($event.detail)"
    >
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($errors->any())
                <div class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    <p class="font-semibold mb-2">No se pudo completar la acción:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-sm text-gray-300">
                Estado actual:
                <strong class="text-white">{{ $supportDocument->status }}</strong>
                @if($supportDocument->comprobante_egreso_id)
                    — Comprobante egreso:
                    <strong class="text-white">#{{ $supportDocument->comprobanteEgreso?->number ?? $supportDocument->comprobante_egreso_id }}</strong>
                @endif
            </div>

            <form id="support-doc-update-form" action="{{ route('stores.product-purchases.documento-soporte.update', [$store, $supportDocument]) }}" method="post" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6">
                @csrf
                @method('PUT')

                <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div class="xl:col-span-2 rounded-lg border border-white/10 bg-white/5 p-4">
                        <h3 class="text-sm font-semibold text-gray-100 mb-3">Documento soporte</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-gray-400">Número</p>
                                <p class="text-gray-100 font-medium">{{ $supportDocument->doc_prefix }}-{{ $supportDocument->doc_number }}</p>
                            </div>
                            <div>
                                <label class="block text-gray-400 mb-1">Fecha de emisión</label>
                                <input type="date" name="issue_date" value="{{ old('issue_date', optional($supportDocument->issue_date)->format('Y-m-d')) }}" :disabled="readonly" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3 disabled:opacity-60">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-white/10 p-4">
                    <h3 class="text-sm font-semibold text-gray-100 border-b border-white/10 pb-2 mb-4">Datos de la compra</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Proveedor / vendedor</label>
                            <select name="proveedor_id" x-model="proveedorId" :disabled="readonly" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3 disabled:opacity-60">
                                <option value="">Seleccione</option>
                                @foreach($proveedores as $prov)
                                    <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Forma de pago</label>
                            <select name="payment_status" x-model="paymentStatus" :disabled="readonly" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3 disabled:opacity-60" @change="syncHiddenInputs()">
                                <option value="PAGADO">Contado</option>
                                <option value="PENDIENTE">A crédito (pendiente)</option>
                            </select>
                        </div>
                        <div x-show="paymentStatus === 'PENDIENTE'" x-transition>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Fecha de vencimiento</label>
                            <input type="date" name="due_date" x-model="dueDate" :disabled="readonly" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3 disabled:opacity-60">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Observaciones</label>
                            <textarea name="notes" rows="2" :disabled="readonly" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3 disabled:opacity-60">{{ old('notes', $supportDocument->notes) }}</textarea>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-white/10 p-4">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-sm font-medium text-gray-100">Detalle (productos de inventario)</h3>
                        <button type="button" class="text-sm text-brand hover:underline disabled:opacity-60" :disabled="readonly" @click="openProductSelector()">+ Agregar producto</button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-white/10">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Item</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Descripción</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Cant.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">V. Unit</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">IVA</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">V. Total</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="details.length === 0">
                                    <tr><td colspan="7" class="px-3 py-6 text-sm text-gray-500 text-center">No hay productos agregados.</td></tr>
                                </template>
                                <template x-for="(line, index) in details" :key="index">
                                    <tr>
                                        <td class="px-3 py-3 text-sm text-gray-400" x-text="index + 1"></td>
                                        <td class="px-3 py-3 text-sm text-gray-200" x-text="line.description"></td>
                                        <td class="px-3 py-3"><input type="number" min="0.01" step="0.01" class="w-24 rounded-md border-white/10 bg-white/5 text-gray-100 py-1 px-2 disabled:opacity-60" x-model.number="line.quantity" @input="recomputeLine(index)" :disabled="readonly"></td>
                                        <td class="px-3 py-3"><input type="number" min="0" step="0.01" class="w-32 rounded-md border-white/10 bg-white/5 text-gray-100 py-1 px-2 disabled:opacity-60" x-model.number="line.unit_cost" @input="recomputeLine(index)" :disabled="readonly"></td>
                                        <td class="px-3 py-3"><input type="number" min="0" step="0.01" class="w-24 rounded-md border-white/10 bg-white/5 text-gray-100 py-1 px-2 disabled:opacity-60" x-model.number="line.tax_rate" @input="recomputeLine(index)" :disabled="readonly"></td>
                                        <td class="px-3 py-3 text-sm text-gray-200" x-text="formatMoney(line.line_total)"></td>
                                        <td class="px-3 py-3 text-sm"><button type="button" class="text-red-400 hover:text-red-300 disabled:opacity-60" :disabled="readonly" @click="removeLine(index)">Quitar</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-white/10 p-4" x-show="paymentStatus === 'PAGADO' && isBorrador()" x-transition>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-100">Orígenes de pago (al aprobar)</h3>
                        <button type="button" class="text-sm text-brand hover:underline disabled:opacity-60" :disabled="readonly" @click="addPaymentPart()">+ Agregar origen</button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-white/10">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead class="bg-white/5">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs text-gray-400">Bolsillo</th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-400">Monto</th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-400">Referencia</th>
                                    <th class="px-3 py-2 text-left text-xs text-gray-400">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="paymentParts.length === 0">
                                    <tr><td colspan="4" class="px-3 py-5 text-sm text-gray-500 text-center">No hay orígenes de pago definidos.</td></tr>
                                </template>
                                <template x-for="(part, index) in paymentParts" :key="index">
                                    <tr>
                                        <td class="px-3 py-3">
                                            <select class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-1.5 px-2" x-model="part.bolsillo_id" @change="syncHiddenInputs()">
                                                <option value="">Seleccione bolsillo</option>
                                                @foreach($bolsillos as $bolsillo)
                                                    <option value="{{ $bolsillo->id }}">{{ $bolsillo->name }} ({{ money($bolsillo->saldo, $store->currency ?? 'COP', false) }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-3"><input type="number" min="0" step="0.01" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-1.5 px-2" x-model.number="part.amount" @input="syncHiddenInputs()"></td>
                                        <td class="px-3 py-3"><input type="text" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-1.5 px-2" x-model="part.reference" @input="syncHiddenInputs()"></td>
                                        <td class="px-3 py-3"><button type="button" class="text-red-400 hover:text-red-300" @click="removePaymentPart(index)">Quitar</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-sm">
                        <div class="flex justify-between text-gray-300">
                            <span>Total documento:</span>
                            <span x-text="formatMoney(summary.total)"></span>
                        </div>
                        <div class="flex justify-between" :class="isPaymentBalanced() ? 'text-emerald-300' : 'text-amber-300'">
                            <span>Total orígenes:</span>
                            <span x-text="formatMoney(paymentPartsTotal())"></span>
                        </div>
                    </div>
                </section>

                <div class="rounded-lg border border-white/10 p-4">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-400">
                            <span>Total bruto</span>
                            <span x-text="formatMoney(summary.subtotal)"></span>
                        </div>
                        <div class="flex justify-between text-gray-400">
                            <span>IVA</span>
                            <span x-text="formatMoney(summary.tax_total)"></span>
                        </div>
                        <div class="border-t border-white/10 pt-2 flex justify-between text-gray-100 font-semibold">
                            <span>Total documento</span>
                            <span x-text="'{{ currency_symbol($store->currency ?? 'COP') }} ' + formatMoney(summary.total)"></span>
                        </div>
                    </div>
                </div>

                <div id="support-doc-hidden-inputs-edit"></div>
                <div id="support-doc-hidden-inputs-approve"></div>

                <div class="flex flex-wrap gap-3 justify-end border-t border-white/10 pt-6">
                    <a href="{{ route('stores.product-purchases', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Volver al listado</a>
                    @if($isBorrador)
                        <button type="submit" class="px-4 py-2 rounded-md text-white" :class="canUpdate() ? 'bg-brand hover:opacity-90' : 'bg-gray-600 cursor-not-allowed'" :disabled="!canUpdate()">Actualizar borrador</button>
                    @endif
                </div>
            </form>

            @if($isBorrador)
                @storeCan($store, 'support-documents.approve')
                <form id="approve-dse-form" action="{{ route('stores.product-purchases.documento-soporte.aprobar', [$store, $supportDocument]) }}" method="post" class="mt-4 flex justify-end">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-md text-white" :class="canApprove() ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-600 cursor-not-allowed'" :disabled="!canApprove()">
                        Aprobar documento soporte
                    </button>
                </form>
                @endstoreCan
            @endif
        </div>
    </div>

    <script>
        function supportDocEdit(config = {}) {
            return {
                readonly: !!config.readonly,
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
                paymentParts: Array.isArray(config.initialPaymentParts) ? config.initialPaymentParts.map((part) => ({
                    bolsillo_id: part.bolsillo_id ? String(part.bolsillo_id) : '',
                    amount: Math.max(0, Number(part.amount || 0)),
                    reference: String(part.reference || ''),
                })) : [],
                summary: { subtotal: 0, tax_total: 0, total: 0 },
                init() {
                    this.recomputeAll();
                    this.syncHiddenInputs();
                },
                isBorrador() {
                    return !this.readonly;
                },
                openProductSelector() {
                    if (this.readonly) return;
                    Livewire.dispatch('open-select-item-for-row', { rowId: 'support-doc-line-edit', itemType: 'INVENTARIO' });
                },
                onItemSelected(detail) {
                    if (this.readonly) return;
                    if (!detail || detail.type !== 'INVENTARIO' || detail.rowId !== 'support-doc-line-edit') return;
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
                    if (this.readonly) return;
                    this.details.splice(index, 1);
                    this.recomputeSummary();
                    this.syncHiddenInputs();
                },
                addPaymentPart() {
                    this.paymentParts.push({ bolsillo_id: '', amount: 0, reference: '' });
                    this.syncHiddenInputs();
                },
                removePaymentPart(index) {
                    this.paymentParts.splice(index, 1);
                    this.syncHiddenInputs();
                },
                paymentPartsTotal() {
                    return this.paymentParts.reduce((carry, part) => carry + Math.max(0, Number(part.amount || 0)), 0);
                },
                isPaymentBalanced() {
                    if (this.paymentStatus !== 'PAGADO') return true;
                    return Math.abs(this.paymentPartsTotal() - Number(this.summary.total || 0)) <= 0.01;
                },
                canUpdate() {
                    if (!this.isBorrador()) return false;
                    if (!this.proveedorId) return false;
                    if (this.details.length === 0) return false;
                    if (this.paymentStatus === 'PENDIENTE' && !this.dueDate) return false;
                    return true;
                },
                canApprove() {
                    if (!this.isBorrador()) return false;
                    if (!this.canUpdate()) return false;
                    if (this.paymentStatus !== 'PAGADO') return true;
                    if (this.paymentParts.length === 0) return false;
                    if (!this.isPaymentBalanced()) return false;
                    const hasInvalidPart = this.paymentParts.some((part) => !part.bolsillo_id || Number(part.amount || 0) <= 0);
                    if (hasInvalidPart) return false;
                    return true;
                },
                syncHiddenInputs() {
                    const updateContainer = document.getElementById('support-doc-hidden-inputs-edit');
                    if (updateContainer) {
                        const html = [];
                        this.details.forEach((line, i) => {
                            html.push(`<input type="hidden" name="inventory_items[${i}][product_id]" value="${line.product_id}">`);
                            html.push(`<input type="hidden" name="inventory_items[${i}][description]" value="${String(line.description || '').replace(/"/g, '&quot;')}">`);
                            html.push(`<input type="hidden" name="inventory_items[${i}][quantity]" value="${line.quantity}">`);
                            html.push(`<input type="hidden" name="inventory_items[${i}][unit_cost]" value="${line.unit_cost}">`);
                            html.push(`<input type="hidden" name="inventory_items[${i}][tax_rate]" value="${line.tax_rate}">`);
                        });
                        updateContainer.innerHTML = html.join('');
                    }

                    const approveContainer = document.getElementById('support-doc-hidden-inputs-approve');
                    if (approveContainer) {
                        const htmlApprove = [];
                        this.paymentParts.forEach((part, i) => {
                            htmlApprove.push(`<input type="hidden" form="approve-dse-form" name="payment_parts[${i}][bolsillo_id]" value="${part.bolsillo_id || ''}">`);
                            htmlApprove.push(`<input type="hidden" form="approve-dse-form" name="payment_parts[${i}][amount]" value="${Number(part.amount || 0)}">`);
                            htmlApprove.push(`<input type="hidden" form="approve-dse-form" name="payment_parts[${i}][reference]" value="${String(part.reference || '').replace(/"/g, '&quot;')}">`);
                        });
                        approveContainer.innerHTML = htmlApprove.join('');
                    }
                },
                formatMoney(value) {
                    return Number(value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
</x-app-layout>
