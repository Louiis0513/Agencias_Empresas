<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    /**
     * Prefijos de slug (antes del primer punto) que comparten un solo PlanFeature:
     * membresías de clientes, asistencias y panel público de suscripciones.
     *
     * @var list<string>
     */
    private const MEMBERSHIP_PERMISSION_PREFIXES = [
        'asistencias',
        'subscriptions',
        'panel-suscripciones-config',
    ];

    public function run(): void
    {
        $membershipFeature = PlanFeature::firstOrCreate(
            ['slug' => 'memberships.module'],
            [
                'module' => 'memberships',
                'name' => 'Membresías, asistencias y panel',
                'description' => 'Planes de suscripción de clientes, registro de asistencias y configuración del panel público de suscripciones.',
            ]
        );

        $permissions = Permission::query()->get();

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission->slug);
            $module = $parts[0] ?? 'general';

            if (in_array($module, self::MEMBERSHIP_PERMISSION_PREFIXES, true)) {
                $permission->planFeatures()->sync([$membershipFeature->id]);

                continue;
            }

            $slug = $module.'.module';

            $feature = PlanFeature::firstOrCreate(
                ['slug' => $slug],
                [
                    'module' => $module,
                    'name' => 'Modulo '.ucfirst(str_replace('-', ' ', $module)),
                    'description' => 'Habilita funciones del modulo '.$module,
                ]
            );

            $permission->planFeatures()->sync([$feature->id]);
        }
    }
}
