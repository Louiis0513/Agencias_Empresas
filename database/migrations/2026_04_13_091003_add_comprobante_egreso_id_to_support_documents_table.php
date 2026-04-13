<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('support_documents', function (Blueprint $table) {
            $table->foreignId('comprobante_egreso_id')
                ->nullable()
                ->after('proveedor_id')
                ->constrained('comprobantes_egreso')
                ->onDelete('set null');
            $table->index('comprobante_egreso_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_documents', function (Blueprint $table) {
            $table->dropIndex(['comprobante_egreso_id']);
            $table->dropForeign(['comprobante_egreso_id']);
            $table->dropColumn('comprobante_egreso_id');
        });
    }
};
