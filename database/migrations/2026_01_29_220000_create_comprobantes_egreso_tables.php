<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo Comprobantes de Egreso.
     * Un comprobante puede tener múltiples destinos (cuentas por pagar + gastos directos)
     * y múltiples orígenes (bolsillos con referencia cheque/transacción).
     */
    public function up(): void
    {
        Schema::create('comprobantes_egreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('number', 50)->comment('Consecutivo CE-001, CE-002');
            $table->decimal('total_amount', 15, 2)->comment('Monto total del egreso');
            $table->date('payment_date');
            $table->string('notes')->nullable();
            $table->string('type', 20)->default('PAGO_CUENTA'); // PAGO_CUENTA | GASTO_DIRECTO | MIXTO
            $table->string('beneficiary_name')->nullable()->comment('A quién se le pagó. Si mixto: "Varios"');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversal_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('comprobante_egreso_destinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_egreso_id')->constrained('comprobantes_egreso')->onDelete('cascade');
            $table->string('type', 20); // CUENTA_POR_PAGAR | GASTO_DIRECTO
            $table->foreignId('account_payable_id')->nullable()->constrained('accounts_payables')->onDelete('cascade');
            $table->string('concepto')->nullable()->comment('Para GASTO_DIRECTO: Taxi, Café, etc.');
            $table->string('beneficiario')->nullable()->comment('Para GASTO_DIRECTO: Juan Pérez, etc.');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('comprobante_egreso_origenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_egreso_id')->constrained('comprobantes_egreso')->onDelete('cascade');
            $table->foreignId('bolsillo_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable()->comment('Número de cheque o transacción bancaria');
            $table->timestamps();
        });

        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('comprobante_egreso_id')->nullable()->after('account_payable_payment_id')
                ->constrained('comprobantes_egreso')->onDelete('set null');
            $table->foreignId('reversal_of_comprobante_egreso_id')->nullable()->after('reversal_of_account_payable_payment_id')
                ->constrained('comprobantes_egreso')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_comprobante_egreso_id']);
            $table->dropForeign(['comprobante_egreso_id']);
        });
        Schema::dropIfExists('comprobante_egreso_origenes');
        Schema::dropIfExists('comprobante_egreso_destinos');
        Schema::dropIfExists('comprobantes_egreso');
    }
};
