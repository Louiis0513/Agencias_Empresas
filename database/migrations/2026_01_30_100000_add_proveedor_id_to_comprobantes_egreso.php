<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regla de Oro: Un Comprobante = Un Proveedor.
     * proveedor_id = NULL para gastos directos (taxi, café, etc.)
     */
    public function up(): void
    {
        Schema::table('comprobantes_egreso', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->after('store_id')
                ->constrained('proveedores')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_egreso', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
        });
    }
};
