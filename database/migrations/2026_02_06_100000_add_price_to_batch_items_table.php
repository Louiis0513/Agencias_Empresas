<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Precio de venta por variante (ej. talla XXL más cara).
     * Nullable: si es NULL se usa el precio base del Product; si tiene valor es excepción para esa variante.
     */
    public function up(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->after('unit_cost')
                ->comment('Precio de venta de esta variante; null = usar precio del Product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batch_items', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
