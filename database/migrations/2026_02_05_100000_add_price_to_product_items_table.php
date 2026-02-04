<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Precio de venta por unidad (serializados pueden tener precio distinto cada uno).
     * Nullable: si viene de compra puede quedar en 0 hasta que se asigne.
     */
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->after('cost')
                ->comment('Precio de venta de esta unidad; null/0 hasta asignar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
