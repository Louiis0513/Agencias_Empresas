<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Movimientos de inventario: entradas y salidas de productos (solo type = producto).
     * Registros inmutables: no se editan ni eliminan.
     */
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            $table->string('type'); // ENTRADA | SALIDA
            $table->unsignedInteger('quantity');
            $table->string('description')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable()->comment('Costo unitario para reportes/valorización');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
