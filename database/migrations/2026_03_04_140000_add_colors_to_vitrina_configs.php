<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Colores personalizables para la vitrina (fondo del contenido y 2 colores de acento).
     */
    public function up(): void
    {
        Schema::table('vitrina_configs', function (Blueprint $table) {
            $table->string('main_background_color', 50)
                ->nullable()
                ->after('default_max_price')
                ->comment('Color de fondo del contenedor principal (main) de la vitrina');

            $table->string('primary_color', 50)
                ->nullable()
                ->after('main_background_color')
                ->comment('Color principal para botones y acentos (ej: WhatsApp, Ver catálogo)');

            $table->string('secondary_color', 50)
                ->nullable()
                ->after('primary_color')
                ->comment('Color secundario para botones alternos/inversos (ej: Ver ubicaciones, Llamar)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vitrina_configs', function (Blueprint $table) {
            $table->dropColumn(['main_background_color', 'primary_color', 'secondary_color']);
        });
    }
};

