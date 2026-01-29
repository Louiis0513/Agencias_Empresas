<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas por pagar: se crea cuando una compra tiene payment_status = PENDIENTE.
     * Una compra a crédito = un registro maestro.
     */
    public function up(): void
    {
        Schema::create('accounts_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade');

            $table->decimal('total_amount', 15, 2)->comment('Deuda original');
            $table->decimal('balance', 15, 2)->comment('Saldo pendiente, baja con abonos');
            $table->date('due_date')->nullable()->comment('Fecha de vencimiento');
            $table->string('status', 20)->default('PENDIENTE'); // PENDIENTE | PARCIAL | PAGADO

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_payables');
    }
};
