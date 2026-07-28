<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 32);
            $table->string('nombre');
            $table->string('clase', 80)->nullable();
            $table->string('categoria', 80)->nullable();
            $table->string('relacion_con', 120)->nullable();
            $table->string('maneja_vencimientos', 80)->nullable();
            $table->boolean('diferencia_fiscal')->default(false);
            $table->boolean('activo')->default(true);
            $table->string('nivel_agrupacion', 40)->nullable();
            $table->boolean('es_auxiliar')->default(false);
            $table->string('origen', 20)->default('plantilla'); // plantilla|manual
            $table->foreignId('cuenta_padre_id')->nullable()->constrained('cuentas_contables')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'codigo']);
            $table->index(['store_id', 'nivel_agrupacion']);
            $table->index(['store_id', 'es_auxiliar']);
            $table->index(['store_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_contables');
    }
};
