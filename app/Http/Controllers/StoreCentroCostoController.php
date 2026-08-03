<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCentroCostoRequest;
use App\Http\Requests\StoreDefinirComprobantesCentroCostoRequest;
use App\Models\CentroCosto;
use App\Models\Store;
use App\Models\TipoComprobante;
use App\Services\CentroCostoService;
use App\Services\StorePermissionService;
use Exception;
use Illuminate\Http\Request;

class StoreCentroCostoController extends Controller
{
    public function __construct(
        protected StorePermissionService $permissionService,
        protected CentroCostoService $centroCostoService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.centros-costo.view');

        $tab = $request->get('tab') === 'definir' ? 'definir' : 'catalogo';

        $centrosPadre = CentroCosto::query()
            ->deStore($store)
            ->centros()
            ->activos()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $subcentrosOpciones = collect();
        $tiposPorFamilia = collect();
        if ($tab === 'definir') {
            $tiposPorFamilia = $this->centroCostoService->matrizDefinirComprobantes($store);
            $subcentrosOpciones = CentroCosto::query()
                ->deStore($store)
                ->subcentros()
                ->activos()
                ->with('padre:id,codigo,nombre')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'parent_id']);
        }

        return view('stores.contabilidad.centros-costo', [
            'store' => $store,
            'tab' => $tab,
            'items' => $tab === 'catalogo'
                ? $this->centroCostoService->listar($store, [
                    'search' => $request->get('search'),
                    'activo' => $request->get('activo'),
                    'nivel' => $request->get('nivel'),
                ])
                : null,
            'centrosPadre' => $centrosPadre,
            'tiposPorFamilia' => $tiposPorFamilia,
            'subcentrosOpciones' => $subcentrosOpciones,
            'etiquetasGrupo' => TipoComprobante::etiquetasFamiliasGrupo(),
        ]);
    }

    public function store(StoreCentroCostoRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.centros-costo.create');

        $data = $request->validated();

        try {
            if (! empty($data['es_subcentro']) || ! empty($data['parent_id'])) {
                $item = $this->centroCostoService->crearSubcentro($store, $data);
                $msg = 'Subcentro «'.$item->codigo.' — '.$item->nombre.'» creado.';
            } else {
                $item = $this->centroCostoService->crearCentro($store, $data);
                $msg = 'Centro «'.$item->codigo.' — '.$item->nombre.'» creado (con subcentro General).';
            }

            return redirect()
                ->route('stores.contabilidad.centros-costo', $store)
                ->with('success', $msg);
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.centros-costo', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function update(StoreCentroCostoRequest $request, Store $store, CentroCosto $centroCosto)
    {
        $this->permissionService->authorize($store, 'contabilidad.centros-costo.edit');

        if ($centroCosto->store_id !== $store->id) {
            abort(404);
        }

        try {
            $this->centroCostoService->actualizar($store, $centroCosto, $request->validated());

            return redirect()
                ->route('stores.contabilidad.centros-costo', $store)
                ->with('success', 'Centro de costo actualizado.');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.centros-costo', $store)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function updateDefinirComprobantes(StoreDefinirComprobantesCentroCostoRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'contabilidad.centros-costo.edit');

        try {
            $n = $this->centroCostoService->guardarDefinirComprobantes(
                $store,
                $request->validated('tipos')
            );

            return redirect()
                ->route('stores.contabilidad.centros-costo', [$store, 'tab' => 'definir'])
                ->with('success', 'Configuración de comprobantes guardada ('.$n.' tipos).');
        } catch (Exception $e) {
            return redirect()
                ->route('stores.contabilidad.centros-costo', [$store, 'tab' => 'definir'])
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
