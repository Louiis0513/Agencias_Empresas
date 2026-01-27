<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            
            // Quién vendió (Cajero) y a quién (Cliente - opcional)
            $table->foreignId('user_id')->constrained(); // El empleado que registró la venta
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null'); // Cliente (Si tenemos módulo de clientes)
            
            // Datos Financieros
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            
            // Estado de la Factura
            // 'PAID' (Pagada), 'PENDING' (Fiado/Crédito), 'VOID' (Anulada)
            $table->string('status')->default('PAID');
            
            // Método de pago principal (Referencia rápida)
            $table->string('payment_method')->default('CASH'); // CASH, CARD, TRANSFER
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
