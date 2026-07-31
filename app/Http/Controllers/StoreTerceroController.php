<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTerceroRequest;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tercero;
use App\Models\TerceroContacto;
use App\Models\TerceroDireccion;
use App\Services\ProductService;
use App\Services\StorePermissionService;
use App\Services\TerceroService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreTerceroController extends Controller
{
    public function __construct(
        private readonly StorePermissionService $permissionService,
        private readonly TerceroService $terceroService,
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request, Store $store)
    {
        $this->permissionService->authorize($store, 'terceros.view');
        $this->terceroService->asegurarConsumidorFinal($store);

        $terceros = $this->terceroService->listar($store, $request->only(['search', 'rol', 'activo']));

        return view('stores.terceros.index', compact('store', 'terceros'));
    }

    public function create(Store $store)
    {
        $this->permissionService->authorize($store, 'terceros.create');

        return $this->formView($store);
    }

    public function store(StoreTerceroRequest $request, Store $store)
    {
        $this->permissionService->authorize($store, 'terceros.create');

        try {
            $tercero = $this->terceroService->crear($store, $request->validated());

            return redirect()->route('stores.terceros.show', [$store, $tercero])
                ->with('success', 'Tercero creado correctamente.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Store $store, Tercero $tercero)
    {
        $this->permissionService->authorize($store, 'terceros.view');
        $this->assertStore($store, $tercero);

        return $this->formView($store, $this->terceroService->obtener($store, $tercero->id));
    }

    public function edit(Store $store, Tercero $tercero)
    {
        return $this->show($store, $tercero);
    }

    public function update(StoreTerceroRequest $request, Store $store, Tercero $tercero)
    {
        $this->permissionService->authorize($store, 'terceros.edit');
        $this->assertStore($store, $tercero);

        try {
            $this->terceroService->actualizar($store, $tercero, $request->validated());

            return back()->with('success', 'Tercero actualizado correctamente.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Store $store, Tercero $tercero)
    {
        $this->permissionService->authorize($store, 'terceros.destroy');
        $this->assertStore($store, $tercero);

        try {
            $this->terceroService->eliminar($store, $tercero);

            return redirect()->route('stores.terceros', $store)->with('success', 'Tercero eliminado.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeContacto(Request $request, Store $store, Tercero $tercero)
    {
        $this->permissionService->authorize($store, 'terceros.edit');
        $this->terceroService->agregarContacto($store, $tercero, $this->validateContacto($request));

        return back()->with('success', 'Contacto agregado.');
    }

    public function updateContacto(Request $request, Store $store, Tercero $tercero, TerceroContacto $contacto)
    {
        $this->permissionService->authorize($store, 'terceros.edit');
        $this->terceroService->actualizarContacto($store, $tercero, $contacto, $this->validateContacto($request));

        return back()->with('success', 'Contacto actualizado.');
    }

    public function destroyContacto(Store $store, Tercero $tercero, TerceroContacto $contacto)
    {
        $this->permissionService->authorize($store, 'terceros.edit');
        $this->terceroService->eliminarContacto($store, $tercero, $contacto);

        return back()->with('success', 'Contacto eliminado.');
    }

    public function storeDireccion(Request $request, Store $store, Tercero $tercero)
    {
        $this->permissionService->authorize($store, 'terceros.edit');
        $this->terceroService->agregarDireccion($store, $tercero, $this->validateDireccion($request));

        return back()->with('success', 'Dirección agregada.');
    }

    public function updateDireccion(Request $request, Store $store, Tercero $tercero, TerceroDireccion $direccion)
    {
        $this->permissionService->authorize($store, 'terceros.edit');
        $this->terceroService->actualizarDireccion($store, $tercero, $direccion, $this->validateDireccion($request));

        return back()->with('success', 'Dirección actualizada.');
    }

    public function destroyDireccion(Store $store, Tercero $tercero, TerceroDireccion $direccion)
    {
        $this->permissionService->authorize($store, 'terceros.edit');
        $this->terceroService->eliminarDireccion($store, $tercero, $direccion);

        return back()->with('success', 'Dirección eliminada.');
    }

    public function buscarProductos(Request $request, Store $store): JsonResponse
    {
        abort_unless(
            $this->permissionService->can($store, 'terceros.view')
                || $this->permissionService->can($store, 'terceros.edit'),
            403,
            'No tienes permiso para consultar productos de terceros.'
        );

        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'exclude' => ['nullable', 'array'],
            'exclude.*' => ['integer'],
        ]);

        if (trim($data['q']) === '') {
            return response()->json([]);
        }

        $productos = $this->productService
            ->buscarProductos($store, trim($data['q']), $data['exclude'] ?? [])
            ->map(fn ($producto) => [
                'id' => $producto->id,
                'name' => $producto->name,
                'sku' => $producto->sku,
                'barcode' => $producto->barcode,
            ])
            ->values();

        return response()->json($productos);
    }

    private function formView(Store $store, ?Tercero $tercero = null)
    {
        $roles = Role::where('store_id', $store->id)->orderBy('name')->get();
        $tercero?->loadMissing('productos');

        return view('stores.terceros.form', compact('store', 'tercero', 'roles'));
    }

    private function validateContacto(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'parentesco' => ['nullable', 'string', 'max:100'],
            'tipo_contacto' => ['nullable', 'in:principal,facturacion,cartera,compras,emergencia,otro'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'celular' => ['nullable', 'string', 'max:40'],
            'es_principal' => ['nullable', 'boolean'],
            'es_facturacion' => ['nullable', 'boolean'],
            'es_cartera' => ['nullable', 'boolean'],
            'es_compras' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);
    }

    private function validateDireccion(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', 'in:fiscal,facturacion,entrega,correspondencia'],
            'linea' => ['required', 'string', 'max:1000'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'departamento' => ['nullable', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:255'],
            'es_principal' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);
    }

    private function assertStore(Store $store, Tercero $tercero): void
    {
        abort_if($tercero->store_id !== $store->id, 404);
    }
}
