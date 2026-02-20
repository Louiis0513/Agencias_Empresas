<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sesiones de caja: cabecera por turno. Sin totales globales; conciliación por bolsillo en sesion_caja_detalles.
     */
    public function up(): void
    {
        Schema::create('sesiones_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamp('opened_at');
            $table->string('nota_apertura')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('nota_cierre')->nullable();

            $table->timestamps();
        });

        Schema::table('sesiones_caja', function (Blueprint $table) {
            $table->index(['store_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_caja');
    }
};
