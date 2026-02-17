<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Productos simples no tienen variantes, por lo que product_variant_id
     * debe ser nullable para permitir batch_items sin variante asociada.
     */
    public function up(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });
    }
};
