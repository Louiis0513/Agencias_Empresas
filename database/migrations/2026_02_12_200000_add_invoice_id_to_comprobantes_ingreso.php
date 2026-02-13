<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobante de ingreso puede estar ligado a una factura (pago de factura).
     */
    public function up(): void
    {
        Schema::table('comprobantes_ingreso', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('customer_id')
                ->constrained('invoices')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_ingreso', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });
    }
};
