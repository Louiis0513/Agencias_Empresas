<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quitar de movimientos_bolsillo datos que ya están en el comprobante:
     * user_id, payment_method → el comprobante tiene user_id y el detalle va en el comprobante.
     * reversal_of_* → la reversa es crear otro comprobante y en el detalle/notas indicarlo.
     */
    public function up(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_bolsillo', 'reversal_of_comprobante_ingreso_id')) {
                $table->dropForeign(['reversal_of_comprobante_ingreso_id']);
                $table->dropColumn('reversal_of_comprobante_ingreso_id');
            }
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_bolsillo', 'reversal_of_comprobante_egreso_id')) {
                $table->dropForeign(['reversal_of_comprobante_egreso_id']);
                $table->dropColumn('reversal_of_comprobante_egreso_id');
            }
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_bolsillo', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_bolsillo', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('amount');
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('comprobante_ingreso_id')->constrained()->onDelete('set null');
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('reversal_of_comprobante_egreso_id')->nullable()->after('comprobante_ingreso_id')
                ->constrained('comprobantes_egreso')->onDelete('set null');
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('reversal_of_comprobante_ingreso_id')->nullable()->after('reversal_of_comprobante_egreso_id')
                ->constrained('comprobantes_ingreso')->onDelete('set null');
        });
    }
};
