<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = Permission::query()->get();

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->slug);
            $module = $parts[0] ?? 'general';
            $slug = $module.'.module';

            $feature = PlanFeature::firstOrCreate(
                ['slug' => $slug],
                [
                    'module' => $module,
                    'name' => 'Modulo '.ucfirst(str_replace('-', ' ', $module)),
                    'description' => 'Habilita funciones del modulo '.$module,
                ]
            );

            $permission->planFeatures()->syncWithoutDetaching([$feature->id]);
        }
    }
}

