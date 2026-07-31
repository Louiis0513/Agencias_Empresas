<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Store;
use Illuminate\Database\Seeder;

class DefaultStoreRolesSeeder extends Seeder
{
    public function run(): void
    {
        Store::query()->select('id')->each(function (Store $store): void {
            Role::firstOrCreate([
                'store_id' => $store->id,
                'name' => 'Trabajador',
            ]);
        });
    }
}
