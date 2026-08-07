@php
    $perm = app(\App\Services\StorePermissionService::class);
    $canExportInvoicesExcel = $perm->can($store, 'invoices.view');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">
                    Informes de facturación
                </h2>
                <p class="text-sm text-gray-400 mt-1">
                    Análisis y exportación de facturas.
                </p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if($canExportInvoicesExcel)
                    <a href="{{ route('stores.invoices.export-excel', $store) }}" class="inline-flex items-center justify-center px-3 py-2 rounded-lg border border-brand/30 bg-brand/20 text-brand text-sm font-medium hover:bg-brand/30 transition">
                        Exportar excel Facturas
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-dark-card border border-white/5 rounded-2xl p-8">
                <p class="text-gray-400 text-sm">
                    Usa el botón de arriba para exportar facturas.
                    El informe de productos fue retirado junto con el inventario legado.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
