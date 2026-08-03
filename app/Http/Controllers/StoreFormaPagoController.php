<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormaPagoRequest;
use App\Models\FormaPago;
use App\Models\Store;
use App\Services\FormaPagoService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreFormaPagoController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected FormaPagoService $formaPagoService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.formas-pago.view');

        $defaults = $this->formaPagoService->asegurarDefaults($store);

        if ($defaults['creadas'] > 0) {
            session()->flash(
                'success',
                'Se crearon '.$defaults['creadas'].' forma(s) de pago por defecto.'
            );
        }

        if ($defaults['errores'] !== []) {
            session()->flash(
                'warning',
                'No se pudieron crear todas las formas por defecto: '.implode(' · ', $defaults['errores'])
            );
        }

        return view('stores.contabilidad.formas-pago', [
            'store' => $store,
            'formasPago' => $this->formaPagoService->listar($store, [
                'search' => $request->get('search'),
                'aplica_a' => $request->get('aplica_a'),
                'en_uso' => $request->get('en_uso'),
                'es_pago_en_linea' => $request->filled('cuenta_contable_id')
                    ? null
                    : ($request->get('tab') === 'linea' ? '1' : '0'),
                'cuenta_contable_id' => $request->get('cuenta_contable_id'),
            ]),
            'aplicaAOptions' => FormaPago::APLICA_A_LABELS,
            'mediosDian' => FormaPago::MEDIOS_PAGO_DIAN,
            'cuentas' => $this->formaPagoService->cuentasDisponibles($store),
            'codigoSugerido' => $this->formaPagoService->siguienteCodigo($store),
            'tabLinea' => $request->get('tab') === 'linea',
        ]);
    }

    public function store(StoreFormaPagoRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.formas-pago.create');

        try {
            $forma = $this->formaPagoService->crear($store, $request->validated());

            return redirect()
                ->route('stores.contabilidad.formas-pago', $store)
                ->with('success', 'Forma de pago «'.$forma->codigo.' — '.$forma->nombre.'» creada.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.formas-pago', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(StoreFormaPagoRequest $request, Store $store, FormaPago $formaPago)
    {
        $this->permissionService->authorize($store, 'contabilidad.formas-pago.edit');

        if ($formaPago->store_id !== $store->id) {
            abort(404);
        }

        try {
            $this->formaPagoService->actualizar($store, $formaPago, $request->validated());

            return redirect()
                ->route('stores.contabilidad.formas-pago', $store)
                ->with('success', 'Forma de pago actualizada.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.formas-pago', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
