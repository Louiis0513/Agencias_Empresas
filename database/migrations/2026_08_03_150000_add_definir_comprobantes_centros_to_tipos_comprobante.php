<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->boolean('centro_costo_obligatorio')->default(false)->after('maneja_centro_costos');
            $table->foreignId('centro_costo_default_id')
                ->nullable()
                ->after('centro_costo_obligatorio')
                ->constrained('centros_costo')
                ->nullOnDelete();
        });

        // Compatibilidad: si ya manejaba centros, se consideraba obligatorio.
        DB::table('tipos_comprobante')
            ->where('maneja_centro_costos', true)
            ->update(['centro_costo_obligatorio' => true]);
    }

    public function down(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_costo_default_id');
            $table->dropColumn('centro_costo_obligatorio');
        });
    }
};
