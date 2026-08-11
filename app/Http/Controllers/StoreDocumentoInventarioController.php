<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaldosInicialesRequest;
use App\Models\DocumentoInventario;
use App\Models\Store;
use App\Services\DocumentoInventarioService;
use App\Services\StorePermissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;

class StoreDocumentoInventarioController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected DocumentoInventarioService $documentoInventarioService,
    ) {}

    public function storeSaldosIniciales(StoreSaldosInicialesRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'products.create');

        try {
            $documento = $this->documentoInventarioService->contabilizarSaldosIniciales(
                $store,
                (int) $request->user()->id,
                $request->validated()
            );
        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Saldos iniciales contabilizados.',
                'redirect' => route('stores.products.documentos.show', [$store, $documento]),
                'documento' => [
                    'id' => $documento->id,
                    'numero' => $documento->numero,
                ],
            ]);
        }

        return redirect()
            ->route('stores.products.documentos.show', [$store, $documento])
            ->with('success', 'Documento '.$documento->numero.' contabilizado.');
    }

    public function show(Store $store, DocumentoInventario $documentoInventario)
    {
        $this->permissionService->authorize($store, 'products.view');
        $vista = $this->documentoInventarioService->datosVistaPdf($store, $documentoInventario);

        $logoUrl = null;
        if (filled($store->logo_path)) {
            $logoUrl = asset('storage/'.$store->logo_path);
        }

        return view('stores.productos.documentos.show', array_merge($vista, [
            'logoUrl' => $logoUrl,
        ]));
    }

    public function pdf(Store $store, DocumentoInventario $documentoInventario)
    {
        $this->permissionService->authorize($store, 'products.view');
        $vista = $this->documentoInventarioService->datosVistaPdf($store, $documentoInventario);

        $pdf = Pdf::loadView('stores.productos.documentos.pdf', $vista);
        $pdf->setPaper('a4', 'portrait');

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', $vista['documento']->numero);

        return $pdf->stream('documento-inventario-'.$safeNumber.'.pdf');
    }

    public function contabilizacion(Store $store, DocumentoInventario $documentoInventario)
    {
        $this->permissionService->authorize($store, 'products.view');
        $documento = $this->documentoInventarioService->obtener($store, $documentoInventario);

        return view('stores.productos.documentos.contabilizacion', compact('store', 'documento'));
    }

    public function contabilizacionExcel(Store $store, DocumentoInventario $documentoInventario)
    {
        $this->permissionService->authorize($store, 'products.view');

        return $this->documentoInventarioService->exportContabilizacionExcel($store, $documentoInventario);
    }
}
