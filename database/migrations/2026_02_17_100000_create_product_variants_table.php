<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla fuente de verdad para las variantes de productos por lote.
     * Cada fila representa una combinación única de atributos (features)
     * para un producto determinado. Almacena precio, costo de referencia,
     * código de barras y SKU propios de la variante.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->json('features')->comment('Combinación de atributos, ej: {"1":"M","2":"Rojo"}');
            $table->decimal('cost_reference', 12, 2)->default(0)->comment('Costo de referencia fijo');
            $table->decimal('price', 12, 2)->nullable()->comment('Precio al público de esta variante');
            $table->string('barcode')->nullable()->comment('Código de barras de la variante');
            $table->string('sku')->nullable()->comment('SKU de la variante');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
