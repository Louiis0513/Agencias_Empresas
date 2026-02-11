<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StorePermissionService
{
    /**
     * Comprueba si el usuario actual tiene un permiso en la tienda.
     * El dueño de la tienda (store->user_id) tiene todos los permisos.
     * Los trabajadores tienen los permisos de su rol.
     */
    public function can(Store $store, string $permissionSlug): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($store->user_id === $user->id) {
            return true;
        }

        $roleId = $this->getRoleIdForUser($store, $user);
        if (! $roleId) {
            return false;
        }

        return Permission::where('slug', $permissionSlug)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId))
            ->exists();
    }

    /**
     * Lanza 403 si el usuario no tiene el permiso.
     */
    public function authorize(Store $store, string $permissionSlug): void
    {
        if (! $this->can($store, $permissionSlug)) {
            abort(403, 'No tienes permiso para realizar esta acción en esta tienda.');
        }
    }

    /**
     * Devuelve el role_id del usuario en la tienda (null si no está o es dueño).
     */
    public function getRoleIdForUser(Store $store, User $user): ?int
    {
        $pivot = $store->workers()->where('user_id', $user->id)->first();
        if (! $pivot || ! $pivot->pivot->role_id) {
            return null;
        }

        return (int) $pivot->pivot->role_id;
    }
}
