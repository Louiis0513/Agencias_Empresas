<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Descripción del negocio (máx. 300) y horario en texto libre (máx. 500).
     */
    public function up(): void
    {
        Schema::table('vitrina_configs', function (Blueprint $table) {
            $table->string('description', 300)->nullable()->after('background_image_path')->comment('Descripción o eslogan del negocio');
            $table->string('schedule', 500)->nullable()->after('description')->comment('Horario en texto libre, ej: Lun a Sáb · 8:00 am – 7:00 pm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vitrina_configs', function (Blueprint $table) {
            $table->dropColumn(['description', 'schedule']);
        });
    }
};
