<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes_ingreso', function (Blueprint $table) {
            $table->foreignId('tipo_comprobante_id')
                ->nullable()
                ->after('type')
                ->constrained('tipos_comprobante')
                ->restrictOnDelete();
            $table->string('modo', 32)->nullable()->after('tipo_comprobante_id');
            $table->foreignId('forma_pago_id')
                ->nullable()
                ->after('modo')
                ->constrained('formas_pago')
                ->nullOnDelete();
            $table->foreignId('centro_costo_id')
                ->nullable()
                ->after('forma_pago_id')
                ->constrained('centros_costo')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_ingreso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_costo_id');
            $table->dropConstrainedForeignId('forma_pago_id');
            $table->dropColumn('modo');
            $table->dropConstrainedForeignId('tipo_comprobante_id');
        });
    }
};
