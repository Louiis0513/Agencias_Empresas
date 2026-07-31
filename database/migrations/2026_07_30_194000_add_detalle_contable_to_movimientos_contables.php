<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->string('detalle_contable')->nullable()->after('tercero_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->dropColumn('detalle_contable');
        });
    }
};
