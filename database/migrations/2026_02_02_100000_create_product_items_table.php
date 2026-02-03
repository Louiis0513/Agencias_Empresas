<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Unidades individuales de productos serializados (product.type = 'serialized').
     */
    public function up(): void
    {
        Schema::create('product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('serial_number');
            $table->string('batch')->nullable()->comment('Para futuros lotes');
            $table->date('expiration_date')->nullable()->comment('Para futuros lotes con vencimiento');
            $table->decimal('cost', 12, 2)->default(0)->comment('Costo real de esta unidad específica');
            $table->string('status')->default('AVAILABLE'); // AVAILABLE, SOLD, RESERVED, DEFECTIVE
            $table->timestamps();

            $table->unique(['store_id', 'product_id', 'serial_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_items');
    }
};
