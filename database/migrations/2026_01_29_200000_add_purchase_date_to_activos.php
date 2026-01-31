<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antigüedad: fecha real de compra del activo.
     */
    public function up(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->date('purchase_date')->nullable()->after('warranty_expiry')
                ->comment('Fecha real de compra del activo (antigüedad)');
        });
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->dropColumn('purchase_date');
        });
    }
};
