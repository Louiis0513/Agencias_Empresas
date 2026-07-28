<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTipoComprobanteRequest;
use App\Models\Store;
use App\Models\TipoComprobante;
use App\Services\StorePermissionService;
use App\Services\TipoComprobanteService;
use Exception;
use Illuminate\Http\Request;

class StoreTipoComprobanteController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected TipoComprobanteService $tipoComprobanteService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.tipos.view');

        $this->tipoComprobanteService->asegurarTiposPorDefecto($store);

        $tipos = $this->tipoComprobanteService->listar($store, [
            'search' => $request->get('search'),
            'familia' => $request->get('familia'),
            'activo' => $request->get('activo'),
        ]);

        $familias = TipoComprobante::etiquetasFamilias();
        $codigoSugerido = $this->tipoComprobanteService->sugerirCodigo(
            $store,
            old('familia', TipoComprobante::FAMILIA_FV)
        );

        return view('stores.contabilidad.tipos-comprobante', compact(
            'store',
            'tipos',
            'familias',
            'codigoSugerido'
        ));
    }

    public function store(StoreTipoComprobanteRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.tipos.create');

        try {
            $tipo = $this->tipoComprobanteService->crear($store, $request->validated());

            return redirect()
                ->route('stores.contabilidad.tipos', $store)
                ->with('success', 'Tipo «'.$tipo->familia.'-'.$tipo->codigo.'» ('.$tipo->nombre.') creado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.tipos', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(StoreTipoComprobanteRequest $request, Store $store, TipoComprobante $tipoComprobante)
    {
        $this->permissionService->authorize($store, 'contabilidad.tipos.edit');

        if ($tipoComprobante->store_id !== $store->id) {
            abort(404);
        }

        try {
            $this->tipoComprobanteService->actualizar($store, $tipoComprobante, $request->validated());

            return redirect()
                ->route('stores.contabilidad.tipos', $store)
                ->with('success', 'Tipo de comprobante actualizado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.tipos', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
