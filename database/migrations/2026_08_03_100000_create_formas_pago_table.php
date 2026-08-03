<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formas_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->boolean('en_uso')->default(true);
            $table->unsignedInteger('codigo');
            $table->string('nombre');
            $table->string('aplica_a', 32);
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->restrictOnDelete();
            $table->string('medio_pago_dian', 8)->nullable();
            $table->boolean('es_pago_en_linea')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'codigo'], 'formas_pago_store_codigo_unique');
            $table->index(['store_id', 'aplica_a', 'en_uso']);
            $table->index(['store_id', 'cuenta_contable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formas_pago');
    }
};
