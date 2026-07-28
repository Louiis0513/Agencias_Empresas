<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bolsillos', function (Blueprint $table) {
            $table->foreignId('cuenta_contable_id')
                ->nullable()
                ->after('store_id')
                ->constrained('cuentas_contables')
                ->restrictOnDelete();

            $table->unique('cuenta_contable_id');
            $table->index(['store_id', 'cuenta_contable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bolsillos', function (Blueprint $table) {
            $table->dropUnique(['cuenta_contable_id']);
            $table->dropIndex(['store_id', 'cuenta_contable_id']);
            $table->dropConstrainedForeignId('cuenta_contable_id');
        });
    }
};
