<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->foreignId('centro_costo_id')
                ->nullable()
                ->after('tercero_id')
                ->constrained('centros_costo')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_costo_id');
        });
    }
};
