<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pagos/abonos a cuentas por pagar.
     * Un pago puede provenir de uno o varios bolsillos (account_payable_payment_parts).
     */
    public function up(): void
    {
        Schema::create('account_payable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_payable_id')->constrained('accounts_payables')->onDelete('cascade');

            $table->decimal('amount', 15, 2)->comment('Monto total del abono');
            $table->date('payment_date');
            $table->string('notes')->nullable();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamps();
        });

        Schema::create('account_payable_payment_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_payable_payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('bolsillo_id')->constrained()->onDelete('cascade');

            $table->decimal('amount', 15, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payable_payment_parts');
        Schema::dropIfExists('account_payable_payments');
    }
};
