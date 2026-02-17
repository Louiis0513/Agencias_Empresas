<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerRequest;
use App\Models\Role;
use App\Models\Store;
use App\Models\Worker;
use App\Services\StorePermissionService;
use App\Services\WorkerService;
use Exception;

class StoreWorkerController extends Controller
{
    public function index(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'workers.view');

        $roles = Role::where('store_id', $store->id)->get()->keyBy('id');

        $owner = $store->owner;
        $workersList = collect();

        if ($owner) {
            $workersList->push([
                'id' => 'owner-' . $owner->id,
                'worker_id' => null,
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'Dueño',
                'role_id' => null,
                'vinculado' => true,
            ]);
        }

        foreach ($store->workerRecords()->with('role')->get() as $w) {
            $workersList->push([
                'id' => $w->id,
                'worker_id' => $w->id,
                'name' => $w->name,
                'email' => $w->email,
                'role' => $w->role->name ?? '-',
                'role_id' => $w->role_id,
                'vinculado' => $w->estaVinculado(),
            ]);
        }

        $rolesList = Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.workers', compact('store', 'workersList', 'rolesList'));
    }

    public function create(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'workers.create');

        $rolesList = Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.worker-create', compact('store', 'rolesList'));
    }

    public function store(Store $store, StoreWorkerRequest $request, StorePermissionService $permission, WorkerService $workerService)
    {
        $permission->authorize($store, 'workers.create');

        if (! Role::where('id', $request->role_id)->where('store_id', $store->id)->exists()) {
            return redirect()->back()->withInput()->with('error', 'El rol seleccionado no pertenece a esta tienda.');
        }

        try {
            $workerService->createWorker($store, $request->only(['name', 'email', 'role_id', 'phone', 'document_number', 'address']));
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador añadido correctamente.');
    }

    public function edit(Store $store, Worker $worker, StorePermissionService $permission)
    {
        $permission->authorize($store, 'workers.edit');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        $rolesList = Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.worker-edit', compact('store', 'worker', 'rolesList'));
    }

    public function update(Store $store, Worker $worker, StoreWorkerRequest $request, StorePermissionService $permission, WorkerService $workerService)
    {
        $permission->authorize($store, 'workers.edit');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        if (! Role::where('id', $request->role_id)->where('store_id', $store->id)->exists()) {
            return redirect()->back()->withInput()->with('error', 'El rol no pertenece a esta tienda.');
        }

        try {
            $workerService->updateWorker($worker, $request->only(['name', 'email', 'role_id', 'phone', 'document_number', 'address']));
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador actualizado correctamente.');
    }

    public function destroy(Store $store, Worker $worker, StorePermissionService $permission, WorkerService $workerService)
    {
        $permission->authorize($store, 'workers.destroy');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        $workerService->deleteWorker($worker);

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador eliminado de la tienda.');
    }
}
