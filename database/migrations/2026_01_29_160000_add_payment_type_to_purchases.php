<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * payment_type: método original (CONTADO=contado, CREDITO=crédito).
     * Permite distinguir "Contado" vs "Crédito (Pagado)" cuando ambos tienen payment_status=PAGADO.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('payment_type', 20)->default('CONTADO')->after('payment_status'); // CONTADO | CREDITO
        });

        \DB::table('purchases')->where('payment_status', 'PENDIENTE')->update(['payment_type' => 'CREDITO']);
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
