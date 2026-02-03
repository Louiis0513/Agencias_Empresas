<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Atributos específicos de cada unidad (color, memoria, talla, etc.).
     */
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            $table->json('features')->nullable()->after('status')
                ->comment('Atributos de esta unidad: {"color":"Rojo","memoria":"128GB"}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
