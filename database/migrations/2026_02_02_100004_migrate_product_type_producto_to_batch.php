<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Todo producto es serializado o por lotes; los que eran "simple" (producto) pasan a "batch".
     */
    public function up(): void
    {
        DB::table('products')
            ->whereNull('type')
            ->orWhere('type', 'producto')
            ->update(['type' => 'batch']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte: no podemos saber cuáles eran producto vs batch original
    }
};
