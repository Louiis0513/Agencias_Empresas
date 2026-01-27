<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Crea grupo "General" por tienda y asigna atributos existentes.
     */
    public function up(): void
    {
        $stores = DB::table('attributes')->distinct()->pluck('store_id');
        foreach ($stores as $storeId) {
            $groupId = DB::table('attribute_groups')->insertGetId([
                'store_id' => $storeId,
                'name' => 'General',
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $attrs = DB::table('attributes')->where('store_id', $storeId)->get();
            $pos = 0;
            foreach ($attrs as $a) {
                DB::table('attribute_group_attribute')->insert([
                    'attribute_group_id' => $groupId,
                    'attribute_id' => $a->id,
                    'position' => $pos++,
                    'is_required' => (bool) $a->is_required,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('attribute_group_attribute')->truncate();
        DB::table('attribute_groups')->truncate();
    }
};
