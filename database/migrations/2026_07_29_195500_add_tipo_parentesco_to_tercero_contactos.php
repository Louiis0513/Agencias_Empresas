<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tercero_contactos', function (Blueprint $table) {
            $table->string('tipo_contacto', 30)->nullable()->after('cargo');
            $table->string('parentesco', 100)->nullable()->after('tipo_contacto');
        });
    }

    public function down(): void
    {
        Schema::table('tercero_contactos', function (Blueprint $table) {
            $table->dropColumn(['tipo_contacto', 'parentesco']);
        });
    }
};
