<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplificación: los movimientos de caja solo tienen como documento soporte
     * un Comprobante de Ingreso o de Egreso. Se eliminan referencias directas a
     * factura y a account_payable_payment.
     */
    public function up(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_bolsillo', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_bolsillo', 'account_payable_payment_id')) {
                $table->dropForeign(['account_payable_payment_id']);
                $table->dropColumn('account_payable_payment_id');
            }
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_bolsillo', 'reversal_of_account_payable_payment_id')) {
                $table->dropForeign('mov_bolsillo_reversal_of_app_fk');
                $table->dropColumn('reversal_of_account_payable_payment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('account_payable_payment_id')->nullable()
                ->constrained('account_payable_payments')->onDelete('set null');
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of_account_payable_payment_id')->nullable();
            $table->foreign('reversal_of_account_payable_payment_id', 'mov_bolsillo_reversal_of_app_fk')
                ->references('id')->on('account_payable_payments')->onDelete('set null');
        });
    }
};
