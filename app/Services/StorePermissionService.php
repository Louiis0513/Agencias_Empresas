<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StorePermissionService
{
    private const CACHE_KEY_PREFIX = 'store_permissions';
    private const CACHE_TTL_MINUTES = 60;

    /**
     * Devuelve los slugs de permisos del usuario en la tienda (cacheado 60 min).
     * El dueño tiene todos los permisos; los trabajadores los de su rol en store_user.
     */
    public function getUserPermissionsInStore(User $user, Store $store): array
    {
        $key = self::CACHE_KEY_PREFIX.'_'.$store->id.'_'.$user->id;

        return Cache::remember($key, self::CACHE_TTL_MINUTES * 60, function () use ($user, $store) {
            if ($store->user_id === $user->id) {
                return Permission::pluck('slug')->all();
            }

            $roleId = $this->getRoleIdForUser($store, $user);
            if (! $roleId) {
                return [];
            }

            $role = Role::find($roleId);

            return $role?->permissions()->pluck('slug')->all() ?? [];
        });
    }

    /**
     * Comprueba si el usuario tiene el permiso (por slug) en la tienda.
     */
    public function userHasPermission(User $user, Store $store, string $permissionSlug): bool
    {
        $permissions = $this->getUserPermissionsInStore($user, $store);

        return in_array($permissionSlug, $permissions, true);
    }

    /**
     * Limpia la caché de permisos para ese usuario en esa tienda (p. ej. al editar rol).
     */
    public function clearPermissionCache(int $userId, int $storeId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX.'_'.$storeId.'_'.$userId);
    }

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

        return $this->userHasPermission($user, $store, $permissionSlug);
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
