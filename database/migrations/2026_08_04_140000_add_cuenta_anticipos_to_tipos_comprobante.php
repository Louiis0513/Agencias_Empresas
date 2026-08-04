<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->foreignId('cuenta_anticipos_id')
                ->nullable()
                ->after('libro_oficial')
                ->constrained('cuentas_contables')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_anticipos_id');
        });
    }
};
