<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costo unitario del activo (promedio ponderado cuando se recibe de varias compras).
     */
    public function up(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0)->after('quantity')
                ->comment('Costo unitario (promedio ponderado)');
        });
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
