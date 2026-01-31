<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha de vencimiento de la factura (cuenta por pagar).
     * El usuario la indica cuando tiene la factura real y acuerdos con el proveedor.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('invoice_date')->comment('Fecha de vencimiento de la cuenta por pagar');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
