<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 32);
            $table->string('nombre');
            $table->string('tipo', 20); // producto|servicio
            $table->foreignId('cuenta_inventario_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
            $table->foreignId('cuenta_costo_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
            $table->foreignId('cuenta_ingreso_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
            $table->foreignId('cuenta_devolucion_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'codigo']);
            $table->index(['store_id', 'tipo']);
            $table->index(['store_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_contables');
    }
};
