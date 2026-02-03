<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo de compra: ACTIVO (activos fijos) o PRODUCTO (productos de inventario).
     * Permite filtrar la lista en Compra de activos vs Compra de productos.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('purchase_type', 20)->default('ACTIVO')->after('status')
                ->comment('ACTIVO = activos fijos, PRODUCTO = productos inventario');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('purchase_type');
        });
    }
};
