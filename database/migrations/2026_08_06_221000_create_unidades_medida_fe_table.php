<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo DIAN de unidades de medida para factura electrónica.
 * Sin id autoincrement: PK natural = codigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_medida_fe', function (Blueprint $table) {
            $table->string('codigo', 40)->primary();
            $table->string('nombre', 120);
        });

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'unidad_medida_dian')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE products MODIFY unidad_medida_dian VARCHAR(40) NOT NULL DEFAULT '94'");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_medida_fe');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'unidad_medida_dian')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE products MODIFY unidad_medida_dian VARCHAR(10) NOT NULL DEFAULT '94'");
            }
        }
    }
};
