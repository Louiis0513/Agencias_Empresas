<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Store;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\StorePermissionService;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return view('stores.facturas', compact('store', 'invoices', 'customers', 'rangoFechas', 'bolsillos'));
    }

    public function show(Store $store, Invoice $invoice, InvoiceService $invoiceService, StorePermissionService $permission)
    {
        $permission->authorize($store, 'invoices.view');

        if ($invoice->store_id !== $store->id) {
            abort(404);
        }

        $invoice = $invoiceService->obtenerFactura($store, $invoice->id);

        return view('stores.factura-detalle', compact('store', 'invoice'));
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
}
