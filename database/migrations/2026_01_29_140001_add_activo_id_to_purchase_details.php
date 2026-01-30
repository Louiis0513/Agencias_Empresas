<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula purchase_details con activos cuando item_type = ACTIVO_FIJO.
     * product_id = inventario (estantería). activo_id = activos (escritorio).
     */
    public function up(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->foreignId('activo_id')->nullable()->after('product_id')
                ->constrained()->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropForeign(['activo_id']);
        });
    }
};
