<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo Cuentas por Cobrar.
     * Una cuenta por cobrar = espejo de una factura en estado PENDING o PARTIAL.
     * 1 factura = 1 cuenta por cobrar. El usuario puede definir cuotas con fechas de vencimiento.
     */
    public function up(): void
    {
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('total_amount', 15, 2)->comment('Total a cobrar (igual al total de la factura)');
            $table->decimal('balance', 15, 2)->comment('Saldo pendiente');
            $table->date('due_date')->nullable()->comment('Fecha de vencimiento general (opcional si se usan cuotas)');
            $table->string('status', 20)->default('PENDIENTE'); // PENDIENTE | PARCIAL | PAGADO
            $table->timestamps();
        });

        Schema::create('account_receivable_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_receivable_id')->constrained('accounts_receivable')->onDelete('cascade');
            $table->unsignedSmallInteger('sequence')->comment('Orden de la cuota: 1, 2, 3...');
            $table->decimal('amount', 15, 2)->comment('Monto de esta cuota');
            $table->decimal('amount_paid', 15, 2)->default(0)->comment('Lo abonado a esta cuota');
            $table->date('due_date')->comment('Fecha de vencimiento de esta cuota');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivable_cuotas');
        Schema::dropIfExists('accounts_receivable');
    }
};
