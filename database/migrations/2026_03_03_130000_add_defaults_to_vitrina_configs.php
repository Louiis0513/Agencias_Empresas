<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Valores por defecto para paginación y rango de precios de la vitrina.
     */
    public function up(): void
    {
        Schema::table('vitrina_configs', function (Blueprint $table) {
            $table->unsignedInteger('default_page_size')
                ->nullable()
                ->after('show_plans')
                ->comment('Cantidad de productos por página en la vitrina (ej: 10, 20, 50)');

            $table->unsignedBigInteger('default_min_price')
                ->nullable()
                ->after('default_page_size')
                ->comment('Precio mínimo sugerido para filtro de vitrina');

            $table->unsignedBigInteger('default_max_price')
                ->nullable()
                ->after('default_min_price')
                ->comment('Precio máximo sugerido para filtro de vitrina');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vitrina_configs', function (Blueprint $table) {
            $table->dropColumn(['default_page_size', 'default_min_price', 'default_max_price']);
        });
    }
};

