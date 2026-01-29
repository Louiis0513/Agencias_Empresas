<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula movimientos de bolsillo con pagos a cuentas por pagar.
     * Cada abono genera EGRESO en caja; este campo permite trazabilidad.
     */
    public function up(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('account_payable_payment_id')->nullable()->after('invoice_id')
                ->constrained('account_payable_payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->dropForeign(['account_payable_payment_id']);
        });
    }
};
