<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compras: abastecimiento de productos o bienes del local.
     * status: proceso logístico (BORRADOR, APROBADO, ANULADO)
     * payment_status: dinero (PENDIENTE=crédito, PAGADO=contado)
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->onDelete('set null');

            $table->string('status', 20)->default('BORRADOR'); // BORRADOR | APROBADO | ANULADO
            $table->string('payment_status', 20)->default('PAGADO'); // PENDIENTE | PAGADO

            $table->string('invoice_number')->nullable()->comment('Número de factura externa del proveedor');
            $table->date('invoice_date')->nullable()->comment('Fecha de emisión de la factura externa');
            $table->string('image_path')->nullable()->comment('Ruta de imagen de la factura (futuro)');

            $table->decimal('total', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
