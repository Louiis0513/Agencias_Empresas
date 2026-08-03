<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 32);
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->foreignId('parent_id')->nullable()->constrained('centros_costo')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'codigo']);
            $table->index(['store_id', 'parent_id']);
            $table->index(['store_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_costo');
    }
};
