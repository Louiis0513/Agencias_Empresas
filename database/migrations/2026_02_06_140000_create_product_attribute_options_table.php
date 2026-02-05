<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lista blanca: qué opciones de cada atributo (Talla: S, M, L) son válidas
     * para las variantes de ESTE producto. En compras solo se podrá elegir entre estas.
     */
    public function up(): void
    {
        Schema::create('product_attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_option_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'attribute_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_options');
    }
};
