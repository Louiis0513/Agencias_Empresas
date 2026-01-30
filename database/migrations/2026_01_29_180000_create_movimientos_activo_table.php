<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Movimientos de activos: entradas y salidas (como inventario).
     * Permite trazabilidad: "4 sillas compradas en enero a $50" y "2 sillas en marzo a $65".
     */
    public function up(): void
    {
        Schema::create('movimientos_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('activo_id')->constrained()->onDelete('cascade');

            $table->string('type'); // ENTRADA | SALIDA
            $table->unsignedInteger('quantity');
            $table->string('description')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable()->comment('Costo unitario (entradas) para trazabilidad');

            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete()->comment('Si viene de una compra');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_activo');
    }
};
