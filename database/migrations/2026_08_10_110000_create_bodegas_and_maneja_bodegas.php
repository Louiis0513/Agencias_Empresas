<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('maneja_bodegas')->default(false)->after('logo_path');
        });

        Schema::create('bodegas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 32);
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'codigo']);
            $table->index(['store_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bodegas');

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('maneja_bodegas');
        });
    }
};
