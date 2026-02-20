<?php

namespace App\Http\Controllers;

use App\Models\Activo;
use App\Models\Store;
use App\Services\ActivoService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreActivoController extends Controller
{
    public function index(Store $store, Request $request, ActivoService $activoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }

        $permission->authorize($store, 'activos.view');

        session(['current_store_id' => $store->id]);

        $filtros = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
        ];

        $activos = $activoService->listarActivos($store, $filtros);

        return view('stores.activos', compact('store', 'activos'));
    }

    public function show(Store $store, Activo $activo, ActivoService $activoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }
        $permission->authorize($store, 'activos.view');

        if ($activo->store_id !== $store->id) {
            abort(404);
        }

        $activo->load(['locationRelation', 'assignedTo', 'movimientos.user']);

        return view('stores.activo-show', compact('store', 'activo'));
    }

    public function edit(Store $store, Activo $activo, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }
        $permission->authorize($store, 'activos.edit');

        if ($activo->store_id !== $store->id) {
            abort(404);
        }

        $workers = $store->workers()->select('users.id', 'users.name')->orderBy('users.name')->get();
        $locations = \App\Models\ActivoLocation::deTienda($store->id)->activos()->orderBy('name')->get();

        return view('stores.activo-editar', compact('store', 'activo', 'workers', 'locations'));
    }

    public function update(Store $store, Activo $activo, Request $request, ActivoService $activoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }
        $permission->authorize($store, 'activos.edit');

        if ($activo->store_id !== $store->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'serial_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('activos', 'serial_number')->where('store_id', $store->id)->ignore($activo->id),
            ],
            'model' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:activo_locations,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'condition' => ['nullable', 'string', 'in:NUEVO,BUENO,REGULAR,MALO'],
            'status' => ['nullable', 'string', 'in:OPERATIVO,EN_REPARACION,EN_PRESTAMO,DONADO,DADO_DE_BAJA,VENDIDO'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_expiry' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $validated['serial_number'] = trim($validated['serial_number']);
        $validated['assigned_to_user_id'] = $request->filled('assigned_to_user_id') ? $validated['assigned_to_user_id'] : null;
        $validated['is_active'] = $request->boolean('is_active');

        try {
            $activoService->actualizarActivo($store, $activo->id, $validated, Auth::id());
        } catch (\Exception $e) {
            return redirect()->route('stores.activos.edit', [$store, $activo])
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()->route('stores.activos.show', [$store, $activo->fresh()])->with('success', 'Activo actualizado.');
    }

    public function darDeBaja(Store $store, Activo $activo, Request $request, ActivoService $activoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }
        $permission->authorize($store, 'activos.edit');

        if ($activo->store_id !== $store->id) {
            abort(404);
        }

        $motivo = $request->input('motivo');

        try {
            $activoService->darDeBaja($store, $activo->id, Auth::id(), $motivo);
        } catch (\Exception $e) {
            return redirect()->route('stores.activos.show', [$store, $activo])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('stores.activos.show', [$store, $activo->fresh()])->with('success', 'Activo dado de baja.');
    }

    public function movimientos(Store $store, Request $request, ActivoService $activoService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403);
        }

        $permission->authorize($store, 'activos.view');

        $filtros = [
            'activo_id' => $request->get('activo_id'),
            'type' => $request->get('type'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];

        $activosParaMovimientos = $activoService->activosParaMovimientos($store);
        $movimientos = $activoService->listarMovimientos($store, $filtros);

        return view('stores.activo-movimientos', compact('store', 'movimientos', 'activosParaMovimientos'));
    }
}
