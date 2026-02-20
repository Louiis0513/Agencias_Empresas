<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincular cada movimiento de bolsillo a la sesión de caja activa al momento del movimiento.
     */
    public function up(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('sesion_caja_id')->nullable()->after('bolsillo_id')
                ->constrained('sesiones_caja')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->dropForeign(['sesion_caja_id']);
        });
    }
};
