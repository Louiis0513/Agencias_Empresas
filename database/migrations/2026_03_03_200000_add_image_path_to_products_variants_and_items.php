<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('sku')->comment('Ruta relativa en el disco public para la imagen principal del producto');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('sku')->comment('Ruta relativa en el disco public para la imagen de la variante');
        });

        Schema::table('product_items', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('status')->comment('Ruta relativa en el disco public para la imagen de la unidad serializada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};

