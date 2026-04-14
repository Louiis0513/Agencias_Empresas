<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Compra de productos - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.products', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver a Productos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                <div class="p-4 sm:p-6">
                    @php
                        $fieldClass = 'w-full min-h-[42px] rounded-lg border border-white/15 bg-zinc-950/70 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-500 shadow-sm focus:border-brand/60 focus:outline-none focus:ring-2 focus:ring-brand/25 transition';
                        $labelClass = 'block text-xs font-medium text-gray-400 mb-1.5';
                        $hasActiveFilters = filled(request('status'))
                            || filled(request('payment_status'))
                            || filled(request('proveedor_nombre'))
                            || (($docType ?? request('doc_type', 'all')) !== 'all');
                    @endphp

                    <div class="mb-6 space-y-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-200 mb-3">Filtros</h3>
                            <form method="GET" action="{{ route('stores.product-purchases', $store) }}" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                                    <div class="min-w-0">
                                        <label for="filter_doc_type" class="{{ $labelClass }}">Tipo de documento</label>
                                        <select id="filter_doc_type" name="doc_type" class="{{ $fieldClass }}">
                                            <option value="all" {{ ($docType ?? 'all') === 'all' ? 'selected' : '' }}>Todos</option>
                                            <option value="purchases" {{ ($docType ?? 'all') === 'purchases' ? 'selected' : '' }}>Factura compras</option>
                                            <option value="support_documents" {{ ($docType ?? 'all') === 'support_documents' ? 'selected' : '' }}>Documento soporte</option>
                                        </select>
                                    </div>
                                    <div class="min-w-0">
                                        <label for="filter_status" class="{{ $labelClass }}">Estado</label>
                                        <select id="filter_status" name="status" class="{{ $fieldClass }}">
                                            <option value="">Todos los estados</option>
                                            <option value="BORRADOR" {{ request('status') == 'BORRADOR' ? 'selected' : '' }}>Borrador</option>
                                            <option value="APROBADO" {{ request('status') == 'APROBADO' ? 'selected' : '' }}>Aprobado</option>
                                            <option value="ANULADO" {{ request('status') == 'ANULADO' ? 'selected' : '' }}>Anulado</option>
                                        </select>
                                    </div>
                                    <div class="min-w-0">
                                        <label for="filter_payment_status" class="{{ $labelClass }}">Pago</label>
                                        <select id="filter_payment_status" name="payment_status" class="{{ $fieldClass }}">
                                            <option value="">Todos los pagos</option>
                                            <option value="PAGADO" {{ request('payment_status') == 'PAGADO' ? 'selected' : '' }}>Pagado</option>
                                            <option value="PENDIENTE" {{ request('payment_status') == 'PENDIENTE' ? 'selected' : '' }}>Pendiente</option>
                                        </select>
                                    </div>
                                    <div class="min-w-0 sm:col-span-2 xl:col-span-1">
                                        <label for="filter_proveedor" class="{{ $labelClass }}">Proveedor</label>
                                        <input id="filter_proveedor" type="text"
                                               name="proveedor_nombre"
                                               value="{{ request('proveedor_nombre') }}"
                                               placeholder="Nombre o parte del nombre…"
                                               class="{{ $fieldClass }}">
                                    </div>
                                </div>
                                <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3">
                                    <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-[42px] px-5 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/40 transition">
                                        <svg class="w-4 h-4 shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                        </svg>
                                        Aplicar filtros
                                    </button>
                                    @if($hasActiveFilters)
                                        <a href="{{ route('stores.product-purchases', $store) }}" class="inline-flex items-center justify-center min-h-[42px] px-4 rounded-lg border border-white/15 text-sm text-gray-300 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-white/20 transition">
                                            Limpiar
                                        </a>
                                    @endif
                                </div>
                            </form>
                        </div>

                        <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4 sm:p-5">
                            <h3 class="text-sm font-semibold text-gray-200 mb-3">Acciones</h3>
                            <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3">
                                @storeCan($store, 'product-purchases.create')
                                <a href="{{ route('stores.product-purchases.create', $store) }}"
                                   class="inline-flex items-center justify-center gap-2 min-h-[42px] px-4 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brand/90 focus:outline-none focus:ring-2 focus:ring-brand/40 sm:flex-1 sm:min-w-[200px] lg:flex-none lg:min-w-0 transition">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Nueva compra
                                </a>
                                @endstoreCan
                                @storeCan($store, 'support-documents.view')
                                <a href="{{ route('stores.product-purchases.documento-soporte.create', $store) }}"
                                   class="inline-flex items-center justify-center gap-2 min-h-[42px] px-4 rounded-lg border border-white/20 bg-white/[0.04] text-gray-100 text-sm font-medium hover:bg-white/[0.08] focus:outline-none focus:ring-2 focus:ring-white/20 sm:flex-1 sm:min-w-[200px] lg:flex-none lg:min-w-0 transition">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Nuevo documento soporte
                                </a>
                                @endstoreCan
                            </div>
                        </div>
                    </div>

                    @if($bandejaRows->count() > 0)
                        <div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 touch-pan-x">
                            <table class="min-w-[720px] w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Tipo</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Proveedor</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Total</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Pago</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($bandejaRows as $row)
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">
                                                <span title="{{ $row->number_label }}">{{ $row->id }}</span>
                                                <span class="block text-xs text-gray-500 font-normal">{{ $row->number_label }}</span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($row->source === 'purchase')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Factura compras</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Documento soporte</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $row->created_at instanceof \DateTimeInterface ? $row->created_at->format('d/m/Y') : \Carbon\Carbon::parse($row->created_at)->format('d/m/Y') }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-100">{{ $row->proveedor_nombre ?? '-' }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-100">{{ money($row->total, $store->currency ?? 'COP') }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($row->status == 'BORRADOR')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Borrador</span>
                                                @elseif($row->status == 'APROBADO')
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Aprobado</span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Anulado</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                @if($row->source === 'purchase')
                                                    @if($row->payment_type == 'CONTADO')
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Contado</span>
                                                    @elseif($row->payment_status == 'PAGADO')
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" title="Crédito pagado en abonos">Crédito (Pagado)</span>
                                                    @else
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Crédito (Pendiente)</span>
                                                    @endif
                                                @else
                                                    @if($row->payment_status == 'PENDIENTE')
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Pendiente</span>
                                                    @elseif($row->payment_status == 'PAGADO' && $row->status == 'BORRADOR')
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200" title="Se ejecutará al aprobar el documento">Contado</span>
                                                    @elseif($row->payment_status == 'PAGADO' && $row->status == 'APROBADO')
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Pagado</span>
                                                    @else
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">—</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ $row->show_url }}" class="text-brand hover:text-white transition mr-3">Ver</a>
                                                @if($row->source === 'support_document')
                                                    @storeCan($store, 'support-documents.print')
                                                    <a href="{{ route('stores.product-purchases.documento-soporte.print', ['store' => $store, 'supportDocument' => $row->id]) }}" target="_blank" rel="noopener" class="text-brand hover:text-white transition mr-3">Imprimir</a>
                                                    @endstoreCan
                                                @endif
                                                @if(!empty($row->edit_url))
                                                    @if($row->source === 'support_document')
                                                        @storeCan($store, 'support-documents.edit')
                                                        <a href="{{ $row->edit_url }}" class="text-brand hover:text-white transition mr-3">Editar</a>
                                                        @endstoreCan
                                                    @else
                                                        @storeCan($store, 'product-purchases.create')
                                                        <a href="{{ $row->edit_url }}" class="text-brand hover:text-white transition mr-3">Editar</a>
                                                        @endstoreCan
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $bandejaRows->links() }}</div>
                    @else
                        <p class="text-gray-400 text-center py-8">No hay registros para los filtros seleccionados.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
