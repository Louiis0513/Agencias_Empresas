<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\AnalizadorPagos;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreInvoiceAnalysisController extends Controller
{
    public function process(Request $request, Store $store, StorePermissionService $permission, AnalizadorPagos $analizadorPagos)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $permission->authorize($store, 'reports.billing.view');

        $wantsJson = $request->ajax()
            || $request->wantsJson()
            || $request->expectsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';

        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:51200',
            ], [
                'excel_file.required' => 'Debes seleccionar un archivo Excel.',
                'excel_file.mimes' => 'El archivo debe ser un Excel (.xlsx o .xls).',
                'excel_file.max' => 'El archivo no puede ser mayor a 50MB.',
            ]);
        } catch (ValidationException $e) {
            if ($wantsJson) {
                throw $e;
            }

            return redirect()
                ->route('stores.reports.index', ['store' => $store, 'tab' => 'facturacion'])
                ->withErrors($e->errors());
        }

        Log::info('[StoreInvoiceAnalysis] Procesando archivo', [
            'user_id' => Auth::id(),
            'store_id' => $store->id,
        ]);

        try {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300);
            ini_set('max_input_time', 300);
            set_time_limit(300);

            if (ob_get_level()) {
                ob_end_clean();
            }

            $response = $analizadorPagos->procesarArchivo($request->file('excel_file'));

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
            $response->headers->set('Content-Disposition', 'attachment; filename="InformeDeAnalizador.xlsx"', true);
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate', true);
            $response->headers->set('Pragma', 'no-cache', true);
            $response->headers->set('Expires', '0', true);

            return $response;
        } catch (Throwable $e) {
            Log::error('[StoreInvoiceAnalysis] Error al procesar', [
                'message' => $e->getMessage(),
                'store_id' => $store->id,
            ]);

            if ($wantsJson) {
                return response()->json([
                    'message' => 'Error al procesar el archivo: '.$e->getMessage(),
                ], 500);
            }

            return redirect()
                ->route('stores.reports.index', ['store' => $store, 'tab' => 'facturacion'])
                ->with('error', 'Error al procesar el archivo: '.$e->getMessage());
        }
    }
}
