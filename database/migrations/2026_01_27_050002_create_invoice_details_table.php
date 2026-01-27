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
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict'); // No permitir eliminar productos con facturas
            
            // Instantánea de la venta
            // Guardamos el nombre y precio AQUÍ también. 
            // ¿Por qué? Porque si mañana cambias el precio del producto original,
            // la factura vieja NO debe cambiar. Debe quedar histórica.
            $table->string('product_name'); 
            $table->decimal('unit_price', 15, 2);
            $table->integer('quantity');
            $table->decimal('subtotal', 15, 2); // unit_price * quantity
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
