<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Movimientos: soporte para eventos de lifecycle (ALTA, BAJA, CAMBIO_ESTADO, ASIGNACION, CAMBIO_UBICACION).
     * quantity nullable para eventos que no son entrada/salida; metadata opcional.
     */
    public function up(): void
    {
        DB::table('movimientos_activo')->where('type', 'ENTRADA')->update(['type' => 'ALTA']);
        DB::table('movimientos_activo')->where('type', 'SALIDA')->update(['type' => 'BAJA']);

        Schema::table('movimientos_activo', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->change();
            $table->json('metadata')->nullable()->after('description')
                ->comment('Estado anterior/nuevo, ubicación, etc.');
        });
    }

    public function down(): void
    {
        DB::table('movimientos_activo')->whereNull('quantity')->update(['quantity' => 1]);
        Schema::table('movimientos_activo', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable(false)->change();
            $table->dropColumn('metadata');
        });
        DB::table('movimientos_activo')->where('type', 'ALTA')->update(['type' => 'ENTRADA']);
        DB::table('movimientos_activo')->where('type', 'BAJA')->update(['type' => 'SALIDA']);
    }
};
