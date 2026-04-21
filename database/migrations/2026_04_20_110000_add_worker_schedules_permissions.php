<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['slug' => 'workers.schedules.view', 'name' => 'Ver registro de horarios', 'description' => 'Ver horarios de trabajadores'],
            ['slug' => 'workers.schedules.create', 'name' => 'Registrar horarios', 'description' => 'Registrar entradas y salidas de trabajadores'],
            ['slug' => 'workers.schedules.edit', 'name' => 'Editar horarios', 'description' => 'Modificar registros de horarios'],
            ['slug' => 'workers.schedules.destroy', 'name' => 'Eliminar horarios', 'description' => 'Eliminar registros de horarios'],
        ];

        foreach ($permissions as $p) {
            if (! DB::table('permissions')->where('slug', $p['slug'])->exists()) {
                DB::table('permissions')->insert([
                    'slug' => $p['slug'],
                    'name' => $p['name'],
                    'description' => $p['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $mappings = [
            'workers.view' => ['workers.schedules.view'],
            'workers.create' => ['workers.schedules.create'],
            'workers.edit' => ['workers.schedules.edit', 'workers.schedules.create'],
            'workers.destroy' => ['workers.schedules.destroy'],
        ];

        foreach ($mappings as $oldSlug => $newSlugs) {
            $old = DB::table('permissions')->where('slug', $oldSlug)->first();
            if (! $old) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $old->id)
                ->pluck('role_id')
                ->unique();

            foreach ($newSlugs as $newSlug) {
                $new = DB::table('permissions')->where('slug', $newSlug)->first();
                if (! $new) {
                    continue;
                }

                foreach ($roleIds as $roleId) {
                    $exists = DB::table('role_permission')
                        ->where('role_id', $roleId)
                        ->where('permission_id', $new->id)
                        ->exists();

                    if (! $exists) {
                        DB::table('role_permission')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $new->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'workers.schedules.view',
            'workers.schedules.create',
            'workers.schedules.edit',
            'workers.schedules.destroy',
        ];

        foreach ($slugs as $slug) {
            $perm = DB::table('permissions')->where('slug', $slug)->first();
            if ($perm) {
                DB::table('role_permission')->where('permission_id', $perm->id)->delete();
                DB::table('permissions')->where('id', $perm->id)->delete();
            }
        }
    }
};
