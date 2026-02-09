<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite activar/desactivar variantes de productos por lote.
     */
    public function up(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
