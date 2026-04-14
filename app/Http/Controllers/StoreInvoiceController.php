<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Store;
use App\Services\CustomerService;
use App\Services\InvoiceExcelExportService;
use App\Services\InvoiceService;
use App\Services\StorePermissionService;
use App\Services\VentaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Picqer\Barcode\BarcodeGeneratorPNG;

class StoreInvoiceController extends Controller
{
    public function index(Store $store, InvoiceService $invoiceService, Request $request, StorePermissionService $permission, CustomerService $customerService)
    {
        $permission->authorize($store, 'invoices.view');

        $rangoFechas = $invoiceService->getRangoFechasPorDefecto();

        $filtros = [
            'status' => $request->get('status'),
            'customer_id' => $request->get('customer_id'),
            'payment_method' => $request->get('payment_method'),
            'bolsillo_id' => $request->get('bolsillo_id'),
            'search' => $request->get('search'),
            'fecha_desde' => $request->get('fecha_desde', $rangoFechas['fecha_desde']->format('Y-m-d')),
            'fecha_hasta' => $request->get('fecha_hasta', $rangoFechas['fecha_hasta']->format('Y-m-d')),
            'per_page' => $request->get('per_page', 10),
        ];

        $invoices = $invoiceService->listarFacturas($store, $filtros);
        $customers = $customerService->getAllStoreCustomers($store);
        $bolsillos = $store->bolsillos()->activos()->orderBy('name')->get();

        return view('stores.factura.facturas', compact('store', 'invoices', 'customers', 'rangoFechas', 'bolsillos'));
    }

    public function exportExcel(Store $store, Request $request, InvoiceService $invoiceService, InvoiceExcelExportService $excelExport, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'invoices.view');

        $rangoFechas = $invoiceService->getRangoFechasPorDefecto();

        $filtros = [
            'status' => $request->get('status'),
            'customer_id' => $request->get('customer_id'),
            'payment_method' => $request->get('payment_method'),
            'bolsillo_id' => $request->get('bolsillo_id'),
            'search' => $request->get('search'),
            'fecha_desde' => $request->get('fecha_desde', $rangoFechas['fecha_desde']->format('Y-m-d')),
            'fecha_hasta' => $request->get('fecha_hasta', $rangoFechas['fecha_hasta']->format('Y-m-d')),
        ];

        $invoices = $invoiceService->listarFacturasParaExportacion($store, $filtros);

        return $excelExport->downloadList($store, $invoices);
    }

    public function show(Store $store, Invoice $invoice, InvoiceService $invoiceService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'invoices.view');

        if ($invoice->store_id !== $store->id) {
            abort(404);
        }

        $invoice = $invoiceService->obtenerFactura($store, $invoice->id);

        return view('stores.factura.factura-detalle', compact('store', 'invoice'));
    }

    public function store(Store $store, StoreInvoiceRequest $request, VentaService $ventaService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'invoices.create');

        try {
            $ventaService->registrarVenta($store, Auth::id(), $request->validated());
            return redirect()->route('stores.invoices', $store)
                ->with('success', 'Factura creada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.invoices', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function void(Store $store, Invoice $invoice, InvoiceService $invoiceService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'invoices.void');

        if ($invoice->store_id !== $store->id) {
            abort(404);
        }

        try {
            $invoiceService->anularFactura($store, $invoice);
            return redirect()->route('stores.invoices', $store)
                ->with('success', 'Factura anulada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.invoices', $store)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Imprime la factura en formato tira térmica (recibo de supermercado).
     * Solo facturas PAID (venta directa pagada).
     */
    public function printReceipt(Store $store, Invoice $invoice, InvoiceService $invoiceService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'invoices.view');

        if ($invoice->store_id !== $store->id) {
            abort(404);
        }

        if ($invoice->status !== 'PAID') {
            abort(403, 'Solo se puede imprimir facturas pagadas.');
        }

        $invoice = $invoiceService->obtenerFactura($store, $invoice->id);

        $barcodeBase64 = null;
        try {
            $generator = new BarcodeGeneratorPNG();
            $barcodePng = $generator->getBarcode((string) $invoice->id, $generator::TYPE_CODE_128, 2, 40);
            $barcodeBase64 = 'data:image/png;base64,' . base64_encode($barcodePng);
        } catch (\Throwable $e) {
            // Si falla el barcode, continuar sin él
        }

        $pdf = Pdf::loadView('invoices.receipt-tira', [
            'invoice' => $invoice,
            'store' => $store,
            'barcodeBase64' => $barcodeBase64,
        ]);

        // Altura dinámica: desde nombre tienda hasta *id* del barcode, sin sobrante de papel
        // No usar size en @page para evitar conflicto con setPaper
        $baseHeightMm = 69; // encabezado + datos + cabecera tabla + totales + pie + barcode
        $itemsHeightMm = 0;
        foreach ($invoice->details as $detail) {
            $desc = $detail->receipt_description ?? $detail->product_name ?? '';
            $charsPerLine = 18; // ~18 caracteres caben en 20mm
            $lines = max(1, (int) ceil(mb_strlen($desc) / $charsPerLine));
            $itemsHeightMm += max(3, $lines * 3); // ~3mm por fila, más si descripción larga
        }
        $totalHeightMm = $baseHeightMm + $itemsHeightMm;
        $heightPt = round($totalHeightMm * 2.83465, 1); // mm a pt, redondeado para precisión

        $pdf->setPaper([0, 0, 164.4, $heightPt], 'portrait');

        return $pdf->stream('factura-' . $invoice->id . '.pdf');
    }
}
