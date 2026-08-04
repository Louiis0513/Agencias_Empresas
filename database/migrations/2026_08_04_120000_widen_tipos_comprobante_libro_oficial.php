<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->string('libro_oficial', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tipos_comprobante', function (Blueprint $table) {
            $table->string('libro_oficial', 16)->nullable()->change();
        });
    }
};
