<div>
    <x-modal name="select-account-payable" focusable maxWidth="6xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Seleccionar cuenta por pagar
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @if($forComprobante && count($excludeIds) > 0)
                    Facturas pendientes del proveedor seleccionado. Haz clic en "Seleccionar" para agregar cada factura.
                @else
                    Busca por Compra #, número de factura o nombre del proveedor. Si no recuerdas el proveedor, busca la factura directamente.
                @endif
            </p>

            <div class="mt-4">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Buscar por Compra #, factura o proveedor (ej: 123, Acme)..."
                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Proveedor</label>
                    @if($forComprobante && count($excludeIds) > 0)
                        @php $prov = $proveedorId ? $this->proveedores->firstWhere('id', $proveedorId) : null; @endphp
                        <div class="px-3 py-2 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm font-medium">
                            {{ $prov?->nombre ?? 'Sin proveedor' }}
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">Solo facturas de este proveedor</p>
                    @else
                        <select wire:model.live="proveedorId"
                                class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                            <option value="">Todos</option>
                            @foreach($this->proveedores as $prov)
                                <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Vencimiento desde</label>
                    <input type="date" wire:model.live="fechaVencimientoDesde"
                           class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Vencimiento hasta</label>
                    <input type="date" wire:model.live="fechaVencimientoHasta"
                           class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Estado</label>
                    <select wire:model.live="status"
                            class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                        <option value="pendientes">Pendientes</option>
                        <option value="PENDIENTE">Pendiente</option>
                        <option value="PARCIAL">Parcial</option>
                        <option value="PAGADO">Pagado</option>
                        <option value="">Todos</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 overflow-auto max-h-96 border border-gray-200 dark:border-gray-600 rounded-md">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Compra</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Proveedor</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Saldo</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vencimiento</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->cuentasPorPagar as $ap)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">#{{ $ap->purchase->id }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $ap->purchase->proveedor?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($ap->total_amount, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($ap->balance, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $ap->due_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm">
                                    @if($ap->status == 'PENDIENTE')
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                    @elseif($ap->status == 'PARCIAL')
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Parcial</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Pagado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button"
                                            wire:click="selectAccountPayable({{ $ap->id }}, {{ $ap->purchase->id }}, {{ $ap->purchase->proveedor_id ?? 'null' }}, @js($ap->purchase->proveedor?->nombre ?? ''), {{ $ap->total_amount }}, {{ $ap->balance }}, @js($ap->due_date?->format('Y-m-d')), @js($ap->status))"
                                            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                                        Seleccionar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No hay cuentas por pagar con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-between items-center">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->cuentasPorPagar->links() }}
                </div>
                <button type="button"
                        x-on:click="$dispatch('close-modal', 'select-account-payable')"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                    Cancelar
                </button>
            </div>
        </div>
    </x-modal>
</div>
