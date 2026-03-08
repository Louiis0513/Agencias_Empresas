<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Configuración propia del Panel de Suscripciones por tienda: slug, imágenes, contactos, ubicación.
     */
    public function up(): void
    {
        Schema::create('panel_suscripciones_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->onDelete('cascade');
            $table->string('slug')->unique()->nullable()->comment('URL pública: /{slug}/PanelSuscripciones');
            $table->string('description', 300)->nullable()->comment('Descripción o eslogan del negocio');
            $table->string('schedule', 500)->nullable()->comment('Horario en texto libre');
            $table->string('cover_image_path')->nullable();
            $table->string('logo_image_path')->nullable();
            $table->string('background_image_path')->nullable();
            $table->string('main_background_color', 50)->nullable();
            $table->string('primary_color', 50)->nullable();
            $table->string('secondary_color', 50)->nullable();
            $table->json('whatsapp_contacts')->nullable()->comment('[{value, location_index}, ...] max 5');
            $table->json('phone_contacts')->nullable()->comment('[{value, location_index}, ...] max 5');
            $table->json('locations')->nullable()->comment('[{map_iframe_src}, ...] max 5');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_suscripciones_configs');
    }
};
