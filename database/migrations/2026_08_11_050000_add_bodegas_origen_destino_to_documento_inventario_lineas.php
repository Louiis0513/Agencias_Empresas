<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_inventario_lineas', function (Blueprint $table) {
            $table->foreignId('bodega_origen_id')
                ->nullable()
                ->after('bodega_id')
                ->constrained('bodegas')
                ->nullOnDelete();
            $table->foreignId('bodega_destino_id')
                ->nullable()
                ->after('bodega_origen_id')
                ->constrained('bodegas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documento_inventario_lineas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bodega_destino_id');
            $table->dropConstrainedForeignId('bodega_origen_id');
        });
    }
};
