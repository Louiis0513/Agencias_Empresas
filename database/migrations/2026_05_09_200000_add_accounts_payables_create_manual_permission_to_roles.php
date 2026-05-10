<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        if (! DB::table('permissions')->where('slug', 'accounts-payables.create-manual')->exists()) {
            DB::table('permissions')->insert([
                'slug' => 'accounts-payables.create-manual',
                'name' => 'Registrar CxP manual',
                'description' => 'Crear cuentas por pagar sin compra (ej. cuenta de cobro)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $newPermId = (int) DB::table('permissions')->where('slug', 'accounts-payables.create-manual')->value('id');
        if (! $newPermId) {
            return;
        }

        if (Schema::hasTable('plan_features') && Schema::hasTable('permission_plan_features')) {
            $module = 'accounts-payables';
            $featureSlug = $module.'.module';
            $featureId = DB::table('plan_features')->where('slug', $featureSlug)->value('id');
            if (! $featureId) {
                DB::table('plan_features')->insert([
                    'slug' => $featureSlug,
                    'module' => $module,
                    'name' => 'Modulo '.ucfirst(str_replace('-', ' ', $module)),
                    'description' => 'Habilita funciones del modulo '.$module,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $featureId = (int) DB::table('plan_features')->where('slug', $featureSlug)->value('id');
            }

            if ($featureId && ! DB::table('permission_plan_features')->where('permission_id', $newPermId)->exists()) {
                DB::table('permission_plan_features')->insert([
                    'permission_id' => $newPermId,
                    'plan_feature_id' => $featureId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('role_permission')) {
            return;
        }

        $sourcePermission = DB::table('permissions')->where('slug', 'accounts-payables.pay')->first();
        if (! $sourcePermission) {
            return;
        }

        $roleIds = DB::table('role_permission')
            ->where('permission_id', $sourcePermission->id)
            ->pluck('role_id')
            ->unique();

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_permission')
                ->where('role_id', $roleId)
                ->where('permission_id', $newPermId)
                ->exists();

            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $newPermId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No revertimos asignaciones en roles para no romper despliegues.
    }
};
