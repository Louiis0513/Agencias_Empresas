<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreRoleController extends Controller
{
    public function index(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'roles.view');

        $roles = $store->roles()
            ->with('permissions')
            ->get();

        $allPermissions = Permission::orderBy('name')->get();

        return view('stores.roles', compact('store', 'roles', 'allPermissions'));
    }

    public function store(Store $store, StoreRoleRequest $request, StorePermissionService $permission)
    {
        $permission->authorize($store, 'roles.create');

        Role::create([
            'store_id' => $store->id,
            'name' => $request->input('name'),
        ]);

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol creado correctamente.');
    }

    public function update(Store $store, Role $role, StoreRoleRequest $request, StorePermissionService $permission)
    {
        $permission->authorize($store, 'roles.edit');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        $role->update(['name' => $request->input('name')]);

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Store $store, Role $role, StorePermissionService $permission)
    {
        $permission->authorize($store, 'roles.destroy');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        $workersConRol = $store->workerRecords()->where('role_id', $role->id)->count();
        if ($workersConRol > 0) {
            return redirect()->back()->with('error', "Hay {$workersConRol} trabajador(es) con este rol. Reasígnalos a otro rol antes de eliminarlo.");
        }

        DB::table('store_user')->where('store_id', $store->id)->where('role_id', $role->id)->update(['role_id' => null]);

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol eliminado correctamente.');
    }

    public function permissions(Store $store, Role $role, StorePermissionService $permission)
    {
        $permission->authorize($store, 'roles.permissions');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        $role->load('permissions');
        $allPermissions = Permission::orderBy('name')->get();
        $workersWithRole = $store->workerRecords()->where('role_id', $role->id)->get();

        return view('stores.role-permissions', compact('store', 'role', 'allPermissions', 'workersWithRole'));
    }

    public function updatePermissions(Store $store, Role $role, StoreRoleRequest $request, StorePermissionService $permission)
    {
        $permission->authorize($store, 'roles.permissions');

        if ($role->store_id !== $store->id) {
            abort(404);
        }

        $permissionIds = $request->input('permission_ids', []) ?: [];
        $role->permissions()->sync($permissionIds);

        return redirect()->route('stores.roles.permissions', [$store, $role])
            ->with('success', 'Permisos actualizados correctamente.');
    }
}
