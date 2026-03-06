<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Compra #{{ $purchase->id }} - {{ $store->name }}
            </h2>
            @if($purchase->isProducto())
                <a href="{{ route('stores.product-purchases', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    ← Volver a Compras de Productos
                </a>
            @else
                <a href="{{ route('stores.purchases', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                    ← Volver a Compras
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-400">Proveedor</p>
                            <p class="text-lg font-semibold text-gray-100">{{ $purchase->proveedor?->nombre ?? 'Sin proveedor' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Estado</p>
                            <p class="text-lg font-semibold">
                                @if($purchase->status == 'BORRADOR')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Borrador</span>
                                @elseif($purchase->status == 'APROBADO')
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aprobado</span>
                                @else
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Anulado</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Forma de Pago</p>
                            <p class="text-lg font-semibold text-gray-100">
                                @if($purchase->payment_type == 'CONTADO')
                                    Contado
                                @elseif($purchase->payment_status == 'PAGADO')
                                    Crédito (Pagado)
                                @else
                                    Crédito (Pendiente)
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Factura Externa</p>
                            <p class="text-lg font-semibold text-gray-100">{{ $purchase->invoice_number ?? '-' }}</p>
                            @if($purchase->invoice_date)
                                <p class="text-sm text-gray-500">{{ $purchase->invoice_date->format('d/m/Y') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mb-6 overflow-x-auto">
                        <h3 class="text-sm font-medium text-gray-100 mb-2">Detalle de la Compra</h3>
                        <table class="min-w-full divide-y divide-white/5">
                            <thead class="border-b border-white/5">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Descripción</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Tipo</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Cantidad</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Costo Unit.</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($purchase->details as $d)
                                    <tr>
                                        <td class="px-3 py-2 text-sm text-gray-100">{{ $d->description }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-100">{{ $d->item_type == 'INVENTARIO' ? 'Inventario' : 'Activo Fijo' }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-100">{{ $d->quantity }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-100">{{ money($d->unit_cost, $store->currency ?? 'COP', false) }}</td>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-100">{{ money($d->subtotal, $store->currency ?? 'COP', false) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-2 text-right">
                            <p class="text-lg font-bold text-gray-100">Total: {{ money($purchase->total, $store->currency ?? 'COP') }}</p>
                        </div>
                    </div>

                    @php
                        $detallesSerializados = $purchase->details->filter(fn($d) => $d->isActivoFijo());
                    @endphp

                    @if($purchase->isBorrador())
                        <div class="flex flex-col gap-4">
                            <div class="flex gap-3">
                                <a href="{{ $purchase->purchase_type === \App\Models\Purchase::TYPE_PRODUCTO ? route('stores.product-purchases.edit', [$store, $purchase]) : route('stores.purchases.edit', [$store, $purchase]) }}" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">Editar</a>
                                @if($purchase->payment_status == 'PAGADO' && $bolsillos && $bolsillos->isNotEmpty())
                                    <button type="button" id="btn-show-pago-contado" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Aprobar Compra (Contado)</button>
                                @elseif($purchase->payment_status == 'PAGADO' && (!$bolsillos || $bolsillos->isEmpty()))
                                    <span class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400 rounded-md cursor-not-allowed" title="Crea al menos un bolsillo en Caja para poder aprobar compras de contado">Aprobar (sin bolsillos)</span>
                                @elseif($purchase->payment_status == 'PENDIENTE')
                                    <form method="POST" action="{{ route('stores.purchases.approve', [$store, $purchase]) }}" class="inline" id="form-aprobar-credito">
                                        @csrf
                                        @if($detallesSerializados->isNotEmpty())
                                            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg mb-3">
                                                <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-2">Activos serializados: indica los números de serie</h3>
                                                <p class="text-xs text-amber-700 dark:text-amber-300 mb-3">Cada unidad debe tener un serial único.</p>
                                                <div class="space-y-3">
                                                    @foreach($detallesSerializados as $d)
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $d->description }} ({{ $d->quantity }}):</span>
                                                            @for($i = 0; $i < $d->quantity; $i++)
                                                                <input type="text" name="serials[{{ $d->id }}][]" placeholder="Serial {{ $i + 1 }}" required
                                                                       class="w-40 rounded-md border-white/10 bg-white/5 text-gray-100 text-sm">
                                                            @endfor
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700" onclick="return confirm('{{ $purchase->purchase_type === \App\Models\Purchase::TYPE_PRODUCTO ? "¿Aprobar esta compra? Los productos sumarán al stock de inventario." : "¿Aprobar esta compra? Los productos tipo Inventario sumarán al stock. Los Activos Fijos sumarán al módulo Activos." }}');">Aprobar Compra</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('stores.purchases.void', [$store, $purchase]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700" onclick="return confirm('¿Anular esta compra?');">Anular</button>
                                </form>
                            </div>

                            @if($purchase->payment_status == 'PAGADO' && $bolsillos)
                                <div id="form-pago-contado" class="hidden p-4 border-b border-white/5 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <h3 class="text-sm font-medium text-gray-100 mb-3">Registrar pago de contado (se descontará de caja)</h3>
                                    <form method="POST" action="{{ route('stores.purchases.approve', [$store, $purchase]) }}">
                                        @csrf
                                        @if($detallesSerializados->isNotEmpty())
                                            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg mb-4">
                                                <h4 class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-2">Activos serializados: indica los números de serie</h4>
                                                <div class="space-y-3">
                                                    @foreach($detallesSerializados as $d)
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $d->description }} ({{ $d->quantity }}):</span>
                                                            @for($i = 0; $i < $d->quantity; $i++)
                                                                <input type="text" name="serials[{{ $d->id }}][]" placeholder="Serial {{ $i + 1 }}" required
                                                                       class="w-40 rounded-md border-white/10 bg-white/5 text-gray-100 text-sm">
                                                            @endfor
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha del Pago</label>
                                                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" required>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                                                <input type="text" name="notes" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" placeholder="Opcional">
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">De qué bolsillo(s) se paga (Total: {{ money($purchase->total, $store->currency ?? 'COP', false) }})</label>
                                            <div id="payment-parts">
                                                <div class="flex gap-2 mb-2">
                                                    <select name="parts[0][bolsillo_id]" class="flex-1 rounded-md border-white/10 bg-white/5 text-gray-100" required>
                                                        <option value="">Seleccionar bolsillo</option>
                                                        @foreach($bolsillos as $b)
                                                            <option value="{{ $b->id }}">{{ $b->name }} ({{ money($b->saldo, $store->currency ?? 'COP', false) }})</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="number" name="parts[0][amount]" step="0.01" min="0.01" placeholder="Monto" value="{{ $purchase->total }}" class="w-32 rounded-md border-white/10 bg-white/5 text-gray-100" required>
                                                </div>
                                            </div>
                                            <button type="button" id="add-part" class="text-sm text-indigo-600 hover:text-indigo-800">+ Agregar otro bolsillo</button>
                                        </div>
                                        <div class="flex gap-3">
                                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Aprobar y Registrar Pago</button>
                                            <button type="button" id="btn-cancel-pago" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($purchase->accountPayable)
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            @if($purchase->accountPayable->isPagado())
                                <p class="text-sm text-gray-400 mb-2">Cuenta por pagar: Pagada</p>
                                <a href="{{ route('stores.accounts-payables.show', [$store, $purchase->accountPayable]) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Ver historial de pagos en Cuentas por Pagar →</a>
                            @else
                                <p class="text-sm text-gray-400 mb-2">Cuenta por pagar: Saldo pendiente {{ money($purchase->accountPayable->balance, $store->currency ?? 'COP', false) }}</p>
                                <a href="{{ route('stores.accounts-payables.show', [$store, $purchase->accountPayable]) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Ir a Cuentas por Pagar →</a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($purchase->isBorrador() && $purchase->payment_status == 'PAGADO' && $bolsillos && $bolsillos->isNotEmpty())
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnShow = document.getElementById('btn-show-pago-contado');
            const formPago = document.getElementById('form-pago-contado');
            const btnCancel = document.getElementById('btn-cancel-pago');
            const addPart = document.getElementById('add-part');
            const container = document.getElementById('payment-parts');
            const totalCompra = {{ $purchase->total }};
            const bolsillos = @json($bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'saldo' => (float)$b->saldo]));

            if (btnShow && formPago) {
                btnShow.addEventListener('click', function() {
                    formPago.classList.remove('hidden');
                    btnShow.classList.add('hidden');
                });
            }
            if (btnCancel && formPago && btnShow) {
                btnCancel.addEventListener('click', function() {
                    formPago.classList.add('hidden');
                    btnShow.classList.remove('hidden');
                });
            }
            if (addPart && container && bolsillos.length) {
                let partIndex = 1;
                addPart.addEventListener('click', function() {
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 mb-2';
                    div.innerHTML = `
                        <select name="parts[${partIndex}][bolsillo_id]" class="flex-1 rounded-md border-white/10 bg-white/5 text-gray-100" required>
                            <option value="">Seleccionar bolsillo</option>
                            ${bolsillos.map(b => `<option value="${b.id}">${b.name} (${b.saldo.toFixed(2)})</option>`).join('')}
                        </select>
                        <input type="number" name="parts[${partIndex}][amount]" step="0.01" min="0.01" placeholder="Monto" class="w-32 rounded-md border-white/10 bg-white/5 text-gray-100" required>
                    `;
                    container.appendChild(div);
                    partIndex++;
                });
            }
        });
    </script>
    @endif
</x-app-layout>
