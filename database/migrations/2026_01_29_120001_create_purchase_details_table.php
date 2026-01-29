<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle de compra: registro histórico de costo y cantidad por transacción.
     * item_type: INVENTARIO (suma stock) | ACTIVO_FIJO (no suma stock)
     */
    public function up(): void
    {
        Schema::create('purchase_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade');

            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('item_type', 20); // INVENTARIO | ACTIVO_FIJO
            $table->string('description')->comment('Obligatorio si product_id es NULL');

            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
    }
};
