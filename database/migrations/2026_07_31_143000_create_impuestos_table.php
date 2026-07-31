<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->boolean('en_uso')->default(true);
            $table->unsignedInteger('codigo');
            $table->string('nombre');
            $table->string('tipo', 64);
            $table->boolean('por_valor')->default(false);
            $table->decimal('tarifa', 8, 4)->default(0);
            $table->foreignId('cuenta_ventas_id')->constrained('cuentas_contables')->restrictOnDelete();
            $table->foreignId('cuenta_compras_id')->constrained('cuentas_contables')->restrictOnDelete();
            $table->foreignId('cuenta_devolucion_ventas_id')->constrained('cuentas_contables')->restrictOnDelete();
            $table->foreignId('cuenta_devolucion_compras_id')->constrained('cuentas_contables')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'codigo'], 'impuestos_store_codigo_unique');
            $table->index(['store_id', 'tipo', 'en_uso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impuestos');
    }
};
