<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para compras de productos por lote: guarda por variante
     * quantity, unit_cost, price (opcional) y features (para usar al aprobar → batch_items).
     */
    public function up(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->json('batch_items')->nullable()->after('serial_items');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn('batch_items');
        });
    }
};
