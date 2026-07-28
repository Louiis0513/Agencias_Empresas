<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_comprobante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('familia', 8); // FV|RC|FC|RP|CC
            $table->string('codigo', 32);
            $table->string('nombre');
            $table->string('titulo')->nullable();
            $table->string('prefijo', 16);
            $table->boolean('numeracion_automatica')->default(true);
            $table->unsignedInteger('siguiente_numero')->default(1);
            $table->boolean('activo')->default(true);
            $table->boolean('maneja_centro_costos')->default(false);
            $table->string('libro_oficial', 16)->nullable(); // ventas|compras|null
            $table->timestamps();

            $table->unique(['store_id', 'familia', 'codigo']);
            $table->index(['store_id', 'familia']);
            $table->index(['store_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_comprobante');
    }
};
