<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReciboCajaTipoRequest;
use App\Models\Store;
use App\Models\TipoComprobante;
use App\Services\StorePermissionService;
use App\Services\TipoComprobanteService;
use Exception;
use Illuminate\Http\Request;

class StoreReciboCajaTipoController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected TipoComprobanteService $tipoComprobanteService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.tipos.view');

        $defaults = $this->tipoComprobanteService->asegurarTiposPorDefecto($store);
        $creadosRc = count(array_filter(
            $defaults['creadas'],
            fn (string $clave) => str_starts_with($clave, TipoComprobante::FAMILIA_RC.':')
        ));

        if ($creadosRc > 0) {
            session()->flash(
                'success',
                'Se creó el tipo de recibo de caja por defecto (RC-1).'
            );
        }

        if ($defaults['errores'] !== []) {
            session()->flash(
                'error',
                'Algunos tipos por defecto no se pudieron crear: '.implode(' | ', $defaults['errores'])
            );
        }

        $tipos = $this->tipoComprobanteService->listar($store, [
            'search' => $request->get('search'),
            'familia' => TipoComprobante::FAMILIA_RC,
            'activo' => $request->get('activo'),
        ], 50);

        $codigoSugerido = $this->tipoComprobanteService->sugerirCodigo(
            $store,
            TipoComprobante::FAMILIA_RC
        );

        $cuentasAnticipos = $this->tipoComprobanteService->cuentasDisponiblesAnticipos($store);

        return view('stores.contabilidad.recibos-caja', compact(
            'store',
            'tipos',
            'codigoSugerido',
            'cuentasAnticipos'
        ));
    }

    public function store(StoreReciboCajaTipoRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.tipos.create');

        try {
            $data = $request->validated();
            $data['familia'] = TipoComprobante::FAMILIA_RC;
            $data['prefijo'] = $data['prefijo'] ?? 'RC';
            if (empty($data['nombre']) && ! empty($data['titulo'])) {
                $data['nombre'] = $data['titulo'];
            }

            $tipo = $this->tipoComprobanteService->crear($store, $data);

            return redirect()
                ->route('stores.contabilidad.recibos-caja', $store)
                ->with('success', 'Tipo «RC-'.$tipo->codigo.'» ('.$tipo->titulo.') creado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.recibos-caja', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(StoreReciboCajaTipoRequest $request, Store $store, TipoComprobante $tipoComprobante)
    {
        $this->permissionService->authorize($store, 'contabilidad.tipos.edit');

        if ($tipoComprobante->store_id !== $store->id
            || $tipoComprobante->familia !== TipoComprobante::FAMILIA_RC) {
            abort(404);
        }

        try {
            $data = $request->validated();
            $data['familia'] = TipoComprobante::FAMILIA_RC;
            $data['prefijo'] = $data['prefijo'] ?? 'RC';
            if (empty($data['nombre']) && ! empty($data['titulo'])) {
                $data['nombre'] = $data['titulo'];
            }
            // Asegurar que se procese incluso si viene null (limpiar cuenta).
            if (! array_key_exists('cuenta_anticipos_id', $data)) {
                $data['cuenta_anticipos_id'] = null;
            }

            $this->tipoComprobanteService->actualizar($store, $tipoComprobante, $data);

            return redirect()
                ->route('stores.contabilidad.recibos-caja', $store)
                ->with('success', 'Recibo de caja actualizado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.recibos-caja', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
