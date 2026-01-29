<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula movimientos de reversa (INGRESO) con el pago original revertido.
     * Trazabilidad bancaria: el ingreso por reversa referencia al pago que deshace.
     */
    public function up(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientos_bolsillo', 'reversal_of_account_payable_payment_id')) {
                $table->unsignedBigInteger('reversal_of_account_payable_payment_id')->nullable()->after('account_payable_payment_id');
            }
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreign('reversal_of_account_payable_payment_id', 'mov_bolsillo_reversal_of_app_fk')
                ->references('id')->on('account_payable_payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->dropForeign('mov_bolsillo_reversal_of_app_fk');
            $table->dropColumn('reversal_of_account_payable_payment_id');
        });
    }
};
