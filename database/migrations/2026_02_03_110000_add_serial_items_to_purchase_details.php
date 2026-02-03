<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para compras de productos serializados: guarda por unidad
     * serial_number, cost y features (para usar al aprobar → product_items).
     */
    public function up(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->json('serial_items')->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn('serial_items');
        });
    }
};
