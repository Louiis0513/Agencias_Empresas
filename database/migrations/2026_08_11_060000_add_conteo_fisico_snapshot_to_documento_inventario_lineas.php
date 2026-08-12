<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_inventario_lineas', function (Blueprint $table) {
            $table->decimal('cantidad_sistema', 18, 4)->nullable()->after('cantidad');
            $table->decimal('cantidad_contada', 18, 4)->nullable()->after('cantidad_sistema');
        });
    }

    public function down(): void
    {
        Schema::table('documento_inventario_lineas', function (Blueprint $table) {
            $table->dropColumn(['cantidad_sistema', 'cantidad_contada']);
        });
    }
};
