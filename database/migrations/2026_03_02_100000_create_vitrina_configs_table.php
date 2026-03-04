<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Configuración de la vitrina virtual por tienda: slug, imágenes, contactos y ubicaciones (JSON).
     */
    public function up(): void
    {
        Schema::create('vitrina_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->onDelete('cascade');
            $table->string('slug')->unique()->nullable()->comment('URL pública: /vitrina/{slug}');
            $table->string('cover_image_path')->nullable();
            $table->string('logo_image_path')->nullable();
            $table->string('background_image_path')->nullable();
            $table->boolean('show_products')->default(true);
            $table->boolean('show_plans')->default(true);
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
        Schema::dropIfExists('vitrina_configs');
    }
};
