<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\PlanFeature;
use App\Models\Store;
use App\Models\StorePlanFeatureOverride;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class StoreFeatureAccessService
{
    public const STATUS_INCLUDED = 'included';
    public const STATUS_PREMIUM = 'premium';
    public const STATUS_ADDON = 'addon';
    public const STATUS_DISABLED = 'disabled';

    private const SCOPE_PUBLISHED = 'published';

    public function canUsePermission(Store $store, string $permissionSlug, ?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user) {
            return false;
        }

        $permission = Permission::where('slug', $permissionSlug)->first();
        if (! $permission) {
            return true;
        }

        $feature = $this->resolveFeatureForPermission($permission);
        if (! $feature) {
            return true;
        }

        $status = $this->getFeatureStatusForStore($store, $feature->id, self::SCOPE_PUBLISHED);

        return $this->statusAllowsUserPlan($status, $user);
    }

    public function resolveFeatureForPermission(Permission $permission): ?PlanFeature
    {
        if ($permission->relationLoaded('planFeatures') && $permission->planFeatures->isNotEmpty()) {
            return $permission->planFeatures->first();
        }

        $existing = $permission->planFeatures()->first();
        if ($existing) {
            return $existing;
        }

        $parts = explode('.', $permission->slug);
        $module = $parts[0] ?? 'general';
        $featureSlug = $module.'.module';
        $featureName = 'Modulo '.str_replace('-', ' ', $module);

        $feature = PlanFeature::firstOrCreate(
            ['slug' => $featureSlug],
            [
                'module' => $module,
                'name' => ucfirst($featureName),
                'description' => 'Habilita el modulo '.$module.' para la tienda.',
            ]
        );

        $permission->planFeatures()->syncWithoutDetaching([$feature->id]);

        return $feature;
    }

    public function getCatalogForStore(Store $store, User $user): Collection
    {
        $permissions = Permission::query()->orderBy('slug')->get();
        $modules = [];

        /** @var Permission $permission */
        foreach ($permissions as $permission) {
            $feature = $this->resolveFeatureForPermission($permission);
            if (! $feature) {
                continue;
            }

            $module = $feature->module ?: 'general';
            $modules[$module] ??= [
                'module' => $module,
                'features' => [],
            ];

            if (! isset($modules[$module]['features'][$feature->id])) {
                $modules[$module]['features'][$feature->id] = [
                    'id' => $feature->id,
                    'slug' => $feature->slug,
                    'name' => $feature->name,
                    'description' => $feature->description,
                    'status' => $this->getFeatureStatusForStore($store, $feature->id, self::SCOPE_PUBLISHED),
                    'permissions' => [],
                ];
            }

            $modules[$module]['features'][$feature->id]['permissions'][] = $permission->slug;
        }

        return collect($modules)
            ->map(function (array $moduleData) {
                $moduleData['features'] = collect($moduleData['features'])
                    ->sortBy('slug')
                    ->values();

                return $moduleData;
            })
            ->sortBy('module')
            ->values();
    }

    public function updateFeatureStatus(Store $store, int $featureId, string $status, int $userId): void
    {
        $this->assertValidStatus($status);

        StorePlanFeatureOverride::updateOrCreate(
            [
                'store_id' => $store->id,
                'plan_feature_id' => $featureId,
                'scope' => self::SCOPE_PUBLISHED,
            ],
            [
                'status' => $status,
                'updated_by_user_id' => $userId,
            ]
        );
    }

    public function applyStatusToAll(Store $store, string $status, int $userId): void
    {
        $this->assertValidStatus($status);
        $featureIds = PlanFeature::query()->pluck('id');

        foreach ($featureIds as $featureId) {
            $this->updateFeatureStatus($store, (int) $featureId, $status, $userId);
        }
    }

    private function getFeatureStatusForStore(Store $store, int $featureId, string $scope): string
    {
        return StorePlanFeatureOverride::query()
            ->where('store_id', $store->id)
            ->where('plan_feature_id', $featureId)
            ->where('scope', $scope)
            ->value('status') ?? self::STATUS_INCLUDED;
    }

    private function statusAllowsUserPlan(string $status, User $user): bool
    {
        if ($status === self::STATUS_INCLUDED) {
            return true;
        }

        if ($status === self::STATUS_DISABLED || $status === self::STATUS_ADDON) {
            return false;
        }

        $slug = strtolower((string) optional($user->plan)->slug);

        if ($status === self::STATUS_PREMIUM) {
            return str_contains($slug, 'pro') || str_contains($slug, 'premium');
        }

        return false;
    }

    private function assertValidStatus(string $status): void
    {
        $valid = [
            self::STATUS_INCLUDED,
            self::STATUS_PREMIUM,
            self::STATUS_ADDON,
            self::STATUS_DISABLED,
        ];

        if (! in_array($status, $valid, true)) {
            abort(422, 'Estado de feature no valido.');
        }
    }
}

