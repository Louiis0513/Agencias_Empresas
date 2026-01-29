<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula movimientos de inventario con la compra que los originó.
     * Trazabilidad: saber qué compra generó cada entrada.
     */
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->after('product_id')
                ->constrained()->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });
    }
};
