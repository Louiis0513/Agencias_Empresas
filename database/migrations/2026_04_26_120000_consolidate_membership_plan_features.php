<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une asistencias, subscriptions y panel-suscripciones-config en un solo PlanFeature
 * para el diseñador de planes (módulo tipo gimnasio / membresías).
 */
return new class extends Migration
{
    private const NEW_SLUG = 'memberships.module';

    private const OLD_SLUGS = [
        'asistencias.module',
        'subscriptions.module',
        'panel-suscripciones-config.module',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('plan_features')) {
            return;
        }

        $now = now();

        $newId = DB::table('plan_features')->where('slug', self::NEW_SLUG)->value('id');
        if (! $newId) {
            $newId = DB::table('plan_features')->insertGetId([
                'slug' => self::NEW_SLUG,
                'module' => 'memberships',
                'name' => 'Membresías, asistencias y panel',
                'description' => 'Planes de suscripción de clientes, registro de asistencias y configuración del panel público de suscripciones.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $oldIds = DB::table('plan_features')
            ->whereIn('slug', self::OLD_SLUGS)
            ->pluck('id')
            ->all();

        $permissionIds = DB::table('permissions')
            ->where(function ($q) {
                $q->where('slug', 'like', 'asistencias.%')
                    ->orWhere('slug', 'like', 'subscriptions.%')
                    ->orWhere('slug', 'like', 'panel-suscripciones-config.%');
            })
            ->pluck('id');

        foreach ($permissionIds as $pid) {
            DB::table('permission_plan_features')->where('permission_id', $pid)->delete();
            DB::table('permission_plan_features')->insert([
                'permission_id' => $pid,
                'plan_feature_id' => $newId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($oldIds !== []) {
            $this->mergeStoreOverrides($oldIds, (int) $newId, $now);
            $this->mergePreviewOverrides($oldIds, (int) $newId, $now);
            DB::table('plan_features')->whereIn('id', $oldIds)->delete();
        }
    }

    public function down(): void
    {
        // Consolidación de datos: no se revierte de forma fiable (overrides fusionados).
    }

    /**
     * @param  array<int>  $oldFeatureIds
     */
    private function mergeStoreOverrides(array $oldFeatureIds, int $newFeatureId, $now): void
    {
        if ($oldFeatureIds === [] || ! Schema::hasTable('store_plan_feature_overrides')) {
            return;
        }

        $rank = [
            'included' => 1,
            'premium' => 2,
            'addon' => 3,
            'disabled' => 4,
        ];

        $rows = DB::table('store_plan_feature_overrides')
            ->whereIn('plan_feature_id', $oldFeatureIds)
            ->get();

        $grouped = $rows->groupBy(fn ($r) => $r->store_id.'|'.$r->scope);

        foreach ($grouped as $compositeKey => $group) {
            [$storeId, $scope] = explode('|', $compositeKey, 2);
            $best = 'included';
            $bestRank = 0;
            $updatedBy = null;
            foreach ($group as $r) {
                $s = (string) $r->status;
                $rval = $rank[$s] ?? 0;
                if ($rval > $bestRank) {
                    $bestRank = $rval;
                    $best = $s;
                    $updatedBy = $r->updated_by_user_id;
                }
            }

            DB::table('store_plan_feature_overrides')
                ->where('store_id', $storeId)
                ->where('scope', $scope)
                ->whereIn('plan_feature_id', $oldFeatureIds)
                ->delete();

            if ($best !== 'included') {
                DB::table('store_plan_feature_overrides')->updateOrInsert(
                    [
                        'store_id' => $storeId,
                        'plan_feature_id' => $newFeatureId,
                        'scope' => $scope,
                    ],
                    [
                        'status' => $best,
                        'updated_by_user_id' => $updatedBy,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<int>  $oldFeatureIds
     */
    private function mergePreviewOverrides(array $oldFeatureIds, int $newFeatureId, $now): void
    {
        if ($oldFeatureIds === [] || ! Schema::hasTable('plan_feature_preview_overrides')) {
            return;
        }

        $rows = DB::table('plan_feature_preview_overrides')
            ->whereIn('plan_feature_id', $oldFeatureIds)
            ->get();

        $grouped = $rows->groupBy(fn ($r) => $r->store_id.'|'.$r->user_id);

        foreach ($grouped as $compositeKey => $group) {
            [$storeId, $userId] = explode('|', $compositeKey, 2);
            $enabled = $group->contains(fn ($r) => (bool) $r->enabled);

            DB::table('plan_feature_preview_overrides')
                ->where('store_id', $storeId)
                ->where('user_id', $userId)
                ->whereIn('plan_feature_id', $oldFeatureIds)
                ->delete();

            if ($enabled) {
                DB::table('plan_feature_preview_overrides')->insert([
                    'store_id' => $storeId,
                    'user_id' => $userId,
                    'plan_feature_id' => $newFeatureId,
                    'enabled' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            }
        }
    }
};
