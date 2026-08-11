<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_inventario_lineas', function (Blueprint $table) {
            $table->string('direccion', 20)->nullable()->after('descripcion');
            $table->foreignId('cuenta_contable_id')
                ->nullable()
                ->after('centro_costo_id')
                ->constrained('cuentas_contables')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documento_inventario_lineas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_contable_id');
            $table->dropColumn('direccion');
        });
    }
};
